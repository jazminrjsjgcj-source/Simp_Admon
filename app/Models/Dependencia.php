<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dependencia del Ayuntamiento.
 *
 * Desde la migración `dependencia_unificada`, el catálogo activo contiene
 * una sola dependencia "H. Ayuntamiento de La Paz" (código '000') y la
 * granularidad orgánica vive en el catálogo de Unidades Responsables.
 *
 * Las dependencias anteriores quedan en estado `activo = false` para
 * preservar trámites históricos sin romper FK.
 */
class Dependencia extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'dependencias';

    protected $casts = [
        'activo' => 'boolean',
    ];

    public const CODIGO_AYUNTAMIENTO_UNIFICADO = '000';

    public function unidades() { return $this->hasMany(UnidadAdministrativa::class); }
    public function tramites() { return $this->hasMany(Tramite::class); }
    public function users()    { return $this->hasMany(User::class); }

    /** Todos los sujetos obligados de la dependencia (incluye históricos). */
    public function sujetosObligados() { return $this->hasMany(SujetoObligado::class); }

    /** El sujeto obligado vigente (titular activo) de la dependencia. */
    public function sujetoObligado()
    {
        return $this->hasOne(SujetoObligado::class)->where('activo', true);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Devuelve la dependencia unificada del Ayuntamiento.
     * Se cachea en memoria para evitar múltiples queries.
     */
    public static function ayuntamientoUnificado(): ?self
    {
        static $cache = null;
        if ($cache === null) {
            $cache = static::where('codigo', self::CODIGO_AYUNTAMIENTO_UNIFICADO)->first();
        }
        return $cache;
    }
}
