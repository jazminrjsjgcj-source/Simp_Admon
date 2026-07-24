<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionEstructuradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * LOS TÍTULOS DE LA LEY: dos fallos que rompían el árbol sin dar ningún error.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LOS TEXTOS DE ESTA PRUEBA SON REALES
 * ══════════════════════════════════════════════════════════════════════
 *
 * Están copiados, línea por línea, del markdown de la Ley de Hacienda del Municipio de La Paz
 * (storage/app/private/regulaciones/markdown/1-ley-de-hacienda.md), incluidos los saltos de línea
 * que dejó la conversión del PDF. No son ejemplos inventados: son EXACTAMENTE lo que rompió.
 *
 * ══════════════════════════════════════════════════════════════════════
 * FALLO 1 — "TÍTULO DÉCIMO PRIMERO" no existía para el sistema
 * ══════════════════════════════════════════════════════════════════════
 *
 * La ley no dice "undécimo". Dice DÉCIMO PRIMERO, en dos palabras. Y la lista de ordinales del
 * detector empezaba por "décimo", que es voraz por la izquierda:
 *
 *     TÍTULO DÉCIMO PRIMERO
 *            └─┬──┘ └──┬──┘
 *           numero   NOMBRE       ← "PRIMERO" se guardaba como el nombre del título
 *
 * Quedaban TRES títulos numerados DÉCIMO. Los artículos del Décimo Primero y del Décimo Segundo
 * colgaban del contenedor equivocado, y eso contamina el contexto heredado — el que el buscador
 * usa para saber de qué habla cada artículo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * FALLO 2 — Media frase de un transitorio se convertía en título
 * ══════════════════════════════════════════════════════════════════════
 *
 * El PDF trae saltos de línea duros. Así que hay renglones que EMPIEZAN, por casualidad, con lo
 * que parece un encabezado:
 *
 *     ...un Capítulo I-BIS al
 *     Título Quinto denominado ¨Venta o explotación de bienes muebles e inmuebles del
 *     Patrimonio Municipal¨ compuesto por...
 *
 * Eso no es un título: es una cita dentro de un artículo transitorio. Y el detector la promovía a
 * TÍTULO, con lo que ese título fantasma se tragaba como hijos todos los artículos siguientes.
 *
 * ── Y ninguno de los dos daba error ──
 *
 * El árbol se construía. La importación decía "listo". Nadie se enteraba de nada hasta que alguien
 * miraba el árbol con los ojos y veía un "Título QUINTO denominado ¨Venta o explotación..." donde
 * debía haber doce títulos limpios.
 */
class TitulosDeLaLeyTest extends TestCase
{
    use RefreshDatabase;

    private RegulacionEstructuradorService $estructurador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->estructurador = app(RegulacionEstructuradorService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Los ordinales compuestos
    // ═══════════════════════════════════════════════════════════════════════

    public function test_reconoce_los_titulos_con_ordinal_compuesto(): void
    {
        $regulacion = $this->regulacionCon(
            "TÍTULO DÉCIMO\n"
            . "DE LAS INFRACCIONES\n"
            . "\n"
            . "Artículo 200. Las infracciones se sancionarán conforme a esta Ley.\n"
            . "\n"
            . "TÍTULO DÉCIMO PRIMERO\n"
            . "DE LOS RECURSOS\n"
            . "\n"
            . "Artículo 210. Contra los actos de la autoridad procede el recurso de revocación.\n"
            . "\n"
            . "TÍTULO DÉCIMO SEGUNDO\n"
            . "DE LA PRESCRIPCIÓN\n"
            . "\n"
            . "Artículo 220. El crédito fiscal prescribe en cinco años.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $decimo         = $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'DÉCIMO');
        $decimoPrimero  = $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'DÉCIMO PRIMERO');
        $decimoSegundo  = $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'DÉCIMO SEGUNDO');

        $this->assertNotNull($decimo, 'No se reconoció el TÍTULO DÉCIMO.');

        $this->assertNotNull(
            $decimoPrimero,
            'No se reconoció el TÍTULO DÉCIMO PRIMERO. La lista de ordinales prueba las '
            . 'alternativas por orden y se queda con la primera que casa: si "décimo" va antes que '
            . '"décimo primero", nunca se llega a la segunda, y "PRIMERO" acaba guardado como si '
            . 'fuera el NOMBRE del título.'
        );

        $this->assertNotNull($decimoSegundo, 'No se reconoció el TÍTULO DÉCIMO SEGUNDO.');
    }

    /**
     * El daño de verdad no era el nombre raro: era que los artículos colgaban del título
     * EQUIVOCADO. Y de ahí sale el contexto que lee el buscador.
     */
    public function test_los_articulos_cuelgan_del_titulo_compuesto_correcto(): void
    {
        $regulacion = $this->regulacionCon(
            "TÍTULO DÉCIMO\n"
            . "DE LAS INFRACCIONES\n"
            . "\n"
            . "Artículo 200. Las infracciones se sancionarán conforme a esta Ley.\n"
            . "\n"
            . "TÍTULO DÉCIMO PRIMERO\n"
            . "DE LOS RECURSOS\n"
            . "\n"
            . "Artículo 210. Contra los actos de la autoridad procede el recurso de revocación.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $decimoPrimero = $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'DÉCIMO PRIMERO');
        $articulo210   = $this->nodo($regulacion, RegulacionNodo::TIPO_ARTICULO, '210');

        $this->assertNotNull($decimoPrimero);
        $this->assertNotNull($articulo210);

        $this->assertSame(
            $decimoPrimero->id,
            $articulo210->parent_id,
            'El artículo 210 tiene que colgar del TÍTULO DÉCIMO PRIMERO, no del DÉCIMO. Si cuelga '
            . 'del contenedor equivocado, el CONTEXTO HEREDADO miente: el buscador creerá que el '
            . 'recurso de revocación está en el título de las infracciones.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Las citas dentro de la prosa NO son encabezados
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El texto es literal del artículo transitorio de la Ley de Hacienda, con los saltos de línea
     * que dejó el PDF. La segunda línea empieza con "Título Quinto denominado…" — y no es un
     * título: es media frase.
     */
    public function test_una_cita_partida_por_el_pdf_no_se_convierte_en_titulo(): void
    {
        $regulacion = $this->regulacionCon(
            "TÍTULO PRIMERO\n"
            . "DISPOSICIONES GENERALES\n"
            . "\n"
            . "Artículo 1. La presente Ley regula las contribuciones municipales.\n"
            . "\n"
            . "ARTÍCULO ÚNICO. Se REFORMAN el artículo 8; las fracciones III, IV, V y VI al "
            . "artículo 151; se ADICIONAN un Capítulo I-BIS al\n"
            . "Título Quinto denominado ¨Venta o explotación de bienes muebles e inmuebles del\n"
            . "Patrimonio Municipal¨ compuesto por una Sección I denominada ¨Venta de bienes¨; y "
            . "en el pago del servicio por conexión a las redes de agua potable a los que se "
            . "refiere el\n"
            . "Titulo Séptimo de la presente Ley y demás disposiciones legales aplicables; y\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $titulos = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_TITULO)
            ->pluck('numero')
            ->all();

        $this->assertSame(
            ['PRIMERO'],
            $titulos,
            "El único título de este texto es el PRIMERO. Todo lo demás son CITAS dentro de un "
            . "artículo transitorio, partidas a mitad por los saltos de línea del PDF.\n\n"
            . "Se detectaron: " . implode(', ', $titulos) . "\n\n"
            . 'Un título fantasma no da ningún error: se traga como hijos todos los artículos que '
            . 'vengan detrás, y contamina el contexto que lee el buscador.'
        );
    }

    /**
     * La regla que separa un encabezado de una cita: después del identificador, MINÚSCULA es
     * prosa. Y no vale solo para los títulos — la misma frase inventa secciones y capítulos.
     */
    public function test_una_cita_tampoco_inventa_secciones_ni_capitulos(): void
    {
        $regulacion = $this->regulacionCon(
            "CAPÍTULO I\n"
            . "DEL OBJETO\n"
            . "\n"
            . "Artículo 1. Esta Ley es de orden público.\n"
            . "\n"
            . "ARTÍCULO ÚNICO. Se ADICIONAN una\n"
            . "Sección II al Capítulo II del Título Cuarto denominada ¨De la renta y concesión de "
            . "locales por\n"
            . "ocupación en los mercados municipales¨.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $secciones = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_SECCION)
            ->count();

        $this->assertSame(
            0,
            $secciones,
            'La línea "Sección II al Capítulo II del Título Cuarto denominada…" es una CITA dentro '
            . 'de un transitorio, no una sección. Lo delata la minúscula que sigue al '
            . 'identificador ("al").'
        );
    }

    /**
     * LA CONTRAPRUEBA. Sin ella, la guardia contra las citas podría estar rechazándolo TODO y las
     * dos pruebas de arriba pasarían igual.
     *
     * Un encabezado con su nombre EN LA MISMA LÍNEA y en mayúscula tiene que seguir reconociéndose.
     */
    public function test_un_encabezado_con_su_nombre_en_la_misma_linea_sigue_valiendo(): void
    {
        $regulacion = $this->regulacionCon(
            "TÍTULO I — DISPOSICIONES GENERALES\n"
            . "\n"
            . "CAPÍTULO II DEL OBJETO\n"
            . "\n"
            . "Artículo 1. Esta Ley es de orden público.\n"
        );

        $this->estructurador->importarDesdeMarkdown($regulacion);

        $this->assertNotNull(
            $this->nodo($regulacion, RegulacionNodo::TIPO_TITULO, 'I'),
            'La guardia contra las citas NO puede llevarse por delante un encabezado legítimo con '
            . 'su nombre en la misma línea. Si esta prueba falla, se está rechazando de más.'
        );

        $this->assertNotNull(
            $this->nodo($regulacion, RegulacionNodo::TIPO_CAPITULO, 'II'),
            'Lo mismo para el capítulo: "CAPÍTULO II DEL OBJETO" es un encabezado de toda la vida.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    private function regulacionCon(string $markdown): Regulacion
    {
        $regulacion = Regulacion::factory()->convertida()->create();
        Storage::disk('local')->put($regulacion->archivo_markdown, $markdown);

        return $regulacion;
    }

    private function nodo(Regulacion $regulacion, string $tipo, string $numero): ?RegulacionNodo
    {
        return RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', $tipo)
            ->where('numero', $numero)
            ->first();
    }
}
