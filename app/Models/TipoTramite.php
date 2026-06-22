<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de trámite.
 *
 * Clasifica los trámites por naturaleza jurídica:
 * Licencia, Permiso, Registro, Certificado, etc.
 */
class TipoTramite extends Model
{
    protected $table   = 'tipos_tramite';

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

    public function tramites()
    {
        return $this->hasMany(Tramite::class, 'tipo_tramite_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
