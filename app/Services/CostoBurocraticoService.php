<?php

namespace App\Services;

use App\Models\ParametroCostoBurocratico;
use App\Models\Tramite;
use App\Models\TramiteCostoBurocratico;
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
        $valorUma = \App\Models\TramiteDerecho::valorUmaVigente();
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
        $cbiResolucion = $this->calcularTiempoResolucionComoCosto($tramite, $parametros);

        return [
            'cbi_requisitos' => round($cbiRequisitos, 2),
            'cbi_resolucion' => round($cbiResolucion, 2),
            'cbi_unitario'   => round($cbiRequisitos + $cbiResolucion, 2),
        ];
    }

    private function sumarCostoRequisitos(Tramite $tramite): float
    {
        if (!$tramite->relationLoaded('requisitos')) {
            $tramite->load('requisitos');
        }

        // Ítem E: un requisito con costo variable (ej. plano arquitectónico) NO
        // suma al costo directo, porque su monto no es cuantificable de forma
        // objetiva. Su TIEMPO sí cuenta en el CBI (ver sumarTiempoRequisitos...).
        return $tramite->requisitos->sum(fn ($r) =>
            ($r->tiene_costo && !$r->costo_variable) ? floatval($r->costo_requisito ?? 0) : 0
        );
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

    private function calcularTiempoResolucionComoCosto(Tramite $tramite, array $parametros): float
    {
        $cantidad = intval($tramite->plazo_resolucion_cantidad ?? 0);
        $unidad   = $tramite->plazo_resolucion_unidad ?? 'habiles';

        $dias = match($unidad) {
            'meses'   => $cantidad * $parametros['dias_por_mes'],
            'anios'   => $cantidad * 365,
            'habiles' => $cantidad * $parametros['factor_dias_habiles'],
            default   => (float) $cantidad,
        };

        return $dias * $parametros['salario_hora'] * $parametros['jornada_laboral'];
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
