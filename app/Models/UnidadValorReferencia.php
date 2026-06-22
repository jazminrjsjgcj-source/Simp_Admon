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

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * actualizado_por SÍ va aquí: UnidadValorController lo asigna en
     * create() y update(). Reconstruido desde las migraciones de la tabla.
     */
    protected $fillable = [
        'unidad',
        'valor_pesos',
        'anio',
        'vigencia_inicio',
        'vigencia_fin',
        'fuente',
        'activo',
        'actualizado_por',
    ];

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
