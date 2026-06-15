<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Observación polimórfica sobre un registro (trámite, acción de agenda,
 * propuesta regulatoria). Marca una sección específica y un destinatario
 * (la persona que debe atender la observación).
 */
class Observacion extends Model
{
    protected $table   = 'observaciones';
    protected $guarded = ['id'];

    /** Estatus rico de una observación (corrección #18). */
    public const ESTATUS_PENDIENTE   = 'pendiente';
    public const ESTATUS_EN_ATENCION = 'en_atencion';
    public const ESTATUS_ATENDIDA    = 'atendida';
    public const ESTATUS_REABIERTA   = 'reabierta';
    public const ESTATUS_VALIDADA    = 'validada';

    /**
     * Estados en que una observación sigue "viva" (sin cerrar): la revisora
     * aún no la ha validado. 'validada' es el cierre, por eso no está aquí.
     * 'atendida' SÍ cuenta como viva: el enlace ya corrigió, pero la revisora
     * debe dar el visto bueno final. Fuente de verdad única para todo el
     * sistema (scope pendientes, Tramite, config/flujos.php).
     */
    public const ESTATUS_VIVOS = [
        self::ESTATUS_PENDIENTE,
        self::ESTATUS_EN_ATENCION,
        self::ESTATUS_ATENDIDA,
        self::ESTATUS_REABIERTA,
    ];

    /** Etiquetas legibles de cada estatus. */
    public const ETIQUETAS_ESTATUS = [
        self::ESTATUS_PENDIENTE   => 'Pendiente',
        self::ESTATUS_EN_ATENCION => 'En atención',
        self::ESTATUS_ATENDIDA    => 'Atendida',
        self::ESTATUS_REABIERTA   => 'Reabierta',
        self::ESTATUS_VALIDADA    => 'Validada',
    ];

    protected $casts = [
        'atendida' => 'boolean',
    ];

    // ========== Relaciones ==========

    public function observable()    { return $this->morphTo(); }
    public function realizadaPor()  { return $this->belongsTo(User::class, 'realizada_por'); }
    public function destinatario()  { return $this->belongsTo(User::class, 'destinatario_id'); }

    // ========== Scopes ==========

    public function scopePendientes($query) { return $query->whereIn('estatus', self::ESTATUS_VIVOS); }
    public function scopeAtendidas($query)   { return $query->whereNotIn('estatus', self::ESTATUS_VIVOS); }

    /** Observaciones dirigidas a un usuario específico. */
    public function scopeParaUsuario($query, int $userId)
    {
        return $query->where('destinatario_id', $userId);
    }

    /** Observaciones de una sección específica. */
    public function scopeDeSeccion($query, string $seccion)
    {
        return $query->where('seccion', $seccion);
    }

    /** Observaciones ligadas a un campo específico. */
    public function scopeDeCampo($query, string $campo)
    {
        return $query->where('campo', $campo);
    }

    /** Observaciones con un estatus dado. */
    public function scopeConEstatus($query, string $estatus)
    {
        return $query->where('estatus', $estatus);
    }

    // ========== Helpers ==========

    /**
     * Indica si la observación ya fue resuelta (atendida o validada).
     */
    public function estaResuelta(): bool
    {
        return in_array($this->estatus, [self::ESTATUS_ATENDIDA, self::ESTATUS_VALIDADA], true);
    }

    /** Etiqueta legible del estatus de esta observación. */
    public function estatusLegible(): string
    {
        return self::ETIQUETAS_ESTATUS[$this->estatus] ?? ucfirst(str_replace('_', ' ', $this->estatus ?? 'pendiente'));
    }
}
