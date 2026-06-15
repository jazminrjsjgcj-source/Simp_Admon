<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table   = 'roles';
    protected $guarded = ['id'];

    protected $casts = [
        'sistema' => 'boolean',
    ];

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'role_permiso')->withTimestamps();
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'user_role')->withTimestamps();
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->permisos->contains('codigo', $codigo);
    }

    public function esDeSistema(): bool
    {
        return (bool) $this->sistema;
    }
}
