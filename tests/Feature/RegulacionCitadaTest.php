<?php

namespace Tests\Feature;

use App\Models\FundamentoJuridico;
use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Models\Tramite;
use App\Models\User;
use App\Notifications\AvisoPunta;
use App\Services\NotificadorService;
use App\Services\RegulacionEstructuradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Qué pasa con los trámites cuando la regulación que los fundamenta cambia.
 *
 * ── El problema, explicado desde cero ────────────────────────────────
 *
 * Un trámite se apoya en la ley. En la tabla `fundamento_juridico` se guarda algo así:
 *
 *     tramite_id: 7
 *     regulacion_id: 12          ← Reglamento de Comercio
 *     articulo_fraccion: "Artículo 15, fracción II"   ← ¡COMO TEXTO!
 *
 * Fíjate en que la cita guarda el artículo **como texto**, no como una referencia al nodo
 * del articulado. Eso tiene una consecuencia buena y una mala:
 *
 *   BUENA: cuando se reestructura la regulación, el articulado entero se borra y se recrea
 *          con ids nuevos. Si las citas apuntaran a esos ids, se romperían todas. Al ser
 *          texto, sobreviven.
 *
 *   MALA:  sobreviven apuntando a un artículo que puede decir OTRA COSA. El "Artículo 15"
 *          del reglamento reestructurado quizá ya no hable de licencias, sino de horarios.
 *          La cita sigue siendo válida sintácticamente y falsa en el fondo.
 *
 * Nada en el sistema puede detectar eso automáticamente: hace falta que una PERSONA lea el
 * artículo nuevo y decida si el trámite sigue bien fundamentado.
 *
 * ── Lo que el sistema hace, y está bien hecho ────────────────────────
 *
 * 1. NO deja borrar una regulación citada. Punto.
 *
 * 2. Al reestructurar, calcula los trámites afectados ANTES de tocar el articulado —si lo
 *    hiciera después ya no sabría qué artículos estaban citados— y avisa al enlace y al
 *    creador de cada trámite, diciéndoles QUÉ artículos referenciaban.
 *
 * Estas pruebas protegen ese aviso. Es lo único que hay entre una reestructuración y un
 * trámite que queda mal fundamentado en silencio.
 */
class RegulacionCitadaTest extends TestCase
{
    use RefreshDatabase;

    private RegulacionEstructuradorService $estructurador;
    private NotificadorService $notificador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->estructurador = app(RegulacionEstructuradorService::class);
        $this->notificador   = app(NotificadorService::class);
    }

    /** Una regulación ya convertida, con su Markdown en el disco falso. */
    private function regulacionCon(string $markdown): Regulacion
    {
        $regulacion = Regulacion::factory()->convertida()->create(['nombre' => 'Reglamento de Comercio']);
        Storage::disk('local')->put($regulacion->archivo_markdown, $markdown);

        return $regulacion;
    }

    /** Un trámite que cita un artículo de la regulación como fundamento. */
    private function tramiteQueCita(Regulacion $regulacion, string $articulo, array $extra = []): Tramite
    {
        $tramite = Tramite::factory()->create(array_merge([
            'nombre_oficial' => 'Licencia de funcionamiento',
        ], $extra));

        FundamentoJuridico::create([
            'tramite_id'        => $tramite->id,
            'regulacion_id'     => $regulacion->id,
            'normativa_nombre'  => $regulacion->nombre,
            'articulo_fraccion' => $articulo,
        ]);

        return $tramite->fresh();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. citacionesEnTramites(): quién depende de esta ley
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un trámite que cita TRES artículos de la misma regulación cuenta como UN trámite
     * afectado, no como tres.
     *
     * Importa porque ese número se le enseña al usuario ("se notificó a N trámites") y
     * porque decide si se envía el aviso. Si contara citas en vez de trámites, un solo
     * trámite con muchos fundamentos inflaría el conteo y el mensaje mentiría.
     */
    public function test_un_tramite_con_varias_citas_cuenta_como_un_solo_tramite_afectado(): void
    {
        $regulacion = $this->regulacionCon("Artículo 1. Objeto.\n");
        $tramite    = $this->tramiteQueCita($regulacion, 'Artículo 15');

        // El mismo trámite cita dos artículos más de la misma regulación.
        foreach (['Artículo 16', 'Artículo 17, fracción II'] as $otro) {
            FundamentoJuridico::create([
                'tramite_id'        => $tramite->id,
                'regulacion_id'     => $regulacion->id,
                'normativa_nombre'  => $regulacion->nombre,
                'articulo_fraccion' => $otro,
            ]);
        }

        $citaciones = $regulacion->citacionesEnTramites();

        $this->assertSame(1, $citaciones['total'], 'Es UN trámite afectado, aunque cite tres artículos.');
        $this->assertCount(3, $citaciones['articulos'], 'Pero los TRES artículos deben aparecer en el aviso.');
    }

    /** Una regulación que nadie cita no tiene trámites afectados. */
    public function test_una_regulacion_sin_citas_no_tiene_tramites_afectados(): void
    {
        $regulacion = $this->regulacionCon("Artículo 1. Objeto.\n");

        $this->assertSame(0, $regulacion->citacionesEnTramites()['total']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El aviso al reestructurar
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA CENTRAL DE ESTE ARCHIVO.
     *
     * Al reestructurar, se avisa al ENLACE y al CREADOR de cada trámite afectado. A los dos:
     * el enlace es quien mantiene el trámite, pero puede haber cambiado desde que se
     * registró, y el creador es quien puso la cita y sabe por qué.
     *
     * El aviso dice QUÉ ARTÍCULOS estaban citados. Un aviso que solo dijera "esta regulación
     * cambió, revisa tu trámite" obligaría a revisarlo entero: nadie lo haría. Diciendo
     * "referenciabas el Artículo 15", el enlace sabe exactamente adónde mirar.
     */
    public function test_al_reestructurar_se_avisa_al_enlace_y_al_creador_de_cada_tramite_citante(): void
    {
        Notification::fake();

        $enlace  = User::factory()->create();
        $creador = User::factory()->create();

        $regulacion = $this->regulacionCon("Artículo 1. Objeto.\n\nArtículo 15. De las licencias.\n");

        $tramite = $this->tramiteQueCita($regulacion, 'Artículo 15, fracción II', [
            'enlace_id'  => $enlace->id,
            'created_by' => $creador->id,
        ]);

        // Se calculan ANTES de tocar el articulado: después ya no se sabría qué se citaba.
        $citaciones = $regulacion->citacionesEnTramites();
        $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->notificador->regulacionReEstructurada($regulacion, $creador, $citaciones);

        Notification::assertSentTo([$enlace, $creador], AvisoPunta::class);
    }

    /**
     * Reestructurar una regulación que NADIE cita no molesta a nadie.
     *
     * Es la mitad que casi nadie escribe, y sin ella la prueba anterior no vale nada: un
     * sistema que notificara a TODO el mundo en CADA reestructuración la pasaría igual. Y un
     * sistema que avisa siempre es tan inútil como uno que no avisa nunca — la gente aprende
     * a ignorar el aviso, y entonces tampoco lo lee el día que sí importa.
     */
    public function test_reestructurar_una_regulacion_sin_citas_no_notifica_a_nadie(): void
    {
        Notification::fake();

        $regulacion = $this->regulacionCon("Artículo 1. Objeto.\n");

        $citaciones = $regulacion->citacionesEnTramites();
        $this->estructurador->importarDesdeMarkdown($regulacion);
        $this->notificador->regulacionReEstructurada($regulacion, User::factory()->create(), $citaciones);

        Notification::assertNothingSent();
    }

    /**
     * EL AGUJERO — ESTA PRUEBA FALLA HOY.
     *
     * Mira lo que hace NotificadorService::regulacionReEstructurada():
     *
     *     $destinatarios = collect([$enlace, $creador])->filter()->unique('id');
     *
     *     if ($destinatarios->isEmpty()) {
     *         continue;          // ← nadie se entera, y no queda rastro
     *     }
     *
     * Si un trámite afectado no tiene enlace NI creador —los dos campos son nullable—, el
     * bucle pasa de largo en silencio. Sin log, sin excepción, sin nada.
     *
     * Resultado: la regulación se reestructura, ese trámite queda con un fundamento que puede
     * apuntar a un artículo que ahora dice otra cosa, y NADIE LO SABRÁ NUNCA.
     *
     * Es el mismo patrón que ya encontramos en CambioPostFirmaService::notificar(): el sistema
     * funciona perfectamente y nadie se entera de nada. El peor tipo de fallo, porque no deja
     * ni una pista de que ocurrió.
     *
     * Esta prueba exige que, cuando no haya a quién avisar, al menos quede constancia. Está
     * escrita en ROJO a propósito: quiero que la veas fallar antes de arreglar el servicio.
     */
    public function test_un_tramite_sin_enlace_ni_creador_deja_constancia_en_el_log(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Log::spy();

        $regulacion = $this->regulacionCon("Artículo 15. De las licencias.\n");

        // Un trámite huérfano: sin enlace y sin creador. Los dos campos son nullable.
        $this->tramiteQueCita($regulacion, 'Artículo 15', [
            'enlace_id'  => null,
            'created_by' => null,
        ]);

        $citaciones = $regulacion->citacionesEnTramites();
        $this->estructurador->importarDesdeMarkdown($regulacion);
        $this->notificador->regulacionReEstructurada($regulacion, User::factory()->create(), $citaciones);

        // No se pudo avisar a nadie. Vale. Pero TIENE que quedar escrito.
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn ($mensaje) => str_contains((string) $mensaje, 'no se pudo avisar')
                                     || str_contains((string) $mensaje, 'sin destinatarios'));
    }

    /**
     * EL CENTINELA DEL BUG QUE MÁS TIEMPO SOBREVIVIÓ.
     *
     * citacionesEnTramites() carga los trámites con una lista EXPLÍCITA de columnas:
     *
     *     ->with('tramite:id,nombre_oficial,homoclave,enlace_id,created_by')
     *
     * Durante meses esa lista fue `id,nombre_oficial,homoclave`. Faltaban enlace_id y
     * created_by — justo los dos que NotificadorService necesita para saber a quién avisar.
     *
     * Y aquí está lo traicionero: una columna que no se carga NO da error. Vale `null`, como
     * si el trámite no tuviera enlace. Los dos ternarios del notificador devolvían null, la
     * lista de destinatarios salía vacía, el `continue` se lo tragaba, y el aviso no se
     * enviaba NUNCA. Para ningún trámite. Ni una vez.
     *
     * Esta prueba mira directamente el modelo cargado. Si alguien "optimiza" la consulta
     * quitando una columna, se pone roja al instante y explica por qué.
     *
     * Una prueba sobre las notificaciones sola no bastaba: la que escribí primero
     * (test_al_reestructurar_se_avisa...) sí cazó el bug, pero no decía DÓNDE estaba. Esta
     * señala el sitio exacto.
     */
    public function test_las_citaciones_cargan_las_columnas_que_el_notificador_necesita(): void
    {
        $enlace  = User::factory()->create();
        $creador = User::factory()->create();

        $regulacion = $this->regulacionCon("Artículo 15. De las licencias.\n");

        $this->tramiteQueCita($regulacion, 'Artículo 15', [
            'enlace_id'  => $enlace->id,
            'created_by' => $creador->id,
        ]);

        $tramiteCargado = $regulacion->citacionesEnTramites()['tramites']->first();

        $this->assertSame(
            $enlace->id,
            $tramiteCargado->enlace_id,
            'citacionesEnTramites() no está cargando enlace_id. El notificador lo lee para saber '
            . 'a quién avisar; si viene null, no avisa a NADIE y no da ningún error.'
        );

        $this->assertSame(
            $creador->id,
            $tramiteCargado->created_by,
            'citacionesEnTramites() no está cargando created_by. Mismo problema: sin él, el '
            . 'creador del trámite nunca se entera de que su fundamento jurídico cambió.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. La cita sobrevive, pero puede quedar apuntando a otra cosa
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Reestructurar NO rompe las citas: sobreviven, porque guardan el artículo como TEXTO.
     *
     * Si guardaran el id del nodo, se romperían todas — el articulado se borra entero y se
     * recrea con ids nuevos en cada reestructuración.
     */
    public function test_reestructurar_no_borra_los_fundamentos_juridicos(): void
    {
        $regulacion = $this->regulacionCon("Artículo 15. De las licencias comerciales.\n");
        $tramite    = $this->tramiteQueCita($regulacion, 'Artículo 15');

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->assertDatabaseHas('fundamento_juridico', [
            'tramite_id'        => $tramite->id,
            'regulacion_id'     => $regulacion->id,
            'articulo_fraccion' => 'Artículo 15',
        ]);
    }

    /**
     * EL CASO QUE NADIE PUEDE DETECTAR AUTOMÁTICAMENTE, Y POR ESO EL AVISO ES TODO.
     *
     * El trámite cita el "Artículo 15", que hablaba de licencias. La regulación se
     * reestructura con un texto nuevo donde el Artículo 15 habla de HORARIOS.
     *
     * La cita sigue ahí. Sigue siendo válida: el Artículo 15 existe. Y es FALSA de fondo: el
     * trámite ya no está fundamentado en lo que decía estarlo.
     *
     * Ningún sistema puede detectar eso solo. Hace falta que una persona lea el artículo
     * nuevo y decida. Y para que lo lea, hay que avisarle — que es exactamente lo que hace la
     * notificación de arriba.
     *
     * Esta prueba deja el escenario ESCRITO, para que quien lea este archivo entienda por qué
     * el aviso importa tanto y no lo quite pensando que es ruido.
     */
    public function test_la_cita_sobrevive_aunque_el_articulo_pase_a_decir_otra_cosa(): void
    {
        $regulacion = $this->regulacionCon("Artículo 15. De las licencias de funcionamiento.\n");
        $tramite    = $this->tramiteQueCita($regulacion, 'Artículo 15');

        $this->estructurador->importarDesdeMarkdown($regulacion);

        // Se sube una versión nueva del reglamento: el Artículo 15 ahora habla de otra cosa.
        Storage::disk('local')->put(
            $regulacion->archivo_markdown,
            "Artículo 15. De los horarios de atención al público.\n"
        );
        $this->estructurador->importarDesdeMarkdown($regulacion->fresh());

        // La cita sigue viva y sigue apuntando al "Artículo 15"...
        $this->assertDatabaseHas('fundamento_juridico', [
            'tramite_id'        => $tramite->id,
            'articulo_fraccion' => 'Artículo 15',
        ]);

        // ...pero el Artículo 15 ya no dice lo que decía.
        $articulo15 = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->where('numero', '15')
            ->first();

        $this->assertNotNull($articulo15);
        $this->assertStringContainsString(
            'horarios',
            mb_strtolower((string) $articulo15->texto),
            'El artículo cambió de contenido y la cita no se enteró. Por eso el aviso al enlace '
            . 'es lo ÚNICO que impide que este trámite quede mal fundamentado en silencio.'
        );
    }
}
