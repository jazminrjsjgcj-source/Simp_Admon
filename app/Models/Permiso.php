<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table   = 'permisos';
    protected $guarded = ['id'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permiso')->withTimestamps();
    }
}
