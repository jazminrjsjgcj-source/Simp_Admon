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
    protected $guarded = ['id'];

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
