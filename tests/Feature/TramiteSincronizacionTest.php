<?php

namespace Tests\Feature;

use App\Models\Requisito;
use App\Models\Tramite;
use App\Services\TramiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cómo TramiteService sincroniza los requisitos de un trámite al editarlo.
 *
 * ── Cómo funciona de verdad, explicado desde cero ────────────────────
 *
 * Cuando abres un trámite para editarlo, la pantalla vuelve a pintar cada requisito que
 * ya tenía, y a cada uno le mete su identificador en un campo oculto:
 *
 *     <input type="hidden" name="requisitos[0][id]" value="40">
 *     <input name="requisitos[0][nombre]" value="Identificación oficial">
 *
 * Al guardar, el formulario manda TODOS los requisitos de vuelta, cada uno con su id.
 * Y sincronizarRequisitos() los compara con lo que hay en la base:
 *
 *     - ¿Vino con id?      → ya existía: se ACTUALIZA en su sitio, conserva su id.
 *     - ¿Vino sin id?      → es nuevo: se CREA.
 *     - ¿Estaba y no vino? → el usuario lo quitó del formulario: se BORRA.
 *
 * La última regla es la única que borra algo, y es la que hace posible que quitar una
 * fila de la pantalla se refleje en la base. Sin ella, un requisito eliminado seguiría
 * vivo para siempre.
 *
 * ── El id oculto es la pieza que sostiene todo ───────────────────────
 *
 * Si algún día alguien quita ese campo del blade "porque no se ve y no sirve para nada",
 * el sistema dejaría de reconocer los requisitos existentes: los borraría y los recrearía
 * con ids nuevos en CADA guardado.
 *
 * ¿Se notaría? Casi no. El trámite seguiría mostrando sus tres requisitos, con los mismos
 * nombres. Pero sus ids cambiarían cada vez, y con ellos se romperían en silencio las
 * regulaciones citadas que cuelgan de cada requisito, y cualquier otra cosa que apunte a
 * un requisito por su id.
 *
 * Por eso la primera prueba de este archivo no cuenta CUÁNTOS requisitos quedan:
 * comprueba que sean LOS MISMOS, con sus mismos ids. Contar filas no habría detectado
 * nada.
 */
class TramiteSincronizacionTest extends TestCase
{
    use RefreshDatabase;

    private TramiteService $tramites;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tramites = app(TramiteService::class);
    }

    /**
     * Un requisito NUEVO, tal y como lo manda el formulario cuando el usuario acaba de
     * añadir la fila: sin id, porque todavía no existe en la base.
     */
    private function requisitoNuevo(string $nombre): array
    {
        return [
            'nombre'  => $nombre,
            'tipo'    => ['documento'],
            'dias'    => 0,
            'horas'   => 1,
            'minutos' => 0,
        ];
    }

    /**
     * Un requisito que YA EXISTE, tal y como lo manda el formulario de edición: con su id
     * en el campo oculto. Este es el caso normal, el de todos los días.
     */
    private function requisitoExistente(Requisito $requisito, ?string $nombre = null): array
    {
        return [
            'id'      => $requisito->id,
            'nombre'  => $nombre ?? $requisito->nombre,
            'tipo'    => ['documento'],
            'dias'    => 0,
            'horas'   => 1,
            'minutos' => 0,
        ];
    }

    private function requisitosDe(Tramite $tramite)
    {
        return Requisito::where('tramite_id', $tramite->id)->orderBy('id')->get();
    }

    /**
     * Llama a actualizar() con los seis argumentos. Hay que pasarlos todos: actualizar()
     * ya no permite omitir las colecciones que sincroniza, precisamente para que nadie las
     * borre sin querer por no mencionarlas.
     */
    private function guardar(Tramite $tramite, array $requisitos): void
    {
        $this->tramites->actualizar(
            tramite:     $tramite,
            datos:       ['nombre_oficial' => $tramite->nombre_oficial],
            derechos:    [],
            requisitos:  $requisitos,
            fichaPortal: [],
            procesos:    [],
        );
    }

    /** Un trámite con tres requisitos ya guardados, listo para editarse. */
    private function tramiteConTresRequisitos(): Tramite
    {
        $tramite = Tramite::factory()->create();

        $this->guardar($tramite, [
            $this->requisitoNuevo('Identificación oficial'),
            $this->requisitoNuevo('Comprobante de domicilio'),
            $this->requisitoNuevo('Acta constitutiva'),
        ]);

        return $tramite->fresh();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El caso de todos los días: editar y guardar
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA QUE MÁS FALTA HACÍA.
     *
     * Abres un trámite, no tocas los requisitos, y guardas. Los tres siguen ahí — y son
     * LOS MISMOS, no tres copias nuevas.
     *
     * Fíjate en que compara IDS, no cuenta filas. Si solo contara, un sistema que los
     * borrara y recreara en cada guardado pasaría la prueba tan campante: seguiría
     * habiendo tres. Pero los ids habrían cambiado, y con ellos se habrían roto en
     * silencio las regulaciones citadas de cada requisito.
     *
     * Contar filas no habría detectado nada. Comparar identidades sí.
     */
    public function test_editar_y_guardar_sin_tocar_los_requisitos_los_deja_intactos(): void
    {
        $tramite = $this->tramiteConTresRequisitos();
        $antes   = $this->requisitosDe($tramite);

        // El formulario de edición devuelve los tres, cada uno con su id oculto.
        $this->guardar($tramite, $antes->map(fn ($r) => $this->requisitoExistente($r))->all());

        $despues = $this->requisitosDe($tramite);

        $this->assertSame(
            $antes->pluck('id')->all(),
            $despues->pluck('id')->all(),
            'Los requisitos cambiaron de id al guardar: el sistema los borró y los recreó en '
            . 'vez de actualizarlos. Todo lo que apunte a un requisito por su id (las '
            . 'regulaciones citadas, por ejemplo) se rompe en silencio.'
        );

        $this->assertSame(
            ['Identificación oficial', 'Comprobante de domicilio', 'Acta constitutiva'],
            $despues->pluck('nombre')->all()
        );
    }

    /** Editar el nombre de un requisito lo cambia en su sitio, sin crear otro. */
    public function test_editar_el_nombre_de_un_requisito_no_crea_uno_nuevo(): void
    {
        $tramite = $this->tramiteConTresRequisitos();
        $antes   = $this->requisitosDe($tramite);

        $this->guardar($tramite, [
            $this->requisitoExistente($antes[0], 'Identificación oficial vigente'), // renombrado
            $this->requisitoExistente($antes[1]),
            $this->requisitoExistente($antes[2]),
        ]);

        $despues = $this->requisitosDe($tramite);

        $this->assertCount(3, $despues);
        $this->assertSame($antes[0]->id, $despues[0]->id, 'Debe ser el MISMO requisito, renombrado.');
        $this->assertSame('Identificación oficial vigente', $despues[0]->nombre);
    }

    /** Añadir una fila nueva no toca las que ya estaban. */
    public function test_agregar_un_requisito_conserva_los_anteriores(): void
    {
        $tramite = $this->tramiteConTresRequisitos();
        $antes   = $this->requisitosDe($tramite);

        $this->guardar($tramite, [
            ...$antes->map(fn ($r) => $this->requisitoExistente($r))->all(),
            $this->requisitoNuevo('Poder notarial'), // el nuevo, sin id
        ]);

        $despues = $this->requisitosDe($tramite);

        $this->assertCount(4, $despues);
        $this->assertSame(
            $antes->pluck('id')->all(),
            $despues->take(3)->pluck('id')->all(),
            'Los tres de antes deben seguir siendo los mismos.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Quitar filas: lo único que borra algo
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El usuario quita UNA fila del formulario y guarda. Se borra esa, y solo esa.
     *
     * Así es como el sistema se entera de que quitaste un requisito: no llega ninguna
     * orden de "borra el 41". Llega un envío donde el 41 sencillamente NO está, y el
     * servicio deduce que lo quitaste.
     */
    public function test_quitar_una_fila_borra_solo_esa(): void
    {
        $tramite = $this->tramiteConTresRequisitos();
        $antes   = $this->requisitosDe($tramite);

        // El usuario borra la fila del medio: el formulario manda la primera y la tercera.
        $this->guardar($tramite, [
            $this->requisitoExistente($antes[0]),
            $this->requisitoExistente($antes[2]),
        ]);

        $despues = $this->requisitosDe($tramite);

        $this->assertSame(
            [$antes[0]->id, $antes[2]->id],
            $despues->pluck('id')->all(),
            'Debe borrarse exactamente el requisito que el usuario quitó, y ninguno más.'
        );
    }

    /**
     * El usuario quita TODAS las filas y guarda. Se borran todas.
     *
     * ── Que quede claro qué NO significa esta prueba ──
     *
     * NO significa "editar un trámite borra sus requisitos". Eso no pasa: el formulario de
     * edición siempre devuelve los requisitos existentes con su id oculto, así que nunca
     * llega vacío por accidente.
     *
     * Un envío vacío significa una sola cosa: el usuario quitó todas las filas a propósito.
     * Y entonces borrarlas es lo correcto.
     *
     * La prueba existe para dejar esa regla ESCRITA, para que nadie la "arregle" pensando
     * que es un bug y deje requisitos zombis imposibles de borrar desde la pantalla.
     */
    public function test_quitar_todas_las_filas_borra_todos_los_requisitos(): void
    {
        $tramite = $this->tramiteConTresRequisitos();
        $this->assertCount(3, $this->requisitosDe($tramite));

        $this->guardar($tramite, []);

        $this->assertCount(0, $this->requisitosDe($tramite));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. La protección de la firma del método
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * actualizar() no permite OMITIR las colecciones que sincroniza.
     *
     * Es una protección contra un llamador futuro, no contra el de hoy. Mientras esos
     * parámetros tuvieran valor por defecto (`= []`), estas dos frases se escribían
     * exactamente igual:
     *
     *     "el trámite no tiene ningún requisito"
     *     "no te estoy mandando los requisitos, no los toques"
     *
     * El único llamador actual (TramiteController::update) los manda siempre, así que hoy
     * no hay problema. Pero el día que alguien añada un endpoint de API, o una acción de
     * "atender observación" que solo toque un campo, escribiría lo que parece obvio:
     *
     *     $this->tramiteService->actualizar($tramite, ['dependencia_id' => 5]);
     *
     * Y esa línea borraría TODOS los requisitos, TODOS los derechos y TODA la ficha del
     * portal. En silencio, sin error, sin nada en la bitácora que lo explique.
     *
     * Sin valores por defecto, esa línea ni siquiera arranca: PHP se queja de que faltan
     * argumentos. El fallo salta al escribir el código, no en producción tres meses después.
     */
    public function test_actualizar_no_permite_omitir_las_colecciones_que_sincroniza(): void
    {
        $parametros = (new ReflectionMethod(TramiteService::class, 'actualizar'))->getParameters();

        foreach ($parametros as $parametro) {
            if (! in_array($parametro->getName(), ['derechos', 'requisitos', 'fichaPortal', 'procesos'], true)) {
                continue;
            }

            $this->assertFalse(
                $parametro->isOptional(),
                "El parámetro \${$parametro->getName()} de actualizar() volvió a tener un valor "
                . "por defecto. Eso hace que omitirlo y vaciarlo se escriban igual, y convierte "
                . "cualquier actualización parcial futura en un borrado silencioso. Si de verdad "
                . "quieres vaciarlo, pasa [] a propósito."
            );
        }
    }

    /**
     * crear(), en cambio, SÍ permite omitirlas. Y es deliberado.
     *
     * Al crear no hay nada que perder: un array vacío significa "todavía no hay ninguno",
     * que es cierto. La ambigüedad solo existe al actualizar.
     *
     * Además hay un llamador real que depende de ello: AgendaService crea trámites sin
     * pasar `procesos`, porque desde la agenda todavía no se capturan.
     *
     * Esta prueba deja escrita la asimetría, para que quien lea las dos firmas entienda que
     * la diferencia es intencionada y no un descuido — y no las "unifique" rompiendo la
     * agenda.
     */
    public function test_crear_si_permite_omitir_las_colecciones(): void
    {
        $parametros = (new ReflectionMethod(TramiteService::class, 'crear'))->getParameters();
        $procesos   = collect($parametros)->firstWhere(fn ($p) => $p->getName() === 'procesos');

        $this->assertNotNull($procesos);
        $this->assertTrue(
            $procesos->isOptional(),
            'AgendaService crea trámites sin pasar procesos. Si se quita el valor por defecto '
            . 'de crear(), ese flujo deja de funcionar.'
        );
    }
}
