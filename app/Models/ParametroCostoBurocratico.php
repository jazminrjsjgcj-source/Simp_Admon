<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parámetro configurable del cálculo de costo burocrático.
 * Ej: salario_hora, precio_copia, jornada_laboral, dias_por_mes, factor_dias_habiles.
 *
 * Si la tabla está vacía o no existe, el servicio cae a las constantes
 * definidas en el modelo Tramite.
 */
class ParametroCostoBurocratico extends Model
{
    protected $table   = 'parametros_costo_burocratico';
    protected $guarded = ['id'];

    protected $casts = [
        'valor'           => 'decimal:4',
        'activo'          => 'boolean',
        'vigencia_inicio' => 'date',
        'vigencia_fin'    => 'date',
    ];

    public const CLAVE_SALARIO_HORA       = 'salario_hora';
    public const CLAVE_PRECIO_COPIA       = 'precio_copia';
    public const CLAVE_JORNADA_LABORAL    = 'jornada_laboral';
    public const CLAVE_DIAS_POR_MES       = 'dias_por_mes';
    public const CLAVE_FACTOR_DIAS_HABILES = 'factor_dias_habiles';

    public function actualizadoPor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
