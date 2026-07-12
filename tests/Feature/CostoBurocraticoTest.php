<?php

namespace Tests\Feature;

use App\Models\Requisito;
use App\Models\Tramite;
use App\Models\TramiteCostoBurocratico;
use App\Models\TramiteDerecho;
use App\Models\UmbralConfigurado;
use App\Models\UnidadValorReferencia;
use App\Services\CostoBurocraticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del cálculo del Costo Burocrático.
 *
 * ── Por qué se reescribió este archivo entero ────────────────────────
 *
 * La versión anterior tenía 5 pruebas en verde y su propio comentario decía:
 *
 *     "Es la parte más delicada del sistema: un error aquí desajusta en silencio
 *      el CBD, el CBU y el CBT."
 *
 * Y no había NI UNA aserción sobre el CBD, el CBU o el CBT. Ninguna.
 *
 * Peor: 3 de las 5 pruebas ni siquiera llamaban al servicio. Comprobaban
 * TramiteDerecho::totalEnPesos(), un ayudante del modelo. Y una de ellas era esto:
 *
 *     $this->assertIsArray($costos);
 *     $this->assertArrayHasKey('tiene_costos_variables', $costos);
 *
 * Eso pasaría aunque calcularCostos() devolviera un array con esa única clave y nada
 * más. Verde perfecto sobre un servicio destripado.
 *
 * Este archivo prueba NÚMEROS, contra las ecuaciones de la metodología.
 *
 * ── Las ecuaciones que se comprueban ─────────────────────────────────
 *
 *   Ec. 4  CBD = derechos + copias + montos de requisitos
 *   Ec. 5  CBI por requisitos = Σ (tiempo homologado en horas × salario)
 *          Homologación: (días × 8) + horas + (minutos / 60)
 *   Ec. 18 CBU = CBD + CBI
 *   Ec. 19 CBT = CBU × volumen anual
 *
 * ── Los parámetros por defecto (fallback del modelo Tramite) ─────────
 *
 *   salario_hora        = 68.20
 *   jornada_laboral     = 8 horas
 *   factor_dias_habiles = 1.4
 *   dias_por_mes        = 365/12
 *
 * ── AVISO: DOS PRUEBAS FALLAN A PROPÓSITO ────────────────────────────
 *
 * Están escritas en ROJO para documentar un bug real:
 *
 *   test_un_requisito_capturado_en_uma_se_convierte_a_pesos
 *   test_un_requisito_en_uma_no_puede_valer_lo_mismo_que_uno_en_pesos
 *
 * El formulario permite capturar el costo de un requisito en UMA (guarda
 * `costo_unidad` = 'UMA'), pero sumarCostoRequisitos() ignora esa columna y suma el
 * número tal cual. Un requisito de 5 UMA (≈ $565) suma $5.
 *
 * Los DERECHOS sí convierten. Los REQUISITOS no. Es una asimetría, no una decisión.
 */
class CostoBurocraticoTest extends TestCase
{
    use RefreshDatabase;

    private CostoBurocraticoService $servicio;

    /** Valor de la UMA que se usa en las pruebas. Redondo, para que las cuentas se lean. */
    private const VALOR_UMA = 100.00;

    private const SALARIO_HORA = 68.20;
    private const JORNADA      = 8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(CostoBurocraticoService::class);
    }

    /**
     * Siembra el valor de la UMA.
     *
     * OJO: si no se siembra, TramiteDerecho::valorUmaVigente() devuelve 0.0 — y
     * entonces un derecho de 5 UMA suma CERO pesos, en silencio. Hay una prueba
     * abajo que fija ese comportamiento, porque es un caso de soporte real: si nadie
     * carga el catálogo de la UMA, todos los derechos en UMA valen cero y nadie se
     * entera.
     */
    private function sembrarUma(float $valor = self::VALOR_UMA): void
    {
        UnidadValorReferencia::create([
            'unidad'      => UnidadValorReferencia::UMA,
            'anio'        => now()->year,
            'valor_pesos' => $valor,
            'activo'      => true,
        ]);
    }

    /**
     * Un trámite "en blanco" para el cálculo: sin copias, sin plazo de resolución,
     * volumen 1. Así, cada prueba enciende SOLO la variable que quiere medir y el
     * resto vale cero. Si el trámite trajera valores de la factory, no se sabría de
     * dónde sale cada peso.
     */
    private function tramiteNeutro(array $extra = []): Tramite
    {
        return Tramite::factory()->create(array_merge([
            'volumen_anual'             => 1,
            'copias_cantidad'           => 0,
            'copias_precio'             => 0,
            'plazo_resolucion_cantidad' => 0,
            'monto_derechos'            => 0,
            'monto_derechos_variable'   => false,
        ], $extra));
    }

    private function derecho(Tramite $tramite, float $monto, string $unidad = 'PESOS', bool $variable = false): void
    {
        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho de prueba',
            'monto'       => $monto,
            'unidad'      => $unidad,
            'es_variable' => $variable,
        ]);
    }

    private function requisito(Tramite $tramite, array $campos): void
    {
        Requisito::create(array_merge([
            'tramite_id'        => $tramite->id,
            'nombre'            => 'Requisito de prueba',
            'orden'             => 1,
            'dias_estimados'    => 0,
            'horas_estimadas'   => 0,
            'minutos_estimados' => 0,
            'tiene_costo'       => false,
            'costo_variable'    => false,
            'costo_requisito'   => 0,
        ], $campos));
    }

    private function costosDe(Tramite $tramite): array
    {
        return $this->servicio->calcularCostos($tramite->fresh());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. Costo Burocrático Directo (Ecuación 4)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CBD = derechos + copias + montos de requisitos.
     *
     * La prueba enciende las tres piezas a la vez, con números que no se pueden
     * confundir entre sí:
     *
     *     derechos       →  500.00
     *     copias         →   10 × 1.50  =  15.00
     *     requisitos     →  300.00
     *     ─────────────────────────────────────
     *     CBD                             815.00
     *
     * Si el resultado sale 500, alguien dejó de contar copias y requisitos. Si sale
     * 515, se perdieron los requisitos. Cada cifra delata a un sumando distinto.
     */
    public function test_el_cbd_suma_derechos_mas_copias_mas_requisitos(): void
    {
        $tramite = $this->tramiteNeutro([
            'copias_cantidad' => 10,
            'copias_precio'   => 1.50,
        ]);

        $this->derecho($tramite, 500.00);
        $this->requisito($tramite, ['tiene_costo' => true, 'costo_requisito' => 300.00]);

        $costos = $this->costosDe($tramite);

        $this->assertEqualsWithDelta(500.00, $costos['monto_derechos_fijos'], 0.01, 'Derechos');
        $this->assertEqualsWithDelta( 15.00, $costos['monto_copias'],         0.01, 'Copias: 10 × 1.50');
        $this->assertEqualsWithDelta(300.00, $costos['monto_requisitos'],     0.01, 'Requisitos');
        $this->assertEqualsWithDelta(815.00, $costos['cbd_unitario'],         0.01, 'CBD = 500 + 15 + 300');
    }

    /**
     * Un derecho VARIABLE no entra en el CBD.
     *
     * Es el caso del predial: el monto depende de cada caso, no es una cifra fija. La
     * metodología exige que el CBD sea una suma de montos concretos; meter una cifra
     * que no es un costo real inflaría el indicador y lo haría incomparable.
     */
    public function test_un_derecho_variable_no_entra_en_el_cbd(): void
    {
        $tramite = $this->tramiteNeutro();

        $this->derecho($tramite, 500.00);
        $this->derecho($tramite, 9999.00, variable: true); // aunque tenga monto capturado

        $costos = $this->costosDe($tramite);

        $this->assertEqualsWithDelta(500.00, $costos['cbd_unitario'], 0.01, 'El variable no cuenta.');
    }

    /** Un derecho en UMA se convierte a pesos antes de sumarse. */
    public function test_un_derecho_en_uma_se_convierte_a_pesos(): void
    {
        $this->sembrarUma(100.00);

        $tramite = $this->tramiteNeutro();
        $this->derecho($tramite, 5, 'UMA'); // 5 UMA × $100 = $500

        $this->assertEqualsWithDelta(500.00, $this->costosDe($tramite)['cbd_unitario'], 0.01);
    }

    /**
     * CASO DE SOPORTE — si nadie sembró el catálogo de la UMA, un derecho en UMA vale CERO.
     *
     * valorUmaVigente() devuelve 0.0 cuando no encuentra registro activo. Entonces
     * 5 UMA × 0 = 0 pesos. El trámite se guarda tan tranquilo, el CBD sale bajísimo, y
     * nadie se entera de nada.
     *
     * Esta prueba NO dice que esté bien. Dice que es lo que pasa hoy, para que la
     * decisión de arreglarlo (avisar, lanzar excepción, bloquear el guardado) sea
     * consciente y no un descubrimiento dentro de dos años.
     */
    public function test_sin_uma_sembrada_un_derecho_en_uma_vale_cero(): void
    {
        // No se llama a sembrarUma() a propósito.
        $tramite = $this->tramiteNeutro();
        $this->derecho($tramite, 5, 'UMA');

        $this->assertEqualsWithDelta(
            0.00,
            $this->costosDe($tramite)['cbd_unitario'],
            0.01,
            'Sin catálogo de UMA, los derechos en UMA valen cero y el CBD se subestima en silencio.'
        );
    }

    /**
     * ROJO — EL BUG.
     *
     * El formulario permite capturar el costo de un requisito en UMA. sincronizarRequisitos()
     * guarda esa unidad en la columna `costo_unidad` ('PESOS' o 'UMA').
     *
     * Pero sumarCostoRequisitos() la ignora:
     *
     *     return $tramite->requisitos->sum(fn ($r) =>
     *         ($r->tiene_costo && !$r->costo_variable) ? floatval($r->costo_requisito ?? 0) : 0
     *     );
     *
     * Suma el número tal cual. Un requisito de 5 UMA (≈ $500) suma $5.
     *
     * Los DERECHOS sí convierten (usan valorUmaVigente()). Los REQUISITOS no. Es una
     * asimetría, no una decisión: los dos son montos en pesos dentro de la misma
     * Ecuación 4.
     *
     * El error se arrastra al CBD, al CBU, al CBT, al porcentaje del umbral, a la
     * clasificación de impacto y al resultado AIR. Todo en silencio.
     */
    public function test_un_requisito_capturado_en_uma_se_convierte_a_pesos(): void
    {
        $this->sembrarUma(100.00);

        $tramite = $this->tramiteNeutro();
        $this->requisito($tramite, [
            'tiene_costo'     => true,
            'costo_requisito' => 5,       // 5 UMA...
            'costo_unidad'    => 'UMA',   // ...capturadas en UMA
        ]);

        $this->assertEqualsWithDelta(
            500.00,
            $this->costosDe($tramite)['monto_requisitos'],
            0.01,
            'Un requisito de 5 UMA debe valer $500, no $5. sumarCostoRequisitos() ignora '
            . 'la columna costo_unidad y suma el número como si fueran pesos.'
        );
    }

    /**
     * ROJO — la misma avería, dicha de la forma más clara posible.
     *
     * Dos requisitos con el MISMO número (5) pero unidades distintas no pueden costar
     * lo mismo. Si el sistema dice que sí, es que no está mirando la unidad.
     */
    public function test_un_requisito_en_uma_no_puede_valer_lo_mismo_que_uno_en_pesos(): void
    {
        $this->sembrarUma(100.00);

        $enPesos = $this->tramiteNeutro();
        $this->requisito($enPesos, ['tiene_costo' => true, 'costo_requisito' => 5, 'costo_unidad' => 'PESOS']);

        $enUma = $this->tramiteNeutro();
        $this->requisito($enUma, ['tiene_costo' => true, 'costo_requisito' => 5, 'costo_unidad' => 'UMA']);

        $this->assertNotEqualsWithDelta(
            $this->costosDe($enPesos)['cbd_unitario'],
            $this->costosDe($enUma)['cbd_unitario'],
            0.01,
            '5 pesos y 5 UMA están dando el mismo costo: el sistema no está mirando la unidad.'
        );
    }

    /**
     * Un requisito de costo VARIABLE (plano arquitectónico, dictamen de un perito) no
     * suma al CBD — su monto no es cuantificable — pero su TIEMPO sí cuenta en el CBI.
     *
     * Las dos mitades en la misma prueba, porque son la misma regla vista desde dos
     * lados, y separarlas permitiría que una pasara y la otra no sin que nadie lo note.
     */
    public function test_un_requisito_variable_no_suma_al_cbd_pero_su_tiempo_si_al_cbi(): void
    {
        $tramite = $this->tramiteNeutro();

        $this->requisito($tramite, [
            'tiene_costo'     => true,
            'costo_variable'  => true,
            'costo_requisito' => 9999.00, // no debe contar
            'horas_estimadas' => 2,       // 2 h × 68.20 = 136.40, sí debe contar
        ]);

        $costos = $this->costosDe($tramite);

        $this->assertEqualsWithDelta(0.00, $costos['monto_requisitos'], 0.01,
            'El monto variable no puede entrar en el CBD.');

        $this->assertEqualsWithDelta(2 * self::SALARIO_HORA, $costos['cbi_requisitos'], 0.01,
            'Pero el tiempo que le cuesta al ciudadano conseguirlo SÍ cuenta en el CBI.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Costo Burocrático Indirecto (Ecuación 5)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El tiempo de cada requisito se homologa a horas y se multiplica por el salario.
     *
     * Homologación (metodología, Tabla 1): (días × 8) + horas + (minutos / 60)
     *
     * La prueba usa los tres a la vez, con el ejemplo de la propia metodología:
     *
     *     1 día     → 8.000 h
     *     1 hora    → 1.000 h
     *     30 min    → 0.500 h
     *     ──────────────────────
     *                 9.500 h  × $68.20 = $647.90
     *
     * Si alguien cambia la jornada de 8 a 7 horas, o divide los minutos entre 100 en
     * vez de 60, esta prueba lo caza al céntimo.
     */
    public function test_el_cbi_de_requisitos_homologa_dias_horas_y_minutos(): void
    {
        $tramite = $this->tramiteNeutro();

        $this->requisito($tramite, [
            'dias_estimados'    => 1,
            'horas_estimadas'   => 1,
            'minutos_estimados' => 30,
        ]);

        $horasEsperadas = (1 * self::JORNADA) + 1 + (30 / 60); // 9.5

        $this->assertEqualsWithDelta(
            $horasEsperadas * self::SALARIO_HORA,
            $this->costosDe($tramite)['cbi_requisitos'],
            0.01,
            'CBI por requisitos = 9.5 h × $68.20 = $647.90'
        );
    }

    /*
     * ── DÓNDE ESTÁ LA PRUEBA DEL COSTO DE RESOLUCIÓN ──
     *
     * Aquí había una prueba llamada
     * test_el_cbi_de_resolucion_convierte_dias_habiles_a_naturales, que afirmaba:
     *
     *     28 días naturales × $68.20 × 8 horas = $15,276.80
     *
     * Esa prueba CONGELABA UN BUG. La fórmula "días × salario × jornada" no está en la
     * metodología: la metodología usa el COSTO DE OPORTUNIDAD, que en sus propios
     * ejemplos va de $0.07 al día (persona física) a $4.08 (persona moral). El error era
     * de entre 130 y 7,800 veces.
     *
     * Y la prueba estaba en verde. Pasaba perfectamente, certificando un cálculo
     * equivocado, porque se escribió mirando el CÓDIGO en vez del DOCUMENTO. Es el olor
     * clásico: congelar la implementación en lugar de la regla.
     *
     * El cálculo correcto vive ahora en CostoResolucionTest, y sus pruebas se escriben
     * contra los ejemplos numéricos de la metodología (Tablas 4, 5 y 9), no contra lo que
     * el código haga.
     *
     * Las pruebas de ESTE archivo usan trámites con plazo de resolución CERO
     * (ver tramiteNeutro), justo para que el costo de espera no se mezcle con lo que aquí
     * se mide: derechos, copias, requisitos y sus sumas.
     */

    // ═══════════════════════════════════════════════════════════════════════
    // 3. CBU y CBT (Ecuaciones 18 y 19)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CBU = CBD + CBI, y CBT = CBU × volumen anual.
     *
     * Los dos en la misma prueba porque el CBT depende del CBU: si se separaran y el
     * CBU estuviera mal, la del CBT también fallaría y no se sabría cuál es la culpable.
     * Así el mensaje de error señala directamente al eslabón roto.
     */
    public function test_el_cbu_suma_directo_mas_indirecto_y_el_cbt_lo_multiplica_por_el_volumen(): void
    {
        $tramite = $this->tramiteNeutro(['volumen_anual' => 1000]);

        $this->derecho($tramite, 200.00);                       // CBD = 200
        $this->requisito($tramite, ['horas_estimadas' => 1]);   // CBI = 68.20

        $costos = $this->costosDe($tramite);

        $cbuEsperado = 200.00 + self::SALARIO_HORA; // 268.20

        $this->assertEqualsWithDelta($cbuEsperado, $costos['cbu_unitario'], 0.01,
            'CBU = CBD + CBI');

        $this->assertEqualsWithDelta($cbuEsperado * 1000, $costos['cbt_total_anual'], 0.01,
            'CBT = CBU × volumen anual');
    }

    /**
     * Un trámite con volumen 0 (o sin volumen capturado) se cuenta como si se hiciera
     * UNA vez al año, no cero.
     *
     * Es deliberado: `max(1, ...)`. Si el volumen fuera cero, el CBT sería cero y el
     * trámite desaparecería de los indicadores por no haberse llenado un campo. Un
     * trámite que existe cuesta algo, aunque nadie sepa cuántas veces se hace.
     */
    public function test_un_tramite_sin_volumen_cuenta_como_uno(): void
    {
        $tramite = $this->tramiteNeutro(['volumen_anual' => 0]);
        $this->derecho($tramite, 500.00);

        $costos = $this->costosDe($tramite);

        $this->assertSame(1, $costos['volumen_anual']);
        $this->assertEqualsWithDelta(500.00, $costos['cbt_total_anual'], 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. La bandera de costos variables (la hermana que faltaba)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * La prueba anterior de este archivo solo comprobaba el caso POSITIVO:
     *
     *     $this->assertTrue((bool) $costos['tiene_costos_variables']);
     *
     * Si alguien dejara ese campo fijado a `true`, esa prueba pasaría igual — y el
     * sistema avisaría de "montos variables" en TODOS los trámites del municipio.
     *
     * Una aserción positiva sin su hermana negativa no protege de nada. Aquí van las dos.
     */
    public function test_un_tramite_sin_montos_variables_no_levanta_la_bandera(): void
    {
        $tramite = $this->tramiteNeutro();
        $this->derecho($tramite, 500.00); // fijo
        $this->requisito($tramite, ['tiene_costo' => true, 'costo_requisito' => 100]); // fijo

        $this->assertFalse(
            (bool) $this->costosDe($tramite)['tiene_costos_variables'],
            'No hay ningún monto variable: la bandera no debe levantarse.'
        );
    }

    public function test_un_derecho_variable_levanta_la_bandera(): void
    {
        $tramite = $this->tramiteNeutro();
        $this->derecho($tramite, 0, variable: true);

        $this->assertTrue((bool) $this->costosDe($tramite)['tiene_costos_variables']);
    }

    public function test_un_requisito_de_costo_variable_levanta_la_bandera(): void
    {
        $tramite = $this->tramiteNeutro();
        $this->requisito($tramite, ['tiene_costo' => true, 'costo_variable' => true]);

        $this->assertTrue(
            (bool) $this->costosDe($tramite)['tiene_costos_variables'],
            'Un requisito de costo de mercado (un plano, un peritaje) también debe avisar.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. Umbral e impacto
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Un umbral GENERAL: el que aplica a cualquier trámite que no tenga umbral propio
     * de su sector ni de su subsector. Es el último escalón de la cascada
     * (subsector → sector → general → null).
     *
     * Sin sector_id ni subsector_id: eso es lo que lo hace "general".
     *
     * `monto_base` + `unidad_base` son lo que capturó el usuario (por ejemplo, "500
     * UMA"); `monto_pesos` es esa misma cantidad ya convertida. El servicio solo mira
     * `monto_pesos` para clasificar el impacto, así que aquí se captura directamente en
     * pesos y las dos columnas coinciden.
     */
    private function umbralGeneral(float $montoPesos): UmbralConfigurado
    {
        return UmbralConfigurado::create([
            'sector_id'    => null,
            'subsector_id' => null,
            'monto_base'   => $montoPesos,
            'unidad_base'  => 'PESOS',
            'monto_pesos'  => $montoPesos,
            'anio'         => now()->year,
            'estatus'      => UmbralConfigurado::ESTATUS_ACTIVO,
        ]);
    }

    /**
     * Sin umbral aplicable, el impacto es "no determinado" — y NO revienta.
     *
     * clasificarImpacto() divide el CBT entre el monto del umbral. Si el umbral fuera
     * null o cero, eso sería una división por cero. La línea 167 del servicio lo
     * protege, y esta prueba lo deja fijado: nadie puede quitar esa guarda sin que se
     * ponga rojo.
     */
    public function test_sin_umbral_el_impacto_es_no_determinado_y_no_revienta(): void
    {
        $clasificado = $this->servicio->clasificarImpacto(1_000_000.00, null);

        $this->assertNull($clasificado['porcentaje']);
        $this->assertSame(TramiteCostoBurocratico::IMPACTO_NO_DETERMINADO, $clasificado['impacto']);
        $this->assertSame(TramiteCostoBurocratico::AIR_NO_DETERMINADO,     $clasificado['resultado_air']);
    }

    /** Un umbral con monto cero se trata igual que no tener umbral. */
    public function test_un_umbral_de_monto_cero_no_provoca_division_por_cero(): void
    {
        $umbral = $this->umbralGeneral(0);

        $clasificado = $this->servicio->clasificarImpacto(500.00, $umbral);

        $this->assertNull($clasificado['porcentaje']);
        $this->assertSame(TramiteCostoBurocratico::IMPACTO_NO_DETERMINADO, $clasificado['impacto']);
    }

    /**
     * Las cuatro franjas de impacto, probadas EN SUS BORDES.
     *
     * Los bordes son donde viven los errores. Un `<` que debía ser `<=` no se nota con
     * un 30% ni con un 200%: se nota exactamente en el 50, en el 100 y en el 150.
     *
     * Umbral de $1,000:
     *   CBT   499.99 →  49.99 %  → bajo
     *   CBT   500.00 →  50.00 %  → medio     (el borde: 50 ya NO es bajo)
     *   CBT   999.99 →  99.99 %  → medio
     *   CBT 1,000.00 → 100.00 %  → alto      (el borde: 100 ya NO es medio)
     *   CBT 1,499.99 → 149.99 %  → alto
     *   CBT 1,500.00 → 150.00 %  → crítico   (el borde: 150 ya NO es alto)
     */
    public function test_las_franjas_de_impacto_se_clasifican_en_sus_bordes(): void
    {
        $umbral = $this->umbralGeneral(1000.00);

        $esperados = [
            499.99  => TramiteCostoBurocratico::IMPACTO_BAJO,
            500.00  => TramiteCostoBurocratico::IMPACTO_MEDIO,
            999.99  => TramiteCostoBurocratico::IMPACTO_MEDIO,
            1000.00 => TramiteCostoBurocratico::IMPACTO_ALTO,
            1499.99 => TramiteCostoBurocratico::IMPACTO_ALTO,
            1500.00 => TramiteCostoBurocratico::IMPACTO_CRITICO,
            5000.00 => TramiteCostoBurocratico::IMPACTO_CRITICO,
        ];

        foreach ($esperados as $cbt => $impactoEsperado) {
            $this->assertSame(
                $impactoEsperado,
                $this->servicio->clasificarImpacto((float) $cbt, $umbral)['impacto'],
                "Un CBT de \${$cbt} contra un umbral de \$1,000 debe clasificarse como '{$impactoEsperado}'."
            );
        }
    }

    /**
     * A partir del 100% del umbral, el trámite PUEDE requerir AIR. Por debajo, no se
     * activa automáticamente.
     *
     * Es la consecuencia práctica de todo el cálculo: de este número depende que una
     * dependencia tenga que elaborar un Análisis de Impacto Regulatorio.
     */
    public function test_a_partir_del_cien_por_ciento_del_umbral_puede_requerirse_air(): void
    {
        $umbral = $this->umbralGeneral(1000.00);

        $this->assertSame(
            TramiteCostoBurocratico::AIR_NO_ACTIVA_AUTOMATICA,
            $this->servicio->clasificarImpacto(999.99, $umbral)['resultado_air'],
            'Por debajo del 100% del umbral el AIR no se activa solo.'
        );

        $this->assertSame(
            TramiteCostoBurocratico::AIR_PUEDE_REQUERIR_AIR,
            $this->servicio->clasificarImpacto(1000.00, $umbral)['resultado_air'],
            'Justo en el 100% ya puede requerirse AIR.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. El snapshot
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * recalcularYGuardar() hace tres cosas: calcula, guarda una foto del cálculo en
     * `tramite_costos_burocraticos`, y copia los totales a las columnas del trámite.
     *
     * Las tres tienen que cuadrar entre sí. Si el snapshot dice una cosa y la columna
     * del trámite dice otra, la ficha y el tablero mostrarían números distintos para
     * el mismo trámite, y nadie sabría cuál creer.
     */
    public function test_el_snapshot_guarda_los_mismos_numeros_que_las_columnas_del_tramite(): void
    {
        $tramite = $this->tramiteNeutro(['volumen_anual' => 10]);
        $this->derecho($tramite, 500.00);

        $snapshot = $this->servicio->recalcularYGuardar($tramite->fresh());
        $tramite  = $tramite->fresh();

        $this->assertEqualsWithDelta(500.00, (float) $snapshot->cbu_unitario,   0.01);
        $this->assertEqualsWithDelta(5000.00, (float) $snapshot->cbt_total_anual, 0.01);

        $this->assertEqualsWithDelta((float) $snapshot->cbu_unitario,    (float) $tramite->cbu_unitario, 0.01,
            'El snapshot y la columna del trámite deben decir lo mismo.');
        $this->assertEqualsWithDelta((float) $snapshot->cbt_total_anual, (float) $tramite->cbt_total,    0.01);
    }

    /**
     * ESCALABILIDAD — cada guardado deja un snapshot nuevo, aunque el cálculo no cambie.
     *
     * TramiteService llama a recalcularYGuardar() en CADA guardado del trámite. Un
     * enlace que guarda su trámite 20 veces mientras lo va llenando deja 20 filas
     * idénticas en `tramite_costos_burocraticos`.
     *
     * Esta prueba NO dice que esté mal. Fija el comportamiento actual, para que la
     * decisión —"¿guardamos foto siempre, o solo cuando el número cambia?"— se tome a
     * propósito y no por inercia.
     *
     * Si algún día se decide guardar solo los cambios, esta prueba se pondrá roja y
     * habrá que cambiarla: será la señal de que la decisión se tomó.
     */
    public function test_cada_recalculo_deja_un_snapshot_nuevo_aunque_nada_haya_cambiado(): void
    {
        $tramite = $this->tramiteNeutro();
        $this->derecho($tramite, 500.00);

        $this->servicio->recalcularYGuardar($tramite->fresh());
        $this->servicio->recalcularYGuardar($tramite->fresh());
        $this->servicio->recalcularYGuardar($tramite->fresh());

        $this->assertSame(
            3,
            TramiteCostoBurocratico::where('tramite_id', $tramite->id)->count(),
            'Hoy se guarda una foto por recálculo, aunque el resultado sea idéntico. '
            . 'Si esto cambia, es porque alguien lo decidió: actualiza la prueba.'
        );
    }

    /**
     * ultimoSnapshot() devuelve la foto más reciente, no la primera.
     *
     * Es lo que alimenta la ficha del trámite. Si devolviera la más antigua, el usuario
     * vería el costo que tenía el trámite el primer día que lo guardó.
     */
    public function test_el_ultimo_snapshot_es_el_mas_reciente(): void
    {
        $tramite = $this->tramiteNeutro();

        $this->derecho($tramite, 100.00);
        $this->servicio->recalcularYGuardar($tramite->fresh());

        $this->derecho($tramite, 400.00); // ahora el CBD es 500
        $this->servicio->recalcularYGuardar($tramite->fresh());

        $this->assertEqualsWithDelta(
            500.00,
            (float) $this->servicio->ultimoSnapshot($tramite)->cbu_unitario,
            0.01,
            'Debe devolver la foto nueva ($500), no la vieja ($100).'
        );
    }
}
