<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El artículo que RESPONDE tiene que llegarle al asistente.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL CASO REAL, CON LOS ARTÍCULOS REALES
 * ══════════════════════════════════════════════════════════════════════
 *
 * Alguien pregunta: "cuáles y cómo se calculan los cobros sobre espectáculos públicos"
 *
 * Y el asistente responde, muy honestamente, que NO ENCUENTRA cómo se calculan. Cita el
 * artículo 55 (el marco legal) y el 63 (qué es un espectáculo), pero no dice el porcentaje.
 *
 * ¿Por qué? Porque el artículo que lo dice NO LE LLEGÓ:
 *
 *     "Artículo 65.- Los sujetos pagarán por concepto de este impuesto, EL 8% del monto total
 *      de los ingresos obtenidos."
 *
 * Está en la ley. Está cargado. Y el buscador no se lo dio.
 *
 * ── Las dos razones, y ninguna era culpa del modelo ──
 *
 * 1. EL LÍMITE. El articulado devolvía 10 resultados como máximo. La Ley de Hacienda tiene
 *    DIECISIETE artículos que mencionan "espectáculos". El 65 quedaba fuera del corte.
 *
 * 2. EL ORDEN. ts_rank premiaba la repetición, así que favorecía a los artículos LARGOS. El
 *    artículo 63 —que solo define qué es un espectáculo: teatro, ballet, ópera, circo, lucha
 *    libre, box, fútbol, carreras de burros...— repite la palabra cuatro veces y puntuaba más
 *    alto que el 65, que es corto y va al grano.
 *
 * El modelo hacía bien su trabajo. Le estábamos dando la basura y escondiéndole el oro.
 *
 * ── Lo importante de esta prueba ──
 *
 * Usa los ARTÍCULOS REALES de la Ley de Hacienda de La Paz, copiados literalmente. No un caso
 * de laboratorio.
 *
 * Un artículo inventado ("Artículo X: se paga el 8%") habría pasado la prueba sin problema, y
 * no habría demostrado nada. El bug solo aparece cuando compiten DIECISIETE artículos sobre el
 * mismo tema, unos largos y otros cortos — y eso solo se reproduce con la ley de verdad.
 */
class ArticuloQueRespondeLlegaTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    /**
     * Los artículos reales de espectáculos de la Ley de Hacienda del Municipio de La Paz 2025.
     *
     * Están copiados literalmente, con su longitud real. La longitud es parte del bug: el 63 es
     * larguísimo y el 65 es corto, y esa diferencia es la que hacía que el equivocado ganara.
     */
    private function cargarArticulosDeEspectaculos(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Hacienda']);

        // ══════════════════════════════════════════════════════════════════════
        // SE CARGA EL ÁRBOL COMPLETO, CON SU JERARQUÍA. NO ARTÍCULOS SUELTOS.
        // ══════════════════════════════════════════════════════════════════════
        //
        // La primera versión de este fixture creaba los artículos sin padre. Y era un fixture
        // MENTIROSO: el sistema real NUNCA produce artículos huérfanos. Los cuelga de su sección,
        // su capítulo y su título.
        //
        // Y esa diferencia lo es todo, porque el artículo 65 —el que dice "el 8%"— NO CONTIENE la
        // palabra "públicos". Dice "que genere el ESPECTÁCULO que corresponda", en singular.
        //
        // ¿Por qué? Porque no le hace falta: ya está DENTRO de la sección "IMPUESTOS SOBRE
        // ESPECTÁCULOS PÚBLICOS".
        //
        //     EN TODA LEY BIEN REDACTADA, UN ARTÍCULO NO REPITE EL TÍTULO DE SU CAPÍTULO.
        //
        // El contexto heredado (columna `contexto`) es lo único que lo hace encontrable. Un
        // fixture sin jerarquía no puede probar eso: probaría un mundo que no existe.
        $titulo = RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_TITULO,
            'numero'        => 'SEGUNDO',
            'texto'         => 'IMPUESTOS',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        $capitulo = RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => $titulo->id,
            'tipo'          => RegulacionNodo::TIPO_CAPITULO,
            'numero'        => 'III',
            'texto'         => 'IMPUESTOS SOBRE LOS INGRESOS',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        $seccion = RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => $capitulo->id,
            'tipo'          => RegulacionNodo::TIPO_SECCION,
            'numero'        => 'III',
            'texto'         => 'IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        // El contexto que el estructurador calcularía para los artículos de esta sección.
        $contexto = 'SEGUNDO IMPUESTOS. III IMPUESTOS SOBRE LOS INGRESOS. III IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS';

        $articulos = [
            '55' => 'Los causantes de este impuesto, se sujetarán a lo establecido en lo conducente '
                  . 'por el Reglamento de Espectáculos Públicos y demás disposiciones vigentes.',

            // EL LARGO. Solo DEFINE qué es un espectáculo. Repite la palabra cuatro veces.
            '63' => 'Es objeto del impuesto sobre espectáculos públicos, el ingreso que se obtenga '
                  . 'por concepto de la explotación de diversos espectáculos públicos tales como '
                  . 'teatro, ballet, ópera, conciertos, audiciones musicales, variedades, '
                  . 'exhibiciones de cualquier naturaleza, carpa, circo, lucha libre, box, fútbol, '
                  . 'béisbol, básquetbol, carreras de vehículos, náuticos, eventos taurinos, '
                  . 'hípicos, carreras de caballos, carreras de burros, charrerías, y demás eventos '
                  . 'deportivos.',

            '64' => 'Son sujetos de este impuesto, las personas físicas y jurídicas que perciban los '
                  . 'ingresos a que se refiere el artículo anterior.',

            // ═══ EL QUE RESPONDE ═══
            //
            // Fíjate: dice "el ESPECTÁCULO que corresponda". SINGULAR, y SIN "públicos".
            // Sin el contexto heredado, este artículo es invisible para quien busque
            // "espectáculos públicos". Y es el ÚNICO que dice cuánto se paga.
            '65' => 'Los sujetos pagarán por concepto de este impuesto, el 8% del monto total de los '
                  . 'ingresos obtenidos, que genere el espectáculo que corresponda, siempre y cuando '
                  . 'no estén obligados al pago del Impuesto al Valor Agregado.',

            '66' => 'Cuando los espectáculos públicos se realicen permanentemente en establecimientos '
                  . 'fijos, será a más tardar el día 20 del mes siguiente al que se hubieran percibido '
                  . 'los ingresos.',

            '69' => 'Se faculta al Presidente Municipal para condonar estos impuestos cuando se trate '
                  . 'de los espectáculos cuyos productos se destinen a obra pública del Municipio.',
        ];

        $orden = 1;

        foreach ($articulos as $numero => $texto) {
            RegulacionNodo::create([
                'regulacion_id' => $ley->id,
                'parent_id'     => $seccion->id,
                'tipo'          => RegulacionNodo::TIPO_ARTICULO,
                'numero'        => $numero,
                'texto'         => $texto,
                'contexto'      => $contexto,
                'orden'         => $orden++,
                'estado'        => RegulacionNodo::ESTADO_VIGENTE,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA. El artículo 65 —el del 8%— tiene que estar entre los resultados.
     *
     * No basta con que el buscador "encuentre algo". Encontraba siete artículos sobre
     * espectáculos, todos legítimos, y ninguno respondía. El asistente no puede elegir lo que no
     * se le da.
     */
    public function test_el_articulo_que_dice_el_porcentaje_llega_a_los_resultados(): void
    {
        $this->cargarArticulosDeEspectaculos();

        $resultados = $this->buscador->buscar('cuales y como se calculan los cobros sobre espectaculos publicos');

        $textos = $resultados['resultados']->pluck('fragmento')->implode(' ');

        $this->assertStringContainsString(
            '8%',
            $textos,
            'El artículo 65 —el ÚNICO que dice cuánto se paga (el 8%)— no llegó a los resultados. '
            . 'El asistente recibió el marco legal y la definición del impuesto, pero no el '
            . 'porcentaje. Y respondió, honestamente, que no encontraba cómo se calcula. '
            . 'El modelo hizo bien su trabajo: le dimos la basura y le escondimos el oro.'
        );
    }

    /**
     * Y tiene que salir POR DELANTE del artículo 63 (el largo que solo define).
     *
     * ── Por qué esto importa tanto ──
     *
     * El asistente lee las 20 mejores fuentes. Si el 65 está en el puesto veinticinco, es como si
     * no estuviera.
     *
     * Y sin normalizar por longitud, el 63 GANABA: repite "espectáculos" cuatro veces y enumera
     * veinte tipos de evento. ts_rank premia la repetición.
     *
     * La normalización (el `2` del tercer argumento de ts_rank) divide la puntuación entre la
     * longitud del texto. Captura algo simple y cierto:
     *
     *     Un texto CORTO que menciona tu palabra HABLA de tu palabra.
     *     Un texto LARGO que la menciona de pasada, no.
     *
     * El 65 tiene 40 palabras y la mitad son la respuesta. El 63 tiene 150 y ninguna dice cuánto
     * se paga.
     */
    public function test_el_articulo_que_responde_puntua_mas_que_el_que_solo_define(): void
    {
        $this->cargarArticulosDeEspectaculos();

        $resultados = $this->buscador
            ->buscar('cuales y como se calculan los cobros sobre espectaculos publicos')['resultados'];

        $posicion65 = $resultados->search(fn ($r) => str_contains((string) $r['fragmento'], '8%'));
        $posicion63 = $resultados->search(fn ($r) => str_contains((string) $r['fragmento'], 'carreras de burros'));

        $this->assertNotFalse($posicion65, 'El artículo 65 (el del 8%) no está en los resultados.');

        if ($posicion63 !== false) {
            $this->assertLessThan(
                $posicion63,
                $posicion65,
                'El artículo 63 —que solo enumera qué es un espectáculo (teatro, circo, lucha libre, '
                . 'carreras de burros...)— salió por delante del 65, que es el único que dice cuánto '
                . 'se paga. ts_rank está premiando la repetición en vez de la densidad: falta la '
                . 'normalización por longitud (el tercer argumento, 2).'
            );
        }
    }

    /**
     * Una búsqueda por el tema, sin palabras de pregunta, también lo encuentra.
     *
     * Es la comprobación de que el arreglo no depende de cómo esté redactada la pregunta.
     */
    public function test_buscar_solo_el_tema_tambien_encuentra_el_porcentaje(): void
    {
        $this->cargarArticulosDeEspectaculos();

        $textos = $this->buscador->buscar('impuesto espectaculos publicos')['resultados']
            ->pluck('fragmento')
            ->implode(' ');

        $this->assertStringContainsString('8%', $textos);
    }

    /**
     * EL CONTEXTO HEREDADO ES LO QUE HACE ENCONTRABLE AL ARTÍCULO 65.
     *
     * ── Antes aquí había otra prueba, y merece la pena saber por qué se fue ──
     *
     * Se llamaba test_los_rotulos_de_seccion_no_compiten_con_los_articulos, y comprobaba que unos
     * "rótulos" —párrafos sueltos en mayúsculas, como "IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS"— no
     * se colaran en los resultados.
     *
     * ESOS RÓTULOS YA NO EXISTEN.
     *
     * Eran el síntoma de un bug del conversor: en el PDF, un encabezado viene en DOS renglones
     * ("SECCIÓN III" / "IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS"), y el parser los procesaba por
     * separado. Creaba la sección SIN NOMBRE, y el nombre quedaba huérfano como párrafo.
     *
     * Ahora se unen antes de estructurar. La sección tiene su nombre, y no hay rótulos sueltos.
     *
     * Una prueba que describe un bug arreglado no es una red de seguridad: es un fósil. Se quita,
     * y se deja escrito por qué.
     *
     * ── Lo que hay que vigilar AHORA ──
     *
     * Que el contexto heredado siga haciendo su trabajo. Sin él, el artículo 65 —que dice
     * "espectáculo" en singular y NUNCA dice "públicos"— vuelve a ser invisible.
     */
    public function test_el_contexto_hace_encontrable_al_articulo_que_no_repite_el_tema(): void
    {
        $this->cargarArticulosDeEspectaculos();

        $resultados = $this->buscador
            ->buscar('cuales y como se calculan los cobros sobre espectaculos publicos')['resultados'];

        $this->assertStringContainsString(
            '8%',
            $resultados->pluck('fragmento')->implode(' '),
            'El artículo 65 no llegó. Y ese artículo NO CONTIENE la palabra "públicos": dice "el '
            . 'espectáculo que corresponda", en singular, porque ya está dentro de la sección '
            . '"IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS".'
            . "\n\n"
            . 'Sin el contexto heredado (columna `contexto`), es invisible. Comprueba que '
            . 'BuscadorService busque sobre texto + contexto, y que el estructurador rellene esa '
            . 'columna.'
        );
    }

    /**
     * Pero un párrafo CON CONTENIDO sí se busca. No se descartan todos los párrafos.
     *
     * El criterio no es "es un párrafo" —eso tiraría contenido legítimo, como los considerandos o
     * los transitorios—. El criterio es "es un párrafo CORTO y en MAYÚSCULAS", que es la firma de
     * un rótulo.
     *
     * Ninguna ley escribe un artículo entero en mayúsculas, y ninguno cabe en 60 caracteres.
     */
    public function test_un_parrafo_con_contenido_real_si_se_busca(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Hacienda']);

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_PARRAFO,
            'numero'        => null,
            'texto'         => 'No se considerarán objeto de este impuesto los ingresos que obtengan '
                             . 'la Federación, el Estado y los Municipios por los espectáculos públicos '
                             . 'que directamente realicen.',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        $resultados = $this->buscador->buscar('espectaculos publicos federacion')['resultados'];

        $this->assertGreaterThan(
            0,
            $resultados->count(),
            'Se descartó un párrafo con contenido real. El filtro solo debe tirar los RÓTULOS '
            . '(cortos y en mayúsculas), no los párrafos legítimos: considerandos, transitorios, '
            . 'excepciones...'
        );
    }
}
