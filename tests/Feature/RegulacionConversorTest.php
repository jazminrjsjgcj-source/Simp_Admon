<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Services\RegulacionConversorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas del conversor de regulaciones (RegulacionConversorService).
 *
 * ── Qué clase de pruebas son estas ───────────────────────────────────
 *
 * NO buscan errores. Buscan CONGELAR el comportamiento actual.
 *
 * El conversor tiene 924 líneas y hace de todo: lee PDF con pdftotext, Word con
 * PHPWord, cae a LibreOffice si hace falta, puntúa la legibilidad del resultado y
 * elige el mejor. Es la pieza más frágil del sistema, porque depende de programas
 * externos que ni siquiera están garantizados en el servidor.
 *
 * Refactorizarlo sin una red de seguridad es apostar. Estas pruebas son la red:
 * describen lo que hace HOY, para que mañana se pueda mover el código de sitio y
 * saber al instante si algo cambió de comportamiento.
 *
 * Consecuencia importante: si el conversor hoy tiene una rareza, la prueba la fija
 * TAL CUAL, rareza incluida. Corregirla es una decisión aparte, y se toma después.
 *
 * ── Qué se prueba y qué no ───────────────────────────────────────────
 *
 * SÍ: las funciones que no tocan el disco ni programas externos.
 *     - scoreLegibilidad(): decide si un texto extraído sirve o es basura.
 *     - extraerIndice():    saca el índice de títulos del Markdown.
 *     - obtenerContenidoMarkdown(): con disco falso (Storage::fake).
 *
 * NO: la extracción real de PDF y Word. Depende de pdftotext, PHPWord y
 *     LibreOffice, que son programas del sistema operativo. Probar eso no sería
 *     una prueba unitaria: sería probar LibreOffice. Se prueba a mano, con
 *     archivos de verdad, y se deja documentado.
 */
class RegulacionConversorTest extends TestCase
{
    use RefreshDatabase;

    private RegulacionConversorService $conversor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversor = app(RegulacionConversorService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. scoreLegibilidad(): ¿el texto extraído sirve o es basura?
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Qué hace esta función, explicado desde cero:
     *
     * Cuando el sistema extrae texto de un PDF, a veces sale bien y a veces sale
     * un churro de símbolos (pasa con los PDF escaneados, o con los que tienen las
     * fuentes incrustadas de forma rara). El sistema necesita saber, sin que un
     * humano lo mire, si lo que extrajo es texto de verdad.
     *
     * scoreLegibilidad() cuenta qué porcentaje de los caracteres son "esperables en
     * español" (letras con acentos, dígitos, espacios, puntuación) y devuelve un
     * número entre 0 y 1. Un texto español normal da casi 1.0. Un churro da 0.2.
     *
     * De ese número dependen dos decisiones del sistema:
     *   - Por debajo de 0.25 (SCORE_GUARDADO_MINIMO) la conversión falla.
     *   - Por debajo de 0.30 (SCORE_ESTRUCTURACION_MINIMO) no se intenta estructurar.
     *
     * Si alguien toca la expresión regular de esta función, esos dos umbrales dejan
     * de significar lo mismo, y regulaciones que antes se aceptaban se empezarían a
     * rechazar en silencio. Por eso está probada.
     */
    public function test_un_texto_en_espanol_normal_se_considera_legible(): void
    {
        $texto = 'Artículo 15. La autoridad municipal resolverá la solicitud en un plazo '
            . 'máximo de diez días hábiles, contados a partir de su recepción.';

        $score = $this->conversor->scoreLegibilidad($texto);

        $this->assertGreaterThan(
            RegulacionConversorService::SCORE_ESTRUCTURACION_MINIMO,
            $score,
            'Un texto español con acentos, puntuación y números debe pasar el umbral.'
        );
    }

    public function test_un_texto_garbleado_se_considera_ilegible(): void
    {
        // Esto es lo que sale de un PDF con las fuentes mal incrustadas: el extractor
        // devuelve símbolos en vez de letras.
        //
        // OJO CON LAS COMILLAS: tienen que ser DOBLES. En PHP, las comillas simples
        // NO interpretan los escapes \u{...} — dejan la cadena tal cual, con la barra,
        // la letra u y las llaves. Escrito con comillas simples, este "churro" serían
        // letras y dígitos ASCII perfectamente legibles, y scoreLegibilidad() lo
        // puntuaría alto (0.625), haciendo fallar la prueba por un motivo falso.
        $churro = "\u{FFFD}\u{2591}\u{2592}\u{2593}\u{25A0}\u{25A1}\u{2022}\u{2020}\u{2021}\u{2030}"
            . "\u{FFFD}\u{2591}\u{2592}\u{2593}\u{25A0}\u{25A1}\u{2022}\u{2020}\u{2021}\u{2030}"
            . "\u{FFFD}\u{2591}\u{2592}\u{2593}\u{25A0}\u{25A1}";

        $score = $this->conversor->scoreLegibilidad($churro);

        $this->assertLessThan(
            RegulacionConversorService::SCORE_GUARDADO_MINIMO,
            $score,
            'Un texto de símbolos no debe guardarse como si fuera una regulación.'
        );
    }

    public function test_un_texto_demasiado_corto_no_se_puntua(): void
    {
        // Menos de 10 caracteres devuelve 0.0 sin calcular nada. Es una salvaguarda:
        // con tan poco texto, el porcentaje no significa nada (una sola letra daría 1.0).
        $this->assertSame(0.0, $this->conversor->scoreLegibilidad('Hola'));
        $this->assertSame(0.0, $this->conversor->scoreLegibilidad(''));
    }

    /**
     * Congela la relación entre los dos umbrales.
     *
     * El umbral para GUARDAR (0.25) es más bajo que el umbral para ESTRUCTURAR
     * (0.30), y eso es deliberado: un texto puede ser lo bastante legible para
     * archivarlo, pero no lo bastante limpio para que el parser encuentre
     * artículos y fracciones dentro.
     *
     * Esta prueba no comprueba los valores exactos (pueden ajustarse). Comprueba la
     * RELACIÓN, que es la regla de negocio. Si alguien los invierte sin querer, el
     * sistema intentaría estructurar textos que ni siquiera pudo guardar.
     */
    public function test_estructurar_exige_mas_calidad_que_guardar(): void
    {
        $this->assertLessThan(
            RegulacionConversorService::SCORE_ESTRUCTURACION_MINIMO,
            RegulacionConversorService::SCORE_GUARDADO_MINIMO,
            'Guardar debe ser más permisivo que estructurar, no al revés.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. extraerIndice(): el índice de navegación de la regulación
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * extraerIndice() recorre el Markdown y devuelve la lista de títulos con su
     * nivel de jerarquía y el número de línea donde están. Es lo que alimenta el
     * índice lateral cuando el usuario abre una regulación.
     *
     * Reconoce DOS formas de encabezado, y eso es lo que hay que congelar:
     *
     *   1. Encabezados Markdown de verdad: "## Capítulo I" → el nivel sale del
     *      número de almohadillas.
     *   2. Texto plano sin almohadillas: "TÍTULO PRIMERO", "Artículo 15". Esto
     *      pasa cuando el texto viene de un PDF, donde no hay Markdown ninguno.
     *      Aquí el nivel se DEDUCE de la palabra: título=1, capítulo=2, artículo=3.
     */
    public function test_el_indice_lee_los_encabezados_markdown_y_su_nivel(): void
    {
        $markdown = "# Reglamento de Comercio\n"
            . "\n"
            . "## Capítulo I\n"
            . "\n"
            . "### Artículo 1\n";

        $indice = $this->conversor->extraerIndice($markdown);

        $this->assertCount(3, $indice);

        $this->assertSame(1, $indice[0]['nivel']);
        $this->assertSame('Reglamento de Comercio', $indice[0]['titulo']);
        $this->assertSame(1, $indice[0]['linea'], 'La línea se cuenta desde 1, no desde 0.');

        $this->assertSame(2, $indice[1]['nivel']);
        $this->assertSame(3, $indice[2]['nivel']);
    }

    public function test_el_indice_reconoce_encabezados_en_texto_plano_sin_almohadillas(): void
    {
        // Así llega el texto de un PDF: sin ninguna marca de Markdown.
        $textoDePdf = "TÍTULO PRIMERO\n"
            . "DISPOSICIONES GENERALES\n"
            . "CAPÍTULO I\n"
            . "Artículo 1. El presente reglamento es de orden público.\n";

        $indice = $this->conversor->extraerIndice($textoDePdf);

        // "DISPOSICIONES GENERALES" no entra: no empieza por ninguna de las palabras
        // que el conversor busca. Es el comportamiento actual y así se congela.
        $this->assertCount(3, $indice);

        $this->assertSame(1, $indice[0]['nivel'], 'TÍTULO es nivel 1.');
        $this->assertSame(2, $indice[1]['nivel'], 'CAPÍTULO es nivel 2.');
        $this->assertSame(3, $indice[2]['nivel'], 'Artículo es nivel 3.');
    }

    /**
     * El conversor añade una cabecera automática al Markdown que genera
     * ("Regulación generada automáticamente", "**Tipo:**", "**Fecha..."). Esa
     * cabecera NO debe aparecer en el índice de navegación: es metadato del
     * sistema, no contenido de la ley.
     */
    public function test_el_indice_descarta_la_cabecera_que_genera_el_propio_sistema(): void
    {
        $markdown = "# Regulación generada automáticamente\n"
            . "## **Tipo:** Reglamento\n"
            . "## Capítulo I\n";

        $indice = $this->conversor->extraerIndice($markdown);

        $this->assertCount(1, $indice);
        $this->assertSame('Capítulo I', $indice[0]['titulo']);
    }

    public function test_un_markdown_sin_encabezados_devuelve_un_indice_vacio(): void
    {
        $indice = $this->conversor->extraerIndice("Solo texto corrido.\nSin ningún título.\n");

        $this->assertSame([], $indice);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. obtenerContenidoMarkdown(): la puerta a todo lo demás
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Este método es el guardián: el estructurador, el buscador y el citador
     * pasan todos por aquí para leer el texto de una regulación.
     *
     * La regla que impone: SOLO devuelve contenido si la conversión terminó bien
     * (conversion_estatus = 'listo' Y hay ruta de Markdown). En cualquier otro
     * caso devuelve null.
     *
     * Es lo que impide que el sistema intente estructurar una regulación cuyo PDF
     * nunca se pudo leer.
     */
    public function test_devuelve_el_markdown_cuando_la_conversion_termino_bien(): void
    {
        Storage::fake('local');

        $regulacion = Regulacion::factory()->convertida()->create();
        Storage::disk('local')->put($regulacion->archivo_markdown, '# Reglamento');

        $this->assertSame('# Reglamento', $this->conversor->obtenerContenidoMarkdown($regulacion));
    }

    public function test_no_devuelve_nada_si_la_conversion_fallo(): void
    {
        Storage::fake('local');

        $regulacion = Regulacion::factory()->conError()->create();

        $this->assertNull($this->conversor->obtenerContenidoMarkdown($regulacion));
    }

    public function test_no_devuelve_nada_si_la_conversion_sigue_pendiente(): void
    {
        Storage::fake('local');

        $regulacion = Regulacion::factory()->create(); // pendiente por defecto

        $this->assertNull($this->conversor->obtenerContenidoMarkdown($regulacion));
    }

    /**
     * Caso de SOPORTE: la base dice que la conversión terminó bien, pero el archivo
     * no está en el disco. Pasa de verdad — un despliegue que no copió el volumen,
     * un borrado manual, un disco que se llenó.
     *
     * El sistema debe devolver null, no reventar. Si reventara, la regulación sería
     * imposible de abrir y el usuario vería un 500 sin explicación.
     */
    public function test_no_revienta_si_la_base_dice_listo_pero_el_archivo_no_existe(): void
    {
        Storage::fake('local');

        // Regulación marcada como convertida, pero NO se escribe el archivo.
        $regulacion = Regulacion::factory()->convertida()->create();

        $this->assertNull(
            $this->conversor->obtenerContenidoMarkdown($regulacion),
            'Un archivo desaparecido debe devolver null, no lanzar una excepción.'
        );
    }
}
