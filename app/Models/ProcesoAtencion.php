<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paso del flujo de un trámite o servicio.
 *
 * Cada registro representa un paso en el proceso de atención o resolución.
 * Fase 4 expande los campos para capturar un flujo estructurado completo:
 * actor, tipo enriquecido, duración, requisitos de entrada/salida, y
 * si el paso es digitalizable.
 *
 * El levantamiento completo (todos los pasos de un trámite) es la fuente
 * de verdad para generar el Mermaid AS-IS, y debe estar aprobado antes
 * de que el digitalizador pueda iniciar la reingeniería TO-BE.
 */
class ProcesoAtencion extends Model
{
    protected $table = 'proceso_atencion';

    protected $fillable = [
        'tramite_id',
        'tipo',           // atencion | resolucion
        'paso',
        'subpaso',
        'accion',
        'detalle',
        'area',
        // Fase 4: campos enriquecidos
        'tipo_paso',      // paso|decision|inspeccion|pago|resolutivo|notificacion|espera|firma|entrega
        'actor',          // quién ejecuta el paso
        'duracion_estimada',
        'es_digital',
        'entrada',        // qué necesita el paso para ejecutarse
        'salida',         // qué produce el paso
        'notas',
        'orden',
    ];

    protected $casts = [
        'es_digital' => 'boolean',
    ];

    // ── Tipos de paso enriquecidos ───────────────────────────────────────

    public const TIPO_PASO          = 'paso';
    public const TIPO_DECISION      = 'decision';
    public const TIPO_INSPECCION    = 'inspeccion';
    public const TIPO_PAGO          = 'pago';
    public const TIPO_RESOLUTIVO    = 'resolutivo';
    public const TIPO_NOTIFICACION  = 'notificacion';
    public const TIPO_ESPERA        = 'espera';
    public const TIPO_FIRMA         = 'firma';
    public const TIPO_ENTREGA       = 'entrega';

    public const TIPOS_PASO = [
        self::TIPO_PASO,
        self::TIPO_DECISION,
        self::TIPO_INSPECCION,
        self::TIPO_PAGO,
        self::TIPO_RESOLUTIVO,
        self::TIPO_NOTIFICACION,
        self::TIPO_ESPERA,
        self::TIPO_FIRMA,
        self::TIPO_ENTREGA,
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function tramite() { return $this->belongsTo(Tramite::class); }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function tipoPasoLegible(): string
    {
        return match ($this->tipo_paso) {
            self::TIPO_PASO         => 'Paso',
            self::TIPO_DECISION     => 'Decisión',
            self::TIPO_INSPECCION   => 'Inspección',
            self::TIPO_PAGO         => 'Pago',
            self::TIPO_RESOLUTIVO   => 'Resolutivo',
            self::TIPO_NOTIFICACION => 'Notificación',
            self::TIPO_ESPERA       => 'Espera',
            self::TIPO_FIRMA        => 'Firma',
            self::TIPO_ENTREGA      => 'Entrega',
            default => ucfirst($this->tipo_paso ?? 'paso'),
        };
    }

    public function iconoTipoPaso(): string
    {
        return match ($this->tipo_paso) {
            self::TIPO_DECISION     => 'ti-git-branch',
            self::TIPO_INSPECCION   => 'ti-eye-check',
            self::TIPO_PAGO         => 'ti-cash',
            self::TIPO_RESOLUTIVO   => 'ti-certificate',
            self::TIPO_NOTIFICACION => 'ti-bell',
            self::TIPO_ESPERA       => 'ti-clock',
            self::TIPO_FIRMA        => 'ti-pencil',
            self::TIPO_ENTREGA      => 'ti-package',
            default                 => 'ti-arrow-right',
        };
    }

    /**
     * Número legible del paso: "1", "2", "1.1", "1.2", etc.
     * Se calcula a partir del paso y subpaso.
     */
    public function numeroLegible(): string
    {
        if ($this->subpaso && $this->subpaso > 0) {
            return $this->paso . '.' . $this->subpaso;
        }
        return (string) $this->paso;
    }
}
