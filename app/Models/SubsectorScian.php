<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Subsector económico SCIAN. Pertenece a un sector y agrupa
 * actividades económicas más específicas (3 dígitos).
 */
class SubsectorScian extends Model
{
    protected $table    = 'subsectores_scian';

    /**
     * Columnas asignables en masa. Esta tabla no usa timestamps
     * ($timestamps = false). Reconstruido desde create_tramites_tables.
     */
    protected $fillable = [
        'sector_id',
        'codigo',
        'nombre',
    ];
    public    $timestamps = false;

    public function sector()
    {
        return $this->belongsTo(SectorScian::class, 'sector_id');
    }
}
