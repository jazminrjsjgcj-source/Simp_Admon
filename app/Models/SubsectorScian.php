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
    protected $guarded  = ['id'];
    public    $timestamps = false;

    public function sector()
    {
        return $this->belongsTo(SectorScian::class, 'sector_id');
    }
}
