<?php

namespace Tests\Feature;

use App\Models\ParametroActividadEconomica;
use App\Models\SectorScian;
use App\Models\Tramite;
use App\Services\CostoBurocraticoService;
use Database\Seeders\ParametrosActividadEconomicaSeeder;
use Database\Seeders\ParametrosCostoBurocraticoSeeder;
use Database\Seeders\ScianSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba de sensatez sobre los datos económicos REALES que se siembran.
 *
 * ── Qué clase de prueba es esta ──────────────────────────────────────
 *
 * No comprueba fórmulas: de eso ya se encargan CostoBurocraticoTest y
 * CostoResolucionTest, con números inventados y redondos.
 *
 * Esta comprueba que los datos que el sistema carga de verdad —el PIB de BCS, la población,
 * la tasa de CETES, los Censos Económicos del INEGI— producen resultados que tienen SENTIDO.
 *
 * ── Por qué hace falta ───────────────────────────────────────────────
 *
 * Una fórmula correcta con un dato mal capturado da un número perfectamente plausible. Y
 * eso es lo peor que puede pasar, porque nadie lo detecta.
 *
 * Ya nos ha pasado dos veces en este módulo:
 *
 *   1. El costo de espera se calculaba como días × salario × jornada. Daba $545.60 al día.
 *      Nadie miró nunca ese número y pensó "esto está mil veces mal".
 *
 *   2. Los requisitos en UMA se sumaban como si fueran pesos. Un requisito de 5 UMA (~$565)
 *      sumaba $5. También plausible. También invisible.
 *
 * La tasa libre de riesgo es la siguiente candidata: se guarda como DECIMAL (0.0630). Si
 * alguien la captura como porcentaje (6.30), el costo se multiplica por cien... y sigue
 * pareciendo razonable.
 *
 * Estas pruebas ponen cotas. No dicen "el número es exactamente X" —eso cambiaría cada vez
 * que el INEGI publique—, dicen "el número tiene que estar en este rango, porque si no, algo
 * está mal capturado".
 */
class DatosEconomicosRealesTest extends TestCase
{
    use RefreshDatabase;

    private CostoBurocraticoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ScianSeeder::class);
        $this->seed(ParametrosCostoBurocraticoSeeder::class);
        $this->seed(ParametrosActividadEconomicaSeeder::class);

        $this->servicio = app(CostoBurocraticoService::class);
    }

    private function costoDiarioDe(Tramite $tramite): float
    {
        return (float) $this->servicio->calcularCostos($tramite->fresh())['costo_oportunidad_diario'];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Persona física
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El costo de esperar un día, para un ciudadano de La Paz, ronda los 14 centavos.
     *
     *     PIB per cápita = 251,902,000,000 / 868,622 = $290,001
     *     Costo diario   = (0.0630 / 365) × (290,001 / 365) = $0.137
     *
     * El ejemplo de la metodología, para otra región y otro año, da $0.07. Mismo orden.
     *
     * ── Las cotas son la prueba, no el valor exacto ──
     *
     * Se afirma que el costo está ENTRE 1 centavo y 5 pesos, no que valga exactamente
     * $0.137. Un rango sobrevive a que el INEGI publique un PIB nuevo; un valor exacto se
     * pondría rojo cada año sin que nada estuviera roto.
     *
     * Pero el rango sí caza lo que importa: si alguien captura la tasa de CETES como 6.30 en
     * vez de 0.0630, el costo se dispara a $13.70 y esta prueba lo detecta. Y si alguien se
     * equivoca de signo o de escala en el PIB, también.
     */
    public function test_esperar_un_dia_le_cuesta_a_un_ciudadano_unos_catorce_centavos(): void
    {
        $tramite = Tramite::factory()->create([
            'dirigido_a'                => 'fisica',
            'plazo_resolucion_cantidad' => 1,
            'plazo_resolucion_unidad'   => 'naturales',
            'volumen_anual'             => 1,
        ]);

        $costoDiario = $this->costoDiarioDe($tramite);

        $this->assertGreaterThan(0.01, $costoDiario,
            'El costo de espera de una persona física salió casi cero: revisa el PIB y la población.');

        $this->assertLessThan(5.00, $costoDiario,
            'El costo de espera se disparó. La causa más probable: la tasa libre de riesgo se '
            . 'capturó como PORCENTAJE (6.30) en vez de como DECIMAL (0.0630). Eso multiplica '
            . 'el resultado por cien, y el número sigue pareciendo plausible.');
    }

    /**
     * LA PRUEBA QUE MATA EL BUG ORIGINAL.
     *
     * El sistema calculaba el costo de espera como días × salario_hora × jornada, que da
     * $545.60 POR DÍA. La metodología da entre $0.07 y $4.08.
     *
     * Esta prueba pone una cota que la fórmula vieja NO podría pasar de ninguna manera. Si
     * alguien la reintroduce —porque parece razonable, y por eso estuvo meses ahí—, se pone
     * roja al instante.
     */
    public function test_el_costo_de_espera_no_puede_volver_a_la_formula_del_salario(): void
    {
        $tramite = Tramite::factory()->create([
            'dirigido_a'                => 'fisica',
            'plazo_resolucion_cantidad' => 20,
            'plazo_resolucion_unidad'   => 'habiles', // → 28 días naturales
            'volumen_anual'             => 1,
        ]);

        $costoEspera = (float) $this->servicio->calcularCostos($tramite->fresh())['cbi_resolucion'];

        // Con la fórmula vieja: 28 × 68.20 × 8 = $15,276.80
        // Con la metodología:   28 × 0.137     = $3.84
        $this->assertLessThan(
            1000.00,
            $costoEspera,
            'Esperar 28 días naturales no puede costar más de mil pesos. Si esta prueba falla, '
            . 'lo más probable es que alguien haya vuelto a multiplicar por el salario y la '
            . 'jornada laboral (la fórmula vieja daba $15,276.80 para este mismo trámite).'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Persona moral
    // ═══════════════════════════════════════════════════════════════════════

    /** Los 16 sectores con datos públicos del INEGI se sembraron. */
    public function test_se_siembran_los_dieciseis_sectores_con_datos_publicos(): void
    {
        // 16 filas de la monografía → 19 registros, porque Manufacturas (31-33) y
        // Transportes (48-49) son una sola fila del INEGI que abarca varios códigos SCIAN.
        $this->assertSame(19, ParametroActividadEconomica::activos()->count());
    }

    /**
     * Minería (21) y Electricidad, agua y gas (22) NO se siembran.
     *
     * El INEGI omite sus cifras por confidencialidad: en todo BCS hay 21 y 5 unidades
     * económicas respectivamente, y publicarlas identificaría a las empresas.
     *
     * Un trámite de esos sectores dirigido a personas morales queda NO CALCULABLE. Es la
     * respuesta honesta: no tenemos el dato, y no lo inventamos.
     */
    public function test_mineria_y_electricidad_no_tienen_datos_y_quedan_no_calculables(): void
    {
        $mineria = SectorScian::where('codigo', '21')->first();

        $tramite = Tramite::factory()->create([
            'dirigido_a'                => 'moral',
            'sector_id'                 => $mineria->id,
            'etapa_operacion'           => ParametroActividadEconomica::ETAPA_OPERACION,
            'plazo_resolucion_cantidad' => 10,
            'plazo_resolucion_unidad'   => 'naturales',
        ]);

        $costos = $this->servicio->calcularCostos($tramite->fresh());

        $this->assertFalse(
            $costos['resolucion_calculable'],
            'El INEGI no publica las cifras de Minería en BCS por confidencialidad. '
            . 'El sistema debe decir que no lo sabe, no inventar una cifra.'
        );
    }

    /**
     * Una empresa de hotelería o comercio en BCS pierde entre 1 y 200 pesos por cada día que
     * espera una resolución.
     *
     * Los ejemplos de la metodología van de $1.37 (industria alimentaria) a $4.08 (comercio
     * al por menor). Con los datos reales de BCS el rango es más ancho —el comercio al por
     * mayor sale alto, porque mueve mucha producción con poco capital—, pero está en el mismo
     * territorio.
     *
     * Las cotas cazan los errores de ESCALA, que son los que de verdad ocurren: si alguien
     * captura las cifras del INEGI en millones cuando el sistema espera pesos (o al revés),
     * el resultado se va un millón de veces arriba o abajo y esta prueba lo ve.
     */
    public function test_el_costo_diario_de_una_empresa_esta_en_un_rango_creible(): void
    {
        $codigosAProbar = ['46', '72', '23', '62']; // comercio, hoteles, construcción, salud

        foreach ($codigosAProbar as $codigo) {
            $sector = SectorScian::where('codigo', $codigo)->first();

            $tramite = Tramite::factory()->create([
                'dirigido_a'                => 'moral',
                'sector_id'                 => $sector->id,
                'etapa_operacion'           => ParametroActividadEconomica::ETAPA_OPERACION,
                'plazo_resolucion_cantidad' => 1,
                'plazo_resolucion_unidad'   => 'naturales',
                'volumen_anual'             => 1,
            ]);

            $costoDiario = $this->costoDiarioDe($tramite);

            $this->assertGreaterThan(0.50, $costoDiario,
                "Sector {$codigo}: el costo diario salió casi cero. Probable error de escala en "
                . 'los datos del INEGI (¿se sembraron en millones en vez de en pesos?).');

            $this->assertLessThan(200.00, $costoDiario,
                "Sector {$codigo}: el costo diario se disparó. Probable error de escala en los "
                . 'datos del INEGI (¿se sembraron en pesos en vez de en millones?).');
        }
    }

    /**
     * Abrir una empresa cuesta más que operarla: en apertura todavía hay que poner los
     * activos fijos.
     *
     * Se prueba con hoteles y restaurantes (sector 72) porque es donde más se nota: concentra
     * el 35.5 % de los activos fijos de todo BCS. Una empresa hotelera que espera un permiso
     * ANTES de abrir tiene mucho más capital parado que una que ya está funcionando.
     */
    public function test_abrir_un_hotel_cuesta_mas_que_operarlo(): void
    {
        $hoteles = SectorScian::where('codigo', '72')->first();

        $base = [
            'dirigido_a'                => 'moral',
            'sector_id'                 => $hoteles->id,
            'plazo_resolucion_cantidad' => 30,
            'plazo_resolucion_unidad'   => 'naturales',
            'volumen_anual'             => 1,
        ];

        $apertura  = Tramite::factory()->create($base + ['etapa_operacion' => ParametroActividadEconomica::ETAPA_APERTURA]);
        $operacion = Tramite::factory()->create($base + ['etapa_operacion' => ParametroActividadEconomica::ETAPA_OPERACION]);

        $this->assertGreaterThan(
            $this->costoDiarioDe($operacion),
            $this->costoDiarioDe($apertura),
            'La etapa de apertura suma los activos fijos al capital comprometido: tiene que '
            . 'costar más esperar. Si esto falla, la etapa no se está leyendo del trámite.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. La trampa de las unidades
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * VIGILA EL ERROR MÁS PROBABLE DE TODO EL MÓDULO.
     *
     * La tasa libre de riesgo se guarda como DECIMAL: un 6.30 % anual es 0.0630.
     *
     * Si alguien la actualiza el año que viene y escribe 6.30 —que es lo que dice la página
     * de Banxico— el costo de espera se multiplica por CIEN. Y seguiría pareciendo
     * razonable: nadie mira "$13.70 al día" y piensa "esto está cien veces mal".
     *
     * Es el mismo error que el UMA de los requisitos: un número correcto en la unidad
     * equivocada. Por eso hay una prueba que lo vigila explícitamente.
     */
    public function test_la_tasa_libre_de_riesgo_esta_guardada_como_decimal(): void
    {
        $tasa = \App\Models\ParametroCostoBurocratico::where(
            'clave',
            \App\Models\ParametroCostoBurocratico::CLAVE_TASA_LIBRE_RIESGO
        )->value('valor');

        $this->assertLessThan(
            1.0,
            (float) $tasa,
            'La tasa libre de riesgo debe guardarse como DECIMAL (0.0630), no como porcentaje '
            . '(6.30). Capturarla como porcentaje multiplica por cien el costo de espera de '
            . 'TODOS los trámites del municipio, y el resultado sigue pareciendo plausible.'
        );

        $this->assertGreaterThan(0.0, (float) $tasa);
    }
}
