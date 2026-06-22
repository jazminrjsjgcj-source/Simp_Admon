<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sector económico según el Sistema de Clasificación Industrial
 * de América del Norte (SCIAN México 2018).
 *
 * El catálogo oficial tiene 20 sectores (códigos 11 al 81),
 * agrupados en 3 grandes grupos:
 *   - Primarias (11)
 *   - Secundarias (21-33)
 *   - Terciarias (43-81)
 */
class SectorScian extends Model
{
    protected $table    = 'sectores_scian';

    /**
     * Columnas asignables en masa. Esta tabla no usa timestamps
     * ($timestamps = false). Reconstruido desde create_tramites_tables.
     */
    protected $fillable = [
        'codigo',
        'nombre',
    ];
    public    $timestamps = false;

    public function subsectores()
    {
        return $this->hasMany(SubsectorScian::class, 'sector_id');
    }

    public function tramites()
    {
        return $this->hasMany(Tramite::class, 'sector_id');
    }
}
