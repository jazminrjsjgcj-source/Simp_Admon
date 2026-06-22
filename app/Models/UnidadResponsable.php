<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Unidad Responsable del H. Ayuntamiento de La Paz.
 *
 * Código jerárquico de 14 dígitos. Los primeros 2 indican el poder:
 *   01 = Ejecutivo
 *   02 = Sindicatura
 *   03 = Regidurías
 *   04 = DIF
 *   05 = IMPLAN
 *   06 = Instituto Municipal de la Mujer
 *
 * El nivel se infiere de qué tantas posiciones tienen ceros al final:
 *   ...000000 (10 ceros)  → Dirección General (nivel 2)
 *   ...0000   ( 4 ceros)  → Subdirección o departamento
 *   etc.
 */
class UnidadResponsable extends Model
{
    protected $table   = 'unidades_responsables';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * Reconstruido desde la migración create_unidades_responsables.
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'poder',
        'nivel',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'poder'  => 'integer',
    ];

    public const PODER_EJECUTIVO     = 1;
    public const PODER_SINDICATURA   = 2;
    public const PODER_REGIDURIAS    = 3;
    public const PODER_DIF           = 4;
    public const PODER_IMPLAN        = 5;
    public const PODER_INST_MUJER    = 6;

    public function tramites()
    {
        return $this->hasMany(Tramite::class, 'unidad_responsable_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDelPoder($query, int $poder)
    {
        return $query->where('poder', $poder);
    }

    /**
     * Devuelve un identificador de la UR para mostrar al usuario:
     * código abreviado + nombre.
     */
    public function etiqueta(): string
    {
        return "{$this->codigo} — {$this->nombre}";
    }

    public function poderLegible(): string
    {
        return match($this->poder) {
            self::PODER_EJECUTIVO   => 'Ejecutivo',
            self::PODER_SINDICATURA => 'Sindicatura',
            self::PODER_REGIDURIAS  => 'Regidurías',
            self::PODER_DIF         => 'DIF Municipal',
            self::PODER_IMPLAN      => 'IMPLAN',
            self::PODER_INST_MUJER  => 'Instituto Municipal de la Mujer',
            default                 => 'Otro',
        };
    }
}
