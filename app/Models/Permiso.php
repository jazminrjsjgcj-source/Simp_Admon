<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table   = 'permisos';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     */
    protected $fillable = [
        'codigo',
        'modulo',
        'accion',
        'descripcion',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permiso')->withTimestamps();
    }
}
