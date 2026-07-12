<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot del cálculo de costo burocrático de un trámite.
 *
 * Cada cálculo se guarda como una fila para tener trazabilidad:
 *   - Qué umbral se usó
 *   - Cuánto valía la UMA / salario mínimo en ese momento
 *   - Qué resultado AIR arrojó
 *
 * El cálculo "vigente" es el último (mayor created_at) para el trámite.
 */
class TramiteCostoBurocratico extends Model
{
    protected $table   = 'tramite_costos_burocraticos';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Snapshot de costos
     * calculados de un trámite. Reconstruido desde su migración.
     */
    protected $fillable = [
        'tramite_id',
        'monto_derechos',
        'numero_copias',
        'precio_copia',
        'monto_copias',
        'monto_requisitos',
        'cbd_unitario',
        'cbi_requisitos',
        'cbi_resolucion',

        // ¿El costo de espera de arriba es de fiar? Cuando es false, cbi_resolucion vale
        // cero porque NO SE PUDO CALCULAR (faltan parámetros económicos), no porque el
        // trámite se resuelva al instante. La ficha tiene que distinguirlo.
        'resolucion_calculable',
        'resolucion_motivo',
        'cbi_unitario',
        'cbu_unitario',
        'volumen_anual',
        'cbt_total_anual',
        'umbral_id',
        'umbral_monto_pesos',
        'umbral_monto_uma',
        'umbral_monto_salario_minimo',
        'porcentaje_umbral',
        'impacto',
        'resultado_air',
        'calculado_en',
    ];

    public const IMPACTO_NO_DETERMINADO = 'no_determinado';
    public const IMPACTO_BAJO           = 'bajo';
    public const IMPACTO_MEDIO          = 'medio';
    public const IMPACTO_ALTO           = 'alto';
    public const IMPACTO_CRITICO        = 'critico';

    public const AIR_NO_DETERMINADO         = 'no_determinado';
    public const AIR_NO_ACTIVA_AUTOMATICA   = 'no_activa_automaticamente';
    public const AIR_PUEDE_REQUERIR_AIR     = 'puede_requerir_air';

    protected $casts = [
        'monto_derechos'              => 'decimal:2',
        'precio_copia'                => 'decimal:2',
        'monto_copias'                => 'decimal:2',
        'monto_requisitos'            => 'decimal:2',
        'cbd_unitario'                => 'decimal:2',
        'cbi_requisitos'              => 'decimal:2',
        'cbi_resolucion'              => 'decimal:2',
        'resolucion_calculable'       => 'boolean',
        'cbi_unitario'                => 'decimal:2',
        'cbu_unitario'                => 'decimal:2',
        'cbt_total_anual'             => 'decimal:2',
        'umbral_monto_pesos'          => 'decimal:2',
        'umbral_monto_uma'            => 'decimal:4',
        'umbral_monto_salario_minimo' => 'decimal:4',
        'porcentaje_umbral'           => 'decimal:2',
        'calculado_en'                => 'datetime',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }
    public function umbral()  { return $this->belongsTo(UmbralConfigurado::class, 'umbral_id'); }
}
