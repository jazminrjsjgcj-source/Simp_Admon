<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Umbral configurado por sector/subsector para clasificar el impacto
 * de un trámite. Define el monto en pesos contra el cual se compara
 * el Costo Burocrático Total Anual (CBT).
 *
 * Búsqueda del umbral aplicable:
 *   1. Activo del subsector del trámite
 *   2. Activo del sector del trámite
 *   3. Activo sin sector ni subsector (umbral general)
 *   4. Si nada coincide → impacto 'no_determinado'
 */
class UmbralConfigurado extends Model
{
    protected $table   = 'umbrales_configurados';
    protected $guarded = ['id'];

    public const ESTATUS_ACTIVO   = 'activo';
    public const ESTATUS_INACTIVO = 'inactivo';

    protected $casts = [
        'monto_base'           => 'decimal:4',
        'monto_pesos'          => 'decimal:4',
        'monto_uma'            => 'decimal:4',
        'monto_salario_minimo' => 'decimal:4',
        'monto_udis'           => 'decimal:4',
        'anio'                 => 'integer',
        'fecha_fuente'         => 'date',
        'fecha_carga'          => 'date',
        'vigencia_inicio'      => 'date',
        'vigencia_fin'         => 'date',
    ];

    public function sector()       { return $this->belongsTo(SectorScian::class, 'sector_id'); }
    public function subsector()    { return $this->belongsTo(SubsectorScian::class, 'subsector_id'); }
    public function cargadoPor()   { return $this->belongsTo(User::class, 'cargado_por'); }

    public function estaActivo(): bool
    {
        return $this->estatus === self::ESTATUS_ACTIVO;
    }
}
