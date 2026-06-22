<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Exención del AIR — Art. 36 LNETB.
 *
 * Cuando una propuesta regulatoria aplica a una de las 8 fracciones
 * del Art. 36, el enlace puede solicitar exención del AIR.
 */
class ExencionAir extends Model
{
    protected $table   = 'exenciones_air';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     */
    protected $fillable = [
        'propuesta_id',
        'supuesto',
        'fracciones',
        'justificacion',
        'costos_estimados',
        'estatus',
        'created_by',
    ];

    protected $casts = [
        'fracciones' => 'array',
    ];

    public const FRACCIONES_ART36 = [
        1 => 'Decretos del Ejecutivo o iniciativas de ley',
        2 => 'Seguridad nacional, fiscal o servicios públicos',
        3 => 'Emergencias (salud, ambiente, economía)',
        4 => 'Actos imperativos del Estado (expropiaciones)',
        5 => 'Derivadas de tratados internacionales',
        6 => 'Adquisiciones, arrendamientos y obra pública',
        7 => 'Situación jurídica concreta de particular',
        8 => 'No modifica obligaciones ni agrega costos burocráticos',
    ];

    public const ESTATUS_SOLICITADA = 'solicitada';
    public const ESTATUS_APROBADA   = 'aprobada';
    public const ESTATUS_RECHAZADA  = 'rechazada';

    // ─── Relaciones ───────────────────────────────────────────────

    public function propuesta()
    {
        return $this->belongsTo(PropuestaRegulatoria::class, 'propuesta_id');
    }

    public function creadaPor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function fraccionesTexto(): string
    {
        if (empty($this->fracciones)) {
            return $this->supuesto ?? '—';
        }

        return implode('; ', array_map(
            fn ($num) => "Fracc. {$num}: " . (self::FRACCIONES_ART36[$num] ?? '?'),
            $this->fracciones
        ));
    }

    public function estadoBadge(): string
    {
        return match ($this->estatus) {
            self::ESTATUS_APROBADA  => 'success-b',
            self::ESTATUS_RECHAZADA => 'danger-b',
            default                  => 'warning-b',
        };
    }
}
