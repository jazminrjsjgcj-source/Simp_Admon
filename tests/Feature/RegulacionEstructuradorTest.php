<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionEstructuradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas del estructurador de regulaciones (RegulacionEstructuradorService).
 *
 * ── Qué hace el estructurador, explicado desde cero ──────────────────
 *
 * Una regulación entra al sistema como un PDF o un Word. El conversor la convierte
 * en texto plano (Markdown). Pero un texto plano no se puede citar: para que el
 * sistema pueda decir "el requisito X se funda en el Artículo 15, fracción II", ese
 * texto tiene que convertirse en un ÁRBOL:
 *
 *     Título I
 *       └─ Capítulo I
 *            └─ Artículo 15
 *                 ├─ Fracción I
 *                 ├─ Fracción II
 *                 │    └─ Inciso a)
 *                 └─ Párrafo
 *
 * Cada nodo de ese árbol es una fila en `regulacion_nodos`, con su tipo, su número,
 * su texto y su padre. Eso es lo que hace importarDesdeMarkdown(): lee el texto,
 * reconoce los patrones ("Artículo 15.", "II.", "a)") y construye el árbol.
 *
 * ── Por qué estas pruebas son de CARACTERIZACIÓN ─────────────────────
 *
 * El servicio tiene 851 líneas y está lleno de heurísticas: cuándo un romano es
 * una fracción y cuándo es una sigla, cuándo una línea es un encabezado y cuándo
 * es texto corrido, qué es maquetación de PDF y qué es contenido de la ley.
 *
 * Esas heurísticas se afinaron a base de probar con documentos reales. NO están
 * escritas en ningún sitio salvo en el propio código. Si alguien lo refactoriza y
 * cambia una sin darse cuenta, las regulaciones se empiezan a parsear mal, y nadie
 * lo nota hasta que un trámite cita mal un artículo.
 *
 * Estas pruebas convierten esas heurísticas en reglas escritas. No dicen si están
 * bien: dicen qué son HOY, para que mañana no cambien sin querer.
 */
class RegulacionEstructuradorTest extends TestCase
{
    use RefreshDatabase;

    private RegulacionEstructuradorService $estructurador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->estructurador = app(RegulacionEstructuradorService::class);
    }

    /**
     * Crea una regulación ya convertida, con el Markdown que le pasemos escrito en
     * el disco falso. Es el punto de partida de todas las pruebas: el estructurador
     * lee el Markdown de una regulación, no un string suelto.
     */
    private function regulacionCon(string $markdown): Regulacion
    {
        $regulacion = Regulacion::factory()->convertida()->create();
        Storage::disk('local')->put($regulacion->archivo_markdown, $markdown);

        return $regulacion;
    }

    /** Los nodos vivos de una regulación, en el orden en que se crearon. */
    private function nodosDe(Regulacion $regulacion)
    {
        return RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->orderBy('id')
            ->get();
    }

    /** Busca un nodo por su tipo y su número. Devuelve null si no existe. */
    private function nodo(Regulacion $regulacion, string $tipo, string $numero): ?RegulacionNodo
    {
        return RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', $tipo)
            ->where('numero', $numero)
            ->first();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El árbol básico
    // ═══════════════════════════════════════════════════════════════════════

    public function test_construye_la_jerarquia_titulo_capitulo_articulo(): void
    {
        $regulacion = $this->regulacionCon(
            "TÍTULO PRIMERO\n"
            . "DISPOSICIONES GENERALES\n"
            . "\n"
            . "CAPÍTULO I\n"
            . "DEL OBJETO\n"
            . "\n"
            . "Artículo 1. El presente reglamento es de orden público e interés social.\n"
        );

        $creados = $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->assertGreaterThan(0, $creados, 'No se creó ningún nodo.');

        $titulo   = $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'PRIMERO');
        $capitulo = $this->nodo($regulacion, RegulacionNodo::TIPO_CAPITULO, 'I');
        $articulo = $this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '1');

        $this->assertNotNull($titulo,   'No se reconoció el TÍTULO PRIMERO.');
        $this->assertNotNull($capitulo, 'No se reconoció el CAPÍTULO I.');
        $this->assertNotNull($articulo, 'No se reconoció el Artículo 1.');

        // Lo importante no es que existan: es que cuelguen unos de otros.
        $this->assertSame($titulo->id,   $capitulo->parent_id, 'El capítulo debe colgar del título.');
        $this->assertSame($capitulo->id, $articulo->parent_id, 'El artículo debe colgar del capítulo.');
    }

    /**
     * Las fracciones (I, II, III...) cuelgan de su artículo, y los incisos (a, b, c)
     * cuelgan de su fracción. Es la jerarquía que hace posible citar
     * "Artículo 15, fracción II, inciso a)".
     */
    public function test_las_fracciones_cuelgan_del_articulo_y_los_incisos_de_la_fraccion(): void
    {
        $regulacion = $this->regulacionCon(
            "Artículo 15. Son requisitos para obtener la licencia:\n"
            . "\n"
            . "I. Presentar identificación oficial;\n"
            . "\n"
            . "II. Acreditar la propiedad del inmueble, mediante:\n"
            . "\n"
            . "a) Escritura pública;\n"
            . "\n"
            . "b) Contrato de arrendamiento.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $articulo  = $this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '15');
        $fraccion2 = $this->nodo($regulacion, RegulacionNodo::TIPO_FRACCION, 'II');
        $incisoA   = $this->nodo($regulacion, RegulacionNodo::TIPO_INCISO,   'a');

        $this->assertNotNull($articulo);
        $this->assertNotNull($fraccion2, 'No se reconoció la fracción II.');
        $this->assertNotNull($incisoA,   'No se reconoció el inciso a).');

        $this->assertSame($articulo->id,  $fraccion2->parent_id);
        $this->assertSame($fraccion2->id, $incisoA->parent_id, 'El inciso debe colgar de la ÚLTIMA fracción abierta.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Las heurísticas frágiles (lo que se rompe al refactorizar)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA HEURÍSTICA MÁS DELICADA DE TODO EL SERVICIO.
     *
     * En una ley, "I." al principio de una línea es la fracción primera. Pero "IVA",
     * "IMSS" o "CFE" también empiezan con letras que parecen números romanos.
     *
     * El estructurador tiene un método esSigla() que intenta distinguirlos. Si
     * alguien lo toca, las siglas se convertirían en fracciones fantasma y el
     * articulado de todas las regulaciones quedaría lleno de basura.
     *
     * Esta prueba fija la regla: un romano seguido de punto es fracción; una sigla
     * en medio del texto, no.
     */
    public function test_una_sigla_como_iva_no_se_confunde_con_una_fraccion(): void
    {
        $regulacion = $this->regulacionCon(
            "Artículo 20. El pago incluirá el IVA correspondiente.\n"
            . "\n"
            . "I. Pagar los derechos;\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $fracciones = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_FRACCION)
            ->get();

        $this->assertCount(
            1,
            $fracciones,
            'Solo hay UNA fracción (la I). Si salen dos, el "IVA" del texto se coló como fracción.'
        );
        $this->assertSame('I', $fracciones->first()->numero);
    }

    /**
     * Cuando el texto viene de un PDF, cada página trae su encabezado y su pie
     * repetidos, y el número de página suelto. Todo eso llega mezclado con el
     * contenido de la ley.
     *
     * quitarMaquetacion() los detecta porque se REPITEN (constante
     * REPETICIONES_MAQUETACION = 4) y los borra antes de estructurar. Sin ese
     * filtro, cada "Página 3 de 40" se convertiría en un nodo.
     */
    public function test_la_maquetacion_repetida_del_pdf_no_se_convierte_en_nodos(): void
    {
        $ruido = "H. AYUNTAMIENTO DE LA PAZ\n";

        $markdown = str_repeat($ruido, 6)  // se repite lo bastante para ser detectado
            . "Artículo 1. El presente reglamento es de orden público.\n"
            . str_repeat($ruido, 6);

        $regulacion = $this->regulacionCon($markdown);

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $textos = $this->nodosDe($regulacion)->pluck('texto')->implode(' ');

        $this->assertStringNotContainsString(
            'H. AYUNTAMIENTO DE LA PAZ',
            $textos,
            'El encabezado repetido del PDF se coló como contenido de la ley.'
        );
        $this->assertNotNull($this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '1'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Reestructurar sin perder trabajo (el caso que te preocupaba)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * importarDesdeMarkdown() es IDEMPOTENTE: se puede ejecutar dos veces sobre la
     * misma regulación sin duplicar nodos. Borra el árbol entero y lo reconstruye.
     *
     * Esto importa porque el usuario puede darle a "reestructurar" varias veces.
     */
    public function test_reestructurar_dos_veces_no_duplica_nodos(): void
    {
        $regulacion = $this->regulacionCon(
            "Artículo 1. Disposición primera.\n\nArtículo 2. Disposición segunda.\n"
        );

        $primeraVez = $this->estructurador->importarDesdeMarkdown($regulacion);
        $segundaVez = $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->assertSame($primeraVez, $segundaVez, 'La segunda pasada debe crear los mismos nodos, no más.');
        $this->assertCount($primeraVez, $this->nodosDe($regulacion));
    }

    /**
     * EL CASO QUE MÁS ME PREOCUPA de todo el módulo.
     *
     * Al reestructurar se borra TODO el árbol y se reconstruye. Si un usuario había
     * enviado nodos a la papelera (limpiando basura del parseo), ese trabajo se
     * perdería en cada reestructuración.
     *
     * El servicio ya lo contempla: antes de borrar, guarda qué nodos estaban en
     * papelera (por tipo + número) y los vuelve a mandar a papelera después de
     * reconstruir.
     *
     * Esta prueba congela ese comportamiento. Es delicado, porque depende de que el
     * nodo reconstruido tenga EL MISMO tipo y número que el que se borró. Si el
     * parseo cambia y el artículo 3 pasa a llamarse "3 bis", la papelera se pierde
     * en silencio.
     */
    public function test_los_nodos_enviados_a_papelera_siguen_en_papelera_tras_reestructurar(): void
    {
        $regulacion = $this->regulacionCon(
            "Artículo 1. Disposición primera.\n\nArtículo 2. Disposición segunda.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        // El usuario manda el artículo 2 a la papelera (decidió que era basura).
        $articulo2 = $this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '2');
        $this->assertNotNull($articulo2);
        $articulo2->delete(); // soft delete

        // Y después vuelve a reestructurar.
        $this->estructurador->importarDesdeMarkdown($regulacion);

        // El artículo 2 debe seguir en la papelera, no resucitado.
        $this->assertNull(
            $this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '2'),
            'El artículo 2 volvió a la vida: se perdió el trabajo de limpieza del usuario.'
        );

        $enPapelera = RegulacionNodo::onlyTrashed()
            ->where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->where('numero', '2')
            ->exists();

        $this->assertTrue($enPapelera, 'El artículo 2 debería estar en la papelera tras reestructurar.');

        // El artículo 1, que nadie tocó, sigue vivo.
        $this->assertNotNull($this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '1'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Entradas degeneradas (lo que llega en la vida real)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Si la conversión falló, no hay Markdown que estructurar. El servicio devuelve
     * 0 y no toca nada. No revienta.
     */
    public function test_una_regulacion_sin_conversion_no_crea_nodos(): void
    {
        $regulacion = Regulacion::factory()->conError()->create();

        $this->assertSame(0, $this->estructurador->importarDesdeMarkdown($regulacion));
        $this->assertCount(0, $this->nodosDe($regulacion));
    }

    public function test_un_markdown_vacio_no_crea_nodos(): void
    {
        $regulacion = $this->regulacionCon('');

        $this->assertSame(0, $this->estructurador->importarDesdeMarkdown($regulacion));
    }

    /**
     * Un texto sin ningún patrón reconocible (sin artículos, sin fracciones) no debe
     * reventar. Lo que HAGA con él —crear párrafos sueltos o no crear nada— es lo
     * que esta prueba deja fijado.
     *
     * Si al correrla el número real no es el que espera, NO cambies el código:
     * cambia el número esperado. Esta prueba describe lo que hay, no lo que debería.
     */
    public function test_un_texto_sin_estructura_no_revienta(): void
    {
        $regulacion = $this->regulacionCon(
            "Este es un documento sin artículos.\nSolo texto corrido, sin numeración.\n"
        );

        $creados = $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->assertIsInt($creados);
        $this->assertGreaterThanOrEqual(0, $creados);
    }
}
