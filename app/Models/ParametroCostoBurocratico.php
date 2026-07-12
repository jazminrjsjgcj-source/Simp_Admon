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

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * actualizado_por lo asigna el controlador al guardar.
     */
    protected $fillable = [
        'clave',
        'valor',
        'unidad',
        'fuente',
        'vigencia_inicio',
        'vigencia_fin',
        'activo',
        'actualizado_por',
    ];

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

    /**
     * ── Parámetros económicos de la región ──
     *
     * Sirven para calcular el COSTO DE OPORTUNIDAD de una persona física: lo que deja de
     * ganar por cada día que espera la resolución de un trámite (Ecuaciones 6 y 7 de la
     * metodología).
     *
     *     PIB per cápita = PIB / Población
     *     Costo diario   = (TasaLibreRiesgo / 365) × (PIBpc / 365)
     *
     * OJO CON LA TASA. Se guarda como DECIMAL, no como porcentaje: un 9.5% anual se
     * captura como 0.095, NO como 9.5.
     *
     * Si se capturara como 9.5, el costo saldría cien veces mayor — y no habría forma de
     * darse cuenta mirando el resultado, porque un número grande parece tan plausible
     * como uno pequeño. Por eso la unidad de esta clave debe registrarse como 'decimal'
     * y la fuente debe decir de dónde salió (Banxico, CETES a 28 días, etc.).
     *
     * A diferencia del resto de claves de este modelo, estas TRES NO TIENEN VALOR POR
     * DEFECTO. El salario o la jornada laboral admiten una convención razonable; el PIB
     * de un municipio, no. Si no están cargadas, el servicio marca el costo de resolución
     * como "no calculable" en vez de inventar una cifra.
     */
    public const CLAVE_PIB               = 'pib';
    public const CLAVE_POBLACION         = 'poblacion';
    public const CLAVE_TASA_LIBRE_RIESGO = 'tasa_libre_riesgo';

    public function actualizadoPor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
