<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Valor en pesos de unidades como UMA, salario mínimo, UDI, por año.
 * Permite convertir umbrales y cálculos a distintas unidades para reportes.
 */
class UnidadValorReferencia extends Model
{
    protected $table   = 'unidades_valor_referencia';
    protected $guarded = ['id'];

    protected $casts = [
        'valor_pesos'     => 'decimal:4',
        'anio'            => 'integer',
        'activo'          => 'boolean',
        'vigencia_inicio' => 'date',
        'vigencia_fin'    => 'date',
    ];

    public const UMA            = 'UMA';
    public const SALARIO_MINIMO = 'salario_minimo';
    public const UDI            = 'UDI';

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
