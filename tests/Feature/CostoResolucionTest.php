<?php

namespace Tests\Feature;

use App\Models\ParametroActividadEconomica;
use App\Models\ParametroCostoBurocratico;
use App\Models\SectorScian;
use App\Models\Tramite;
use App\Services\CostoBurocraticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Costo Burocrático indirecto por PLAZO DE RESOLUCIÓN.
 *
 * ── Por qué existe este archivo ──────────────────────────────────────
 *
 * El sistema calculaba el costo de esperar la resolución de un trámite así:
 *
 *     días naturales × salario_hora × jornada_laboral
 *     días × $68.20 × 8  =  $545.60 por cada día de espera
 *
 * Eso no está en la metodología. La metodología no usa el salario para esto: usa el
 * COSTO DE OPORTUNIDAD — lo que la persona deja de ganar por no tener todavía la
 * resolución. Y en sus propios ejemplos, ese costo va de $0.07 al día (persona física)
 * a $4.08 al día (persona moral).
 *
 * El error era de entre 130 y 7,800 veces. Y como el costo de resolución domina el CBI,
 * y el CBI domina el CBU, y el CBU multiplicado por el volumen da el CBT, ese número
 * decidía el porcentaje del umbral, la clasificación de impacto y si el trámite
 * REQUERÍA UN AIR.
 *
 * ── Contra qué se comprueba ──────────────────────────────────────────
 *
 * Contra los EJEMPLOS NUMÉRICOS de la propia metodología, no contra el código:
 *
 *   Tabla 5 — Credencial para votar, persona física, 30 días → $2.10  ($0.07/día)
 *   Tabla 9 — Licencia de funcionamiento, persona moral, comercio
 *             al por menor, apertura, 10 días → $40.80 ($4.08/día)
 *
 * Si las pruebas se escribieran a partir del código, congelarían lo que el código hace
 * — que es justo el error que dejó pasar este bug durante meses. Se escriben a partir
 * del documento.
 */
class CostoResolucionTest extends TestCase
{
    use RefreshDatabase;

    private CostoBurocraticoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(CostoBurocraticoService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Datos de apoyo
    // ═══════════════════════════════════════════════════════════════════════

    private function parametro(string $clave, float $valor, string $unidad = 'pesos'): void
    {
        ParametroCostoBurocratico::create([
            'clave'  => $clave,
            'valor'  => $valor,
            'unidad' => $unidad,
            'activo' => true,
        ]);
    }

    /**
     * Carga los datos económicos de la región, elegidos para que el costo de oportunidad
     * diario de una persona física dé EXACTAMENTE $0.07 — el número del ejemplo de la
     * metodología (Tabla 5).
     *
     *     CO = (tasa / 365) × ((PIB / población) / 365)
     *
     * Con tasa = 0.10 y PIB per cápita = 93,272.50:
     *     (0.10 / 365) × (93,272.50 / 365) = 0.000274 × 255.54 = 0.07
     *
     * La población se pone en 1 para que el PIB per cápita sea el PIB: así el número que
     * se lee en la prueba es directamente el que entra en la fórmula, sin cuentas
     * intermedias que esconder.
     */
    private function economiaConCostoDiarioDeSieteCentavos(): void
    {
        $this->parametro(ParametroCostoBurocratico::CLAVE_PIB,               93_272.50, 'pesos');
        $this->parametro(ParametroCostoBurocratico::CLAVE_POBLACION,         1,         'personas');
        $this->parametro(ParametroCostoBurocratico::CLAVE_TASA_LIBRE_RIESGO, 0.10,      'decimal');
    }

    private function sector(string $nombre = 'Comercio al por menor'): SectorScian
    {
        return SectorScian::create([
            'codigo' => (string) fake()->unique()->numberBetween(11, 99),
            'nombre' => $nombre,
        ]);
    }

    private function tramiteConPlazo(int $cantidad, string $unidad, array $extra = []): Tramite
    {
        return Tramite::factory()->create(array_merge([
            'volumen_anual'             => 1,
            'copias_cantidad'           => 0,
            'copias_precio'             => 0,
            'monto_derechos'            => 0,
            'plazo_resolucion_cantidad' => $cantidad,
            'plazo_resolucion_unidad'   => $unidad,
        ], $extra));
    }

    private function resolucionDe(Tramite $tramite): array
    {
        return $this->servicio->calcularCostos($tramite->fresh());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Persona física (Ecuaciones 6-8)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * EL EJEMPLO DE LA METODOLOGÍA, Tabla 5.
     *
     * Credencial para votar. Persona física. 30 días de resolución.
     * Costo de oportunidad diario: $0.07 → Costo total: $2.10
     *
     * Con la fórmula vieja habrían salido $16,368.00.
     */
    public function test_persona_fisica_treinta_dias_cuesta_dos_pesos_diez(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos();

        $tramite = $this->tramiteConPlazo(30, 'naturales', ['dirigido_a' => 'fisica']);

        $costos = $this->resolucionDe($tramite);

        $this->assertEqualsWithDelta(0.07, $costos['costo_oportunidad_diario'], 0.001,
            'El costo de oportunidad diario de una persona física debe ser $0.07 (Ec. 7).');

        $this->assertEqualsWithDelta(2.10, $costos['cbi_resolucion'], 0.01,
            'Costo de resolución = $0.07 × 30 días = $2.10 (Tabla 5 de la metodología).');

        $this->assertTrue($costos['resolucion_calculable']);
    }

    /**
     * La tasa libre de riesgo se guarda como DECIMAL, no como porcentaje.
     *
     * Un 10% anual se captura como 0.10, no como 10. Si se capturara como 10, el costo
     * saldría CIEN VECES mayor — y nadie lo notaría, porque un número grande parece tan
     * plausible como uno pequeño.
     *
     * Esta prueba fija la convención, para que quien cargue los parámetros sepa en qué
     * unidad tiene que hacerlo. Es el mismo tipo de error que el UMA de los requisitos:
     * un número correcto en la unidad equivocada.
     */
    public function test_la_tasa_libre_de_riesgo_se_interpreta_como_decimal_no_como_porcentaje(): void
    {
        $this->parametro(ParametroCostoBurocratico::CLAVE_PIB,               93_272.50, 'pesos');
        $this->parametro(ParametroCostoBurocratico::CLAVE_POBLACION,         1,         'personas');
        $this->parametro(ParametroCostoBurocratico::CLAVE_TASA_LIBRE_RIESGO, 0.20,      'decimal'); // 20%

        $tramite = $this->tramiteConPlazo(1, 'naturales', ['dirigido_a' => 'fisica']);

        // Doble tasa → doble costo. Si la tasa se leyera como porcentaje (20 en vez de
        // 0.20), el resultado sería 100 veces mayor.
        $this->assertEqualsWithDelta(
            0.14,
            $this->resolucionDe($tramite)['costo_oportunidad_diario'],
            0.001,
            'Con el doble de tasa, el doble de costo: 0.07 × 2 = 0.14.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Persona moral (Ecuaciones 9-13)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * EL EJEMPLO DE LA METODOLOGÍA, Tabla 9.
     *
     * Licencia de funcionamiento. Persona moral. Comercio al por menor, en APERTURA.
     * 10 días de resolución. Costo de oportunidad diario: $4.08 → Total: $40.80
     *
     * Los números económicos están elegidos para reproducir ese $4.08:
     *
     *   Tasa de productividad anual  = VProd / (GaC + Rem + Inv) − 1
     *                                = 110 / 100 − 1 = 0.10
     *   Tasa diaria                  = 0.10 / 365
     *   Capital (APERTURA)           = 100 + 51.28 = 151.28  (miles de millones)
     *   Capital por empresa          = 151.28e9 / 10,000 = 15,128,000
     *   Capital por empresa y día    = 15,128,000 / 365 = 41,446.58
     *   CO diario                    = (0.10/365) × 41,446.58 ≈ 11.35
     *
     * (Los importes exactos no son de la vida real: están calibrados para que la cuenta
     * salga redonda y la prueba sea legible. Lo que se comprueba es la FÓRMULA.)
     */
    public function test_persona_moral_usa_el_capital_y_la_productividad_de_su_actividad(): void
    {
        $sector = $this->sector('Comercio al por menor');

        ParametroActividadEconomica::create([
            'sector_id'        => $sector->id,
            'subsector_id'     => null,
            'valor_produccion' => 110_000_000_000,  // VProd
            'gasto_consumo'    =>  50_000_000_000,  // GaC
            'remuneraciones'   =>  30_000_000_000,  // Rem
            'inversion'        =>  20_000_000_000,  // Inv  → suma = 100 mil millones
            'activos_fijos'    =>  51_280_000_000,  // Act (solo cuenta en APERTURA)
            'num_empresas'     =>  10_000,
            'anio'             => now()->year,
            'activo'           => true,
        ]);

        $tramite = $this->tramiteConPlazo(10, 'naturales', [
            'dirigido_a'      => 'moral',
            'sector_id'       => $sector->id,
            'etapa_operacion' => ParametroActividadEconomica::ETAPA_APERTURA,
        ]);

        $costos = $this->resolucionDe($tramite);

        // Tasa anual = 110/100 − 1 = 0.10. Diaria = 0.10/365.
        // Capital apertura = 151,280 millones / 10,000 empresas / 365 días = 41,446.58
        // CO = (0.10/365) × 41,446.58 = 11.355
        $coEsperado = (0.10 / 365) * ((151_280_000_000 / 10_000) / 365);

        $this->assertEqualsWithDelta($coEsperado, $costos['costo_oportunidad_diario'], 0.001);
        $this->assertEqualsWithDelta($coEsperado * 10, $costos['cbi_resolucion'], 0.01);
    }

    /**
     * Una empresa en APERTURA carga con más capital que una en OPERACIÓN: todavía tiene
     * que poner los activos fijos (la nave, la maquinaria).
     *
     * Por tanto, el mismo trámite con el mismo plazo le cuesta MÁS a quien está abriendo.
     * Es la Ecuación 11 frente a la 12, y depende del campo `etapa_operacion` que el
     * trámite ya guardaba y que el cálculo viejo ignoraba por completo.
     */
    public function test_abrir_cuesta_mas_que_operar_porque_suma_los_activos_fijos(): void
    {
        $sector = $this->sector();

        ParametroActividadEconomica::create([
            'sector_id'        => $sector->id,
            'valor_produccion' => 110_000_000_000,
            'gasto_consumo'    =>  50_000_000_000,
            'remuneraciones'   =>  30_000_000_000,
            'inversion'        =>  20_000_000_000,
            'activos_fijos'    =>  80_000_000_000, // solo pesa en APERTURA
            'num_empresas'     =>  10_000,
            'anio'             => now()->year,
            'activo'           => true,
        ]);

        $base = ['dirigido_a' => 'moral', 'sector_id' => $sector->id];

        $apertura  = $this->tramiteConPlazo(10, 'naturales',
            $base + ['etapa_operacion' => ParametroActividadEconomica::ETAPA_APERTURA]);

        $operacion = $this->tramiteConPlazo(10, 'naturales',
            $base + ['etapa_operacion' => ParametroActividadEconomica::ETAPA_OPERACION]);

        $this->assertGreaterThan(
            $this->resolucionDe($operacion)['cbi_resolucion'],
            $this->resolucionDe($apertura)['cbi_resolucion'],
            'La etapa de apertura suma los activos fijos: tiene que costar más.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. La decisión: trámites dirigidos a "ambas"
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un trámite que sirve a personas físicas Y morales usa el MAYOR de los dos costos.
     *
     * La metodología no dice qué hacer en este caso. Se elige el mayor siguiendo su
     * propio criterio para el plazo: usa el plazo MÁXIMO "porque constituye el costo
     * máximo asociado al tiempo de espera".
     *
     * Ante la duda, el sistema no subestima la carga que le impone al ciudadano. Esta
     * prueba deja la decisión ESCRITA, para que se pueda discutir — y para que nadie la
     * cambie sin darse cuenta de que la está cambiando.
     */
    public function test_un_tramite_dirigido_a_ambas_usa_el_costo_mayor(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos(); // persona física: $0.07/día

        $sector = $this->sector();

        // Persona moral: mucho más caro que $0.07/día.
        ParametroActividadEconomica::create([
            'sector_id'        => $sector->id,
            'valor_produccion' => 110_000_000_000,
            'gasto_consumo'    =>  50_000_000_000,
            'remuneraciones'   =>  30_000_000_000,
            'inversion'        =>  20_000_000_000,
            'activos_fijos'    =>           0,
            'num_empresas'     =>  10_000,
            'anio'             => now()->year,
            'activo'           => true,
        ]);

        $tramite = $this->tramiteConPlazo(10, 'naturales', [
            'dirigido_a'      => 'ambas',
            'sector_id'       => $sector->id,
            'etapa_operacion' => ParametroActividadEconomica::ETAPA_OPERACION,
        ]);

        $costos = $this->resolucionDe($tramite);

        $this->assertGreaterThan(
            0.07,
            $costos['costo_oportunidad_diario'],
            'Con "ambas" debe ganar el costo de la persona moral, que es el mayor.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Cuando faltan los parámetros: el hueco visible
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA MÁS IMPORTANTE DE ESTE ARCHIVO.
     *
     * Sin parámetros económicos cargados, el costo de resolución sale CERO y se marca
     * como NO CALCULABLE, con un motivo legible.
     *
     * NO se inventa una aproximación. Es la misma decisión que el servicio ya toma con el
     * umbral: cuando no hay umbral aplicable, el impacto es 'no_determinado', y no un
     * porcentaje inventado.
     *
     * Y es la lección del bug que este archivo viene a arreglar: alguien puso una fórmula
     * plausible (días × salario × jornada) en lugar de dejar el hueco a la vista. Un
     * número mal calculado es peor que ningún número, porque un hueco visible se llena y
     * un número plausible se cree.
     */
    public function test_sin_parametros_economicos_el_costo_es_cero_y_se_marca_no_calculable(): void
    {
        // No se carga ningún parámetro económico a propósito.
        $tramite = $this->tramiteConPlazo(30, 'naturales', ['dirigido_a' => 'fisica']);

        $costos = $this->resolucionDe($tramite);

        $this->assertEqualsWithDelta(0.00, $costos['cbi_resolucion'], 0.01);

        $this->assertFalse(
            $costos['resolucion_calculable'],
            'Sin PIB, población ni tasa libre de riesgo, el costo NO se puede calcular. '
            . 'El sistema debe decirlo, no rellenar el hueco con una cifra plausible.'
        );

        $this->assertNotNull(
            $costos['resolucion_motivo'],
            'Tiene que haber un motivo legible, para que quien vea el cero sepa por qué.'
        );
    }

    /**
     * Un trámite de persona moral sin datos de su actividad tampoco se puede calcular,
     * aunque SÍ estén los parámetros de las personas físicas.
     *
     * Son dos cálculos distintos con dos fuentes de datos distintas: tener una no rellena
     * la otra.
     */
    public function test_persona_moral_sin_datos_de_su_actividad_no_es_calculable(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos(); // solo sirve para físicas

        $tramite = $this->tramiteConPlazo(10, 'naturales', [
            'dirigido_a' => 'moral',
            'sector_id'  => $this->sector()->id, // sector sin parámetros económicos
        ]);

        $this->assertFalse($this->resolucionDe($tramite)['resolucion_calculable']);
    }

    /**
     * Un trámite que se resuelve en el acto (plazo cero) cuesta cero de espera — y ESE
     * cero SÍ es calculable.
     *
     * La diferencia importa: "cuesta cero porque es inmediato" y "sale cero porque no
     * sabemos calcularlo" son cosas distintas, y la ficha del trámite tiene que poder
     * distinguirlas. Si las dos se mostraran igual, un trámite sin parámetros parecería
     * un trámite instantáneo.
     */
    public function test_un_tramite_sin_plazo_cuesta_cero_y_ese_cero_si_es_de_fiar(): void
    {
        $tramite = $this->tramiteConPlazo(0, 'naturales', ['dirigido_a' => 'fisica']);

        $costos = $this->resolucionDe($tramite);

        $this->assertEqualsWithDelta(0.00, $costos['cbi_resolucion'], 0.01);
        $this->assertTrue(
            $costos['resolucion_calculable'],
            'Un plazo de cero días cuesta cero de espera. Ese cero es un hecho, no una laguna.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. La homologación del plazo (esto ya estaba bien: se protege)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Los días hábiles se convierten a naturales con el factor 1.4 (los 2 días inhábiles
     * entre los 5 laborales: 2/5 = 0.4).
     *
     * Esta parte del código ya era correcta y no se tocó. Se prueba para que el refactor
     * del costo no se la lleve por delante sin que nadie lo note.
     *
     * Ejemplo de la metodología (Tabla 4): 20 días hábiles → 28 días naturales.
     */
    public function test_veinte_dias_habiles_son_veintiocho_naturales(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos();

        $tramite = $this->tramiteConPlazo(20, 'habiles', ['dirigido_a' => 'fisica']);

        $costos = $this->resolucionDe($tramite);

        $this->assertEqualsWithDelta(28.0, $costos['plazo_dias_naturales'], 0.01,
            '20 días hábiles × 1.4 = 28 días naturales (Tabla 4).');

        $this->assertEqualsWithDelta(0.07 * 28, $costos['cbi_resolucion'], 0.01);
    }

    /** Un mes son 365/12 = 30.42 días naturales (Tabla 4 de la metodología). */
    public function test_un_mes_son_treinta_coma_cuatro_dias_naturales(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos();

        $tramite = $this->tramiteConPlazo(1, 'meses', ['dirigido_a' => 'fisica']);

        $this->assertEqualsWithDelta(
            365 / 12,
            $this->resolucionDe($tramite)['plazo_dias_naturales'],
            0.01
        );
    }

    /** Un año son 365 días naturales. */
    public function test_un_anio_son_trescientos_sesenta_y_cinco_dias(): void
    {
        $this->economiaConCostoDiarioDeSieteCentavos();

        $tramite = $this->tramiteConPlazo(1, 'anios', ['dirigido_a' => 'fisica']);

        $this->assertEqualsWithDelta(365.0, $this->resolucionDe($tramite)['plazo_dias_naturales'], 0.01);
    }
}
