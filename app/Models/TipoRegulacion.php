<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de regulación.
 *
 * Alimenta los selects de propuestas regulatorias y del módulo
 * de regulaciones. Admite soft-toggle (activo) para no perder
 * registros históricos asociados.
 */
class TipoRegulacion extends Model
{
    protected $table   = 'tipos_regulacion';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde la migración create_tipos_catalogs.
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function propuestas()
    {
        return $this->hasMany(PropuestaRegulatoria::class, 'tipo_regulacion', 'nombre');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
