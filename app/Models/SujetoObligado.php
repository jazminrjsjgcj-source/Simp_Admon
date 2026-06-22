<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sujeto Obligado: la persona titular o responsable de una dependencia
 * (su director o cabeza).
 *
 * Cada dependencia tiene un sujeto obligado vigente (activo = true). En el
 * formulario de propuesta regulatoria se muestra automáticamente el titular
 * de la dependencia del usuario, reemplazando al antiguo "Enlace de
 * Simplificación".
 *
 * Se guarda en tabla separada para permitir historial de titulares: al
 * cambiar de titular se da de baja el anterior (activo = false) y de alta
 * el nuevo, conservando el registro previo.
 */
class SujetoObligado extends Model
{
    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde la migración de sujetos_obligados.
     */
    protected $fillable = [
        'dependencia_id',
        'nombre',
        'cargo',
        'activo',
    ];
    protected $table   = 'sujetos_obligados';

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * La dependencia a la que pertenece este sujeto obligado.
     */
    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class);
    }

    /**
     * Filtra solo los sujetos obligados vigentes (activos).
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Devuelve el sujeto obligado vigente de una dependencia, o null si
     * no tiene uno registrado. Útil para auto-rellenar el formulario.
     *
     * @param  int  $dependenciaId
     * @return self|null
     */
    public static function vigenteDe(int $dependenciaId): ?self
    {
        return static::where('dependencia_id', $dependenciaId)
            ->where('activo', true)
            ->first();
    }
}
