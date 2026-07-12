<?php

namespace App\Services;

use App\Models\ParametroActividadEconomica;
use App\Models\ParametroCostoBurocratico;
use App\Models\Tramite;
use App\Models\TramiteCostoBurocratico;
use App\Models\TramiteDerecho;
use App\Models\UmbralConfigurado;
use App\Models\UnidadValorReferencia;
use Illuminate\Support\Carbon;

/**
 * Servicio que concentra toda la lógica del Costo Burocrático.
 *
 * Responsabilidades:
 *   1. Cargar parámetros configurables (con fallback a constantes del modelo).
 *   2. Calcular CBD, CBI separados, CBU y CBT.
 *   3. Buscar el umbral aplicable según sector/subsector/vigencia.
 *   4. Clasificar el impacto del trámite.
 *   5. Determinar el resultado AIR.
 *   6. Guardar snapshot del cálculo en `tramite_costos_burocraticos`.
 *
 * Las constantes en el modelo Tramite siguen siendo el fallback cuando
 * la tabla de parámetros está vacía o aún no se ha corrido el seeder.
 */
class CostoBurocraticoService
{
    /** Umbrales de clasificación por impacto (porcentaje del umbral configurado). */
    public const IMPACTO_UMBRAL_MEDIO    = 50;
    public const IMPACTO_UMBRAL_ALTO     = 100;
    public const IMPACTO_UMBRAL_CRITICO  = 150;

    /**
     * Calcula todos los costos de un trámite y devuelve el desglose completo.
     * No persiste; solo calcula. Para guardar el snapshot, usar `recalcularYGuardar()`.
     *
     * @return array{
     *   monto_derechos: float, monto_copias: float, monto_requisitos: float,
     *   cbd_unitario: float,
     *   cbi_requisitos: float, cbi_resolucion: float, cbi_unitario: float,
     *   cbu_unitario: float, cbt_total_anual: float
     * }
     */
    public function calcularCostos(Tramite $tramite): array
    {
        $parametros = $this->cargarParametros();

        $cbd = $this->calcularCostoDirecto($tramite);
        $cbi = $this->calcularCostoIndirecto($tramite, $parametros);

        $cbuUnitario   = $cbd['cbd_unitario'] + $cbi['cbi_unitario'];
        $volumenAnual  = max(1, intval($tramite->volumen_anual ?? 1));
        $cbtTotalAnual = $cbuUnitario * $volumenAnual;

        // Ítem E: bandera para que el desglose muestre una nota cuando hay montos
        // no cuantificables (derechos variables del trámite o requisitos de costo
        // de mercado), que quedan fuera del CBD pero existen para la ciudadanía.
        if (!$tramite->relationLoaded('requisitos')) {
            $tramite->load('requisitos');
        }
        // Se comprueban los derechos DE VERDAD, no solo la bandera del trámite: esa
        // bandera la actualiza el formulario y puede quedar desincronizada si los
        // derechos se modifican por otra vía. Mirar los datos reales siempre acierta.
        if (! $tramite->relationLoaded('derechos')) {
            $tramite->load('derechos');
        }

        $tieneCostosVariables = (bool) (
            $tramite->monto_derechos_variable
            || $tramite->derechos->contains(fn ($d) => (bool) $d->es_variable)
            || $tramite->requisitos->contains(fn ($r) => (bool) $r->costo_variable)
        );

        return array_merge($cbd, $cbi, [
            'cbu_unitario'           => round($cbuUnitario,   2),
            'volumen_anual'          => $volumenAnual,
            'cbt_total_anual'        => round($cbtTotalAnual, 2),
            'tiene_costos_variables' => $tieneCostosVariables,
        ]);
    }

    /**
     * Calcula todo, busca umbral, clasifica impacto y guarda snapshot.
     * Actualiza también las columnas legacy en `tramites` para compatibilidad.
     */
    public function recalcularYGuardar(Tramite $tramite): TramiteCostoBurocratico
    {
        $costos       = $this->calcularCostos($tramite);
        $umbral       = $this->buscarUmbralAplicable($tramite);
        $clasificado  = $this->clasificarImpacto($costos['cbt_total_anual'], $umbral);

        $snapshot = TramiteCostoBurocratico::create([
            'tramite_id'         => $tramite->id,
            'monto_derechos'     => $tramite->monto_derechos     ?? 0,
            'numero_copias'      => $tramite->copias_cantidad    ?? 0,
            'precio_copia'       => $tramite->copias_precio      ?? 0,
            'monto_copias'       => $costos['monto_copias'],
            'monto_requisitos'   => $costos['monto_requisitos'],
            'cbd_unitario'       => $costos['cbd_unitario'],
            'cbi_requisitos'     => $costos['cbi_requisitos'],
            'cbi_resolucion'     => $costos['cbi_resolucion'],

            // Si el costo de espera no se pudo calcular, el cero de arriba es una LAGUNA,
            // no un hecho. Sin estas dos columnas, la ficha del trámite pintaría ese cero
            // igual que el de un trámite que de verdad se resuelve en el acto — y el
            // usuario leería "esperar no cuesta nada" cuando la verdad es "no lo sabemos".
            'resolucion_calculable' => $costos['resolucion_calculable'],
            'resolucion_motivo'     => $costos['resolucion_motivo'],
            'cbi_unitario'       => $costos['cbi_unitario'],
            'cbu_unitario'       => $costos['cbu_unitario'],
            'volumen_anual'      => $costos['volumen_anual'],
            'cbt_total_anual'    => $costos['cbt_total_anual'],
            'umbral_id'                   => $umbral?->id,
            'umbral_monto_pesos'          => $umbral?->monto_pesos,
            'umbral_monto_uma'            => $umbral?->monto_uma,
            'umbral_monto_salario_minimo' => $umbral?->monto_salario_minimo,
            'porcentaje_umbral'           => $clasificado['porcentaje'],
            'impacto'                     => $clasificado['impacto'],
            'resultado_air'               => $clasificado['resultado_air'],
            'calculado_en'                => now(),
        ]);

        // Actualizar columnas legacy + nuevas en `tramites`
        $tramite->update([
            'cbd_directo'                 => $costos['cbd_unitario'],
            'cbi_indirecto'               => $costos['cbi_unitario'],
            'cbi_requisitos'              => $costos['cbi_requisitos'],
            'cbi_resolucion'              => $costos['cbi_resolucion'],
            'monto_requisitos_con_costo'  => $costos['monto_requisitos'],
            'cbu_unitario'                => $costos['cbu_unitario'],
            'cbt_total'                   => $costos['cbt_total_anual'],
            'impacto'                     => $clasificado['impacto'],
            'resultado_air'               => $clasificado['resultado_air'],
        ]);

        return $snapshot;
    }

    /**
     * Busca el umbral aplicable al trámite según sector/subsector/vigencia.
     * Cascada: subsector → sector → general (sin sector/subsector) → null.
     */
    public function buscarUmbralAplicable(Tramite $tramite): ?UmbralConfigurado
    {
        $hoy = Carbon::today();

        $query = fn () => UmbralConfigurado::where('estatus', UmbralConfigurado::ESTATUS_ACTIVO)
            ->where(fn ($q) => $q->whereNull('vigencia_inicio')->orWhere('vigencia_inicio', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('vigencia_fin')->orWhere('vigencia_fin', '>=', $hoy));

        // 1. Por subsector
        if ($tramite->subsector_id) {
            $umbral = $query()->where('subsector_id', $tramite->subsector_id)->latest()->first();
            if ($umbral) return $umbral;
        }

        // 2. Por sector
        if ($tramite->sector_id) {
            $umbral = $query()->where('sector_id', $tramite->sector_id)
                ->whereNull('subsector_id')->latest()->first();
            if ($umbral) return $umbral;
        }

        // 3. Umbral general (sin sector ni subsector)
        return $query()->whereNull('sector_id')->whereNull('subsector_id')->latest()->first();
    }

    /**
     * Clasifica el impacto del trámite contra el umbral.
     *
     * @return array{porcentaje: ?float, impacto: string, resultado_air: string}
     */
    public function clasificarImpacto(float $cbtTotalAnual, ?UmbralConfigurado $umbral): array
    {
        if (!$umbral || empty($umbral->monto_pesos) || $umbral->monto_pesos <= 0) {
            return [
                'porcentaje'    => null,
                'impacto'       => TramiteCostoBurocratico::IMPACTO_NO_DETERMINADO,
                'resultado_air' => TramiteCostoBurocratico::AIR_NO_DETERMINADO,
            ];
        }

        $porcentaje = ($cbtTotalAnual / floatval($umbral->monto_pesos)) * 100;

        $impacto = match(true) {
            $porcentaje < self::IMPACTO_UMBRAL_MEDIO   => TramiteCostoBurocratico::IMPACTO_BAJO,
            $porcentaje < self::IMPACTO_UMBRAL_ALTO    => TramiteCostoBurocratico::IMPACTO_MEDIO,
            $porcentaje < self::IMPACTO_UMBRAL_CRITICO => TramiteCostoBurocratico::IMPACTO_ALTO,
            default                                    => TramiteCostoBurocratico::IMPACTO_CRITICO,
        };

        $resultadoAir = $porcentaje >= self::IMPACTO_UMBRAL_ALTO
            ? TramiteCostoBurocratico::AIR_PUEDE_REQUERIR_AIR
            : TramiteCostoBurocratico::AIR_NO_ACTIVA_AUTOMATICA;

        return [
            'porcentaje'    => round($porcentaje, 2),
            'impacto'       => $impacto,
            'resultado_air' => $resultadoAir,
        ];
    }

    /**
     * Devuelve el snapshot más reciente del cálculo del trámite, si existe.
     */
    public function ultimoSnapshot(Tramite $tramite): ?TramiteCostoBurocratico
    {
        return TramiteCostoBurocratico::where('tramite_id', $tramite->id)
            ->with('umbral')
            ->latest('calculado_en')
            ->first();
    }

    // ========== Internos ==========

    private function calcularCostoDirecto(Tramite $tramite): array
    {
        // Monto total de derechos (para referencia informativa).
        $montoDerechosTodos = floatval($tramite->monto_derechos ?? 0);

        // CBD: solo suma derechos que NO son variables (Art. 29 LNETB).
        // Los derechos variables (ej. predial) no son cuantificables de forma
        // fija, así que se excluyen del cálculo pero se registran como nota
        // informativa (monto_derechos_referencia + bandera tiene_costos_variables).
        if (!$tramite->relationLoaded('derechos')) {
            $tramite->load('derechos');
        }
        $valorUma = TramiteDerecho::valorUmaVigente();
        $montoDerechosFijos = $tramite->derechos
            ->filter(fn ($d) => !$d->es_variable)
            ->sum(fn ($d) => ($d->unidad ?? 'pesos') === 'UMA'
                ? floatval($d->monto) * $valorUma
                : floatval($d->monto));

        $numeroCopias    = intval($tramite->copias_cantidad     ?? 0);
        $precioCopia     = floatval($tramite->copias_precio     ?? 0);
        $montoCopias     = $numeroCopias * $precioCopia;
        $montoRequisitos = $this->sumarCostoRequisitos($tramite);

        // El CBD unitario solo incluye derechos fijos + copias + requisitos.
        $cbdUnitario = $montoDerechosFijos + $montoCopias + $montoRequisitos;

        return [
            'monto_derechos'       => round($montoDerechosTodos,  2),
            'monto_derechos_fijos' => round($montoDerechosFijos,  2),
            'monto_copias'         => round($montoCopias,         2),
            'monto_requisitos'     => round($montoRequisitos,     2),
            'cbd_unitario'         => round($cbdUnitario,         2),
        ];
    }

    private function calcularCostoIndirecto(Tramite $tramite, array $parametros): array
    {
        $cbiRequisitos = $this->sumarTiempoRequisitosComoCosto($tramite, $parametros);
        $resolucion    = $this->calcularCostoResolucion($tramite, $parametros);

        $cbiResolucion = $resolucion['costo'];

        return [
            'cbi_requisitos' => round($cbiRequisitos, 2),
            'cbi_resolucion' => round($cbiResolucion, 2),
            'cbi_unitario'   => round($cbiRequisitos + $cbiResolucion, 2),

            // Le dice a la ficha del trámite si el costo de resolución es de fiar.
            // Cuando es false, cbi_resolucion vale 0 porque NO SE PUDO CALCULAR —
            // no porque el trámite se resuelva gratis. La vista tiene que
            // distinguirlo, o estará mostrando un cero que miente.
            'resolucion_calculable'    => $resolucion['calculable'],
            'resolucion_motivo'        => $resolucion['motivo'],
            'costo_oportunidad_diario' => $resolucion['costo_oportunidad_diario'],
            'plazo_dias_naturales'     => $resolucion['dias_naturales'],
        ];
    }

    /**
     * Costo Burocrático indirecto por PLAZO DE RESOLUCIÓN.
     *
     * ══════════════════════════════════════════════════════════════════
     * QUÉ ESTABA MAL — el bug más caro que tenía el sistema
     * ══════════════════════════════════════════════════════════════════
     *
     * La versión anterior calculaba así:
     *
     *     return $dias * $parametros['salario_hora'] * $parametros['jornada_laboral'];
     *
     * Es decir: días × $68.20 × 8 = unos $545.60 POR CADA DÍA de espera.
     *
     * Eso no está en la metodología. La metodología no usa el salario para esto: usa el
     * COSTO DE OPORTUNIDAD, que es lo que la persona deja de ganar por no tener todavía
     * la resolución. Y en sus propios ejemplos, ese costo va de $0.07 al día (persona
     * física) a $4.08 al día (persona moral).
     *
     * La diferencia era de entre 130 y 7,800 veces.
     *
     * Y no se quedaba ahí: el costo de resolución domina el CBI, el CBI domina el CBU, y
     * el CBU multiplicado por el volumen da el CBT. De ese número dependen el porcentaje
     * del umbral, la clasificación de impacto y si el trámite REQUIERE UN AIR. Con la
     * fórmula vieja, casi cualquier trámite cruzaba el umbral.
     *
     * Además, el trámite ya guardaba `dirigido_a` y `etapa_operacion` —los dos campos que
     * la metodología necesita— y el cálculo los ignoraba por completo.
     *
     * ══════════════════════════════════════════════════════════════════
     * QUÉ HACE AHORA
     * ══════════════════════════════════════════════════════════════════
     *
     * Costo = costo de oportunidad DIARIO × plazo en días naturales
     *
     * Y el costo de oportunidad diario se calcula distinto según a quién va dirigido:
     *
     *   PERSONA FÍSICA (Ec. 6-8) — depende de la economía de la región:
     *       CO = (TasaLibreRiesgo / 365) × ((PIB / Población) / 365)
     *
     *   PERSONA MORAL (Ec. 9-13) — depende de la ACTIVIDAD ECONÓMICA y de la ETAPA:
     *       CO = TasaProductividadDiaria × CapitalDiarioPorEmpresa
     *
     *   AMBAS — se toma el MAYOR de los dos.
     *       Es una decisión, y conviene que quede escrita. La metodología no dice qué
     *       hacer cuando un trámite sirve a los dos públicos. Se elige el mayor por el
     *       mismo criterio que la propia metodología aplica al plazo: usa el plazo
     *       MÁXIMO "porque constituye el costo máximo asociado al tiempo de espera".
     *       Ante la duda, el sistema no subestima la carga que impone al ciudadano.
     *
     * ══════════════════════════════════════════════════════════════════
     * SI FALTAN LOS PARÁMETROS ECONÓMICOS
     * ══════════════════════════════════════════════════════════════════
     *
     * El costo sale CERO y se marca como NO CALCULABLE, con el motivo. No se inventa una
     * aproximación.
     *
     * Es la misma decisión que este servicio ya toma con el umbral: cuando no hay umbral
     * aplicable, el impacto es 'no_determinado', y no un porcentaje inventado.
     *
     * Y es la lección del bug que estamos arreglando: un número mal calculado es peor que
     * ningún número, porque nadie sabe que está mal. Un hueco visible se llena; un número
     * plausible se cree.
     *
     * @return array{costo: float, calculable: bool, motivo: ?string,
     *               costo_oportunidad_diario: ?float, dias_naturales: float}
     */
    private function calcularCostoResolucion(Tramite $tramite, array $parametros): array
    {
        $dias = $this->plazoEnDiasNaturales($tramite, $parametros);

        $sinCosto = fn (?string $motivo, ?float $co = null) => [
            'costo'                    => 0.0,
            'calculable'               => $motivo === null,
            'motivo'                   => $motivo,
            'costo_oportunidad_diario' => $co,
            'dias_naturales'           => $dias,
        ];

        // Un trámite que se resuelve en el acto no tiene costo de espera. Eso SÍ es un
        // cero de verdad, y por eso se marca como calculable.
        if ($dias <= 0) {
            return $sinCosto(null, 0.0);
        }

        $costoOportunidad = $this->costoOportunidadDiario($tramite);

        if ($costoOportunidad === null) {
            return $sinCosto(
                'No se pudo calcular el costo de espera: faltan parámetros económicos '
                . '(PIB, población y tasa libre de riesgo para personas físicas; datos de '
                . 'la actividad económica para personas morales).'
            );
        }

        return [
            'costo'                    => $costoOportunidad * $dias,
            'calculable'               => true,
            'motivo'                   => null,
            'costo_oportunidad_diario' => round($costoOportunidad, 6),
            'dias_naturales'           => $dias,
        ];
    }

    /**
     * Homologa el plazo de resolución a DÍAS NATURALES (metodología, Tabla 4).
     *
     * Esta parte ya estaba bien y no se toca:
     *
     *   días hábiles → × 1.4   (los 2 días inhábiles entre los 5 laborales: 2/5 = 0.4)
     *   meses        → × 365/12
     *   años         → × 365
     *
     * Se ha separado en su propia función porque antes vivía mezclada con el cálculo del
     * costo, y eso hacía que el error de la fórmula pasara desapercibido: la conversión
     * de días era correcta, así que todo el bloque "parecía" correcto.
     */
    private function plazoEnDiasNaturales(Tramite $tramite, array $parametros): float
    {
        $cantidad = intval($tramite->plazo_resolucion_cantidad ?? 0);
        $unidad   = $tramite->plazo_resolucion_unidad ?? 'habiles';

        return match($unidad) {
            'meses'   => $cantidad * $parametros['dias_por_mes'],
            'anios'   => $cantidad * 365,
            'habiles' => $cantidad * $parametros['factor_dias_habiles'],
            default   => (float) $cantidad, // 'naturales': ya vienen homologados
        };
    }

    /**
     * Costo de oportunidad diario del destinatario del trámite.
     * Devuelve null si faltan los datos para calcularlo.
     */
    private function costoOportunidadDiario(Tramite $tramite): ?float
    {
        $fisica = $this->costoOportunidadPersonaFisica();
        $moral  = $this->costoOportunidadPersonaMoral($tramite);

        return match($tramite->dirigido_a) {
            'fisica' => $fisica,
            'moral'  => $moral,

            // 'ambas' (o sin especificar): el mayor de los dos que se puedan calcular.
            // Ver el razonamiento en el docblock de calcularCostoResolucion().
            default  => $this->mayorDeLosConocidos($fisica, $moral),
        };
    }

    /** El mayor de dos valores que pueden ser null. Null si los dos lo son. */
    private function mayorDeLosConocidos(?float $a, ?float $b): ?float
    {
        $conocidos = array_filter([$a, $b], fn ($v) => $v !== null);

        return $conocidos === [] ? null : max($conocidos);
    }

    /**
     * Costo de oportunidad diario de una PERSONA FÍSICA (Ecuaciones 6 y 7).
     *
     *     PIB per cápita = PIB / Población
     *     CO_diario      = (TasaLibreRiesgo / 365) × (PIBpc / 365)
     *
     * La tasa libre de riesgo se guarda como decimal, no como porcentaje: un 9.5% anual
     * se captura como 0.095. Si se capturara como 9.5, el costo saldría cien veces mayor
     * — y no habría forma de notarlo mirando el resultado.
     */
    private function costoOportunidadPersonaFisica(): ?float
    {
        $economicos = $this->cargarParametrosEconomicos();

        $pib       = $economicos['pib'];
        $poblacion = $economicos['poblacion'];
        $tasa      = $economicos['tasa_libre_riesgo'];

        if ($pib === null || $poblacion === null || $tasa === null || $poblacion <= 0) {
            return null;
        }

        $pibPerCapita = $pib / $poblacion;

        return ($tasa / 365) * ($pibPerCapita / 365);
    }

    /**
     * Costo de oportunidad diario de una PERSONA MORAL (Ecuaciones 9 a 13).
     *
     * Depende de la actividad económica del trámite y de la etapa en que está la empresa.
     * Los datos se buscan igual que los umbrales: primero por subsector, luego por sector.
     * Si no hay ninguno, devuelve null.
     */
    private function costoOportunidadPersonaMoral(Tramite $tramite): ?float
    {
        $actividad = $this->buscarParametrosDeActividad($tramite);

        return $actividad?->costoOportunidadDiario($tramite->etapa_operacion);
    }

    /**
     * Busca los datos económicos de la actividad del trámite.
     *
     * Cascada subsector → sector → null, igual que buscarUmbralAplicable(). El subsector
     * es más específico, así que gana: si hay datos de "restaurantes", se usan esos antes
     * que los de "servicios de alojamiento y preparación de alimentos" entero.
     */
    private function buscarParametrosDeActividad(Tramite $tramite): ?ParametroActividadEconomica
    {
        if ($tramite->subsector_id) {
            $porSubsector = ParametroActividadEconomica::activos()
                ->where('subsector_id', $tramite->subsector_id)
                ->latest('anio')
                ->first();

            if ($porSubsector) {
                return $porSubsector;
            }
        }

        if ($tramite->sector_id) {
            return ParametroActividadEconomica::activos()
                ->where('sector_id', $tramite->sector_id)
                ->whereNull('subsector_id')
                ->latest('anio')
                ->first();
        }

        return null;
    }

    /**
     * Carga PIB, población y tasa libre de riesgo.
     *
     * A diferencia de cargarParametros(), aquí NO hay valores por defecto: si el dato no
     * está, devuelve null. Es deliberado.
     *
     * Los parámetros de cargarParametros() (salario, jornada, factor de días hábiles) son
     * convenciones: un valor por defecto razonable es mejor que nada. Estos NO: el PIB de
     * un municipio no se puede "estimar por defecto". Inventar uno sería repetir el error
     * que este arreglo viene a corregir.
     *
     * @return array{pib: ?float, poblacion: ?float, tasa_libre_riesgo: ?float}
     */
    private function cargarParametrosEconomicos(): array
    {
        try {
            $registros = ParametroCostoBurocratico::activos()
                ->pluck('valor', 'clave')
                ->toArray();
        } catch (\Throwable $e) {
            $registros = [];
        }

        $leer = fn (string $clave) => isset($registros[$clave]) ? floatval($registros[$clave]) : null;

        return [
            'pib'               => $leer(ParametroCostoBurocratico::CLAVE_PIB),
            'poblacion'         => $leer(ParametroCostoBurocratico::CLAVE_POBLACION),
            'tasa_libre_riesgo' => $leer(ParametroCostoBurocratico::CLAVE_TASA_LIBRE_RIESGO),
        ];
    }

    /**
     * Suma los montos que la persona usuaria paga por cumplir los requisitos.
     * Es el término MRT de la Ecuación 4 (CBD = MD + MCS + MRT).
     *
     * ── Qué estaba mal ──
     *
     * Esta función sumaba `costo_requisito` tal cual, sin mirar en qué UNIDAD se había
     * capturado. Pero el formulario permite capturar el costo de un requisito en UMA, y
     * sincronizarRequisitos() guarda esa unidad en la columna `costo_unidad`
     * ('PESOS' o 'UMA').
     *
     * Resultado: un requisito de 5 UMA (unos $565) sumaba $5.
     *
     * Los DERECHOS sí convertían — calcularCostoDirecto() usa valorUmaVigente(). Los
     * REQUISITOS no. Era una asimetría, no una decisión: los dos son montos en pesos
     * dentro de la misma ecuación.
     *
     * Y el error no se quedaba ahí: se arrastraba al CBD, de ahí al CBU, al CBT, al
     * porcentaje del umbral, a la clasificación de impacto y al resultado AIR. Es decir,
     * podía hacer que un trámite que debía requerir un Análisis de Impacto Regulatorio
     * no lo requiriera. En silencio, porque ninguna prueba tocaba un solo número.
     *
     * ── Por qué un null se trata como PESOS y no como UMA ──
     *
     * La columna `costo_unidad` se añadió cuando ya había requisitos guardados
     * (migración add_costo_unidad_to_requisitos), así que los antiguos la tienen en null.
     *
     * Un null tiene que significar PESOS, nunca UMA. Si se interpretara al revés, un
     * requisito histórico de 300 pesos pasaría a valer 300 UMA —unos $33,900— de la noche
     * a la mañana, y nadie sabría de dónde salió esa cifra.
     *
     * La regla general: cuando hay que elegir un valor por defecto entre dos, elige el que
     * NO multiplica.
     */
    private function sumarCostoRequisitos(Tramite $tramite): float
    {
        if (!$tramite->relationLoaded('requisitos')) {
            $tramite->load('requisitos');
        }

        $valorUma = TramiteDerecho::valorUmaVigente();

        return $tramite->requisitos->sum(function ($r) use ($valorUma) {

            // Ítem E: un requisito de costo VARIABLE (un plano arquitectónico, un
            // peritaje) no suma al costo directo, porque su monto no es cuantificable de
            // forma objetiva. Su TIEMPO sí cuenta en el CBI: ver sumarTiempoRequisitosComoCosto().
            if (! $r->tiene_costo || $r->costo_variable) {
                return 0.0;
            }

            $monto = floatval($r->costo_requisito ?? 0);

            $enUma = strtoupper((string) ($r->costo_unidad ?? 'PESOS')) === 'UMA';

            return $enUma ? $monto * $valorUma : $monto;
        });
    }

    private function sumarTiempoRequisitosComoCosto(Tramite $tramite, array $parametros): float
    {
        if (!$tramite->relationLoaded('requisitos')) {
            $tramite->load('requisitos');
        }

        $salarioHora = $parametros['salario_hora'];

        return $tramite->requisitos->sum(function ($r) use ($parametros, $salarioHora) {
            $dias    = intval($r->dias_estimados  ?? 0);
            $horas   = intval($r->horas_estimadas ?? 0);
            $minutos = intval($r->minutos_estimados ?? 0);

            $tiempoEnHoras = ($dias * $parametros['jornada_laboral'])
                           + $horas
                           + ($minutos / 60);

            return $tiempoEnHoras * $salarioHora;
        });
    }

    /**
     * Carga los parámetros activos desde DB; cae a constantes del modelo
     * si la tabla está vacía o no tiene una clave concreta.
     */
    private function cargarParametros(): array
    {
        try {
            $registros = ParametroCostoBurocratico::activos()
                ->pluck('valor', 'clave')
                ->toArray();
        } catch (\Throwable $e) {
            $registros = [];
        }

        return [
            'salario_hora'        => floatval($registros[ParametroCostoBurocratico::CLAVE_SALARIO_HORA]
                                        ?? Tramite::SALARIO_HORA_DEFAULT),
            'precio_copia'        => floatval($registros[ParametroCostoBurocratico::CLAVE_PRECIO_COPIA]
                                        ?? Tramite::COSTO_COPIA_DEFAULT),
            'jornada_laboral'     => floatval($registros[ParametroCostoBurocratico::CLAVE_JORNADA_LABORAL]
                                        ?? Tramite::HORAS_JORNADA_LABORAL),
            'dias_por_mes'        => floatval($registros[ParametroCostoBurocratico::CLAVE_DIAS_POR_MES]
                                        ?? Tramite::DIAS_PROMEDIO_MES),
            'factor_dias_habiles' => floatval($registros[ParametroCostoBurocratico::CLAVE_FACTOR_DIAS_HABILES]
                                        ?? Tramite::FACTOR_DIAS_HABILES),
        ];
    }
}
