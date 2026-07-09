<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reingeniería TO-BE de un trámite o servicio.
 *
 * Cada reingeniería es una versión del flujo propuesto después de
 * simplificar o digitalizar. Puede nacer de una Agenda de Digitalización
 * o de una solicitud directa justificada.
 *
 * La reingeniería se firma por el Enlace y el Sujeto Obligado usando el
 * sistema polimórfico de Firmas existente (firmable_type = Reingenieria).
 * Una vez firmada, se bloquea — cualquier cambio crea una nueva versión.
 */
class Reingenieria extends Model
{
    use SoftDeletes;

    protected $table = 'reingenierias';

    protected $fillable = [
        'tramite_id',
        'agenda_accion_id',
        'origen',
        'version',
        'estado',
        'motivo_directa',
        'justificacion',
        'documento_soporte',
        'area_solicitante',
        'fecha_limite',
        'flujo_to_be',
        'hash_reingenieria',
        'firmado_en',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'flujo_to_be'  => 'array',
        'fecha_limite' => 'date',
        'firmado_en'   => 'datetime',
    ];

    // ── Estados ──────────────────────────────────────────────────────────

    public const ESTADO_EN_REINGENIERIA     = 'en_reingenieria';
    public const ESTADO_OBSERVADA           = 'reingenieria_observada';
    public const ESTADO_APROBADA_PARA_FIRMA = 'aprobada_para_firma';
    public const ESTADO_PENDIENTE_FIRMAS    = 'pendiente_firmas';
    public const ESTADO_FIRMADA             = 'reingenieria_firmada';

    public const ESTADOS = [
        self::ESTADO_EN_REINGENIERIA,
        self::ESTADO_OBSERVADA,
        self::ESTADO_APROBADA_PARA_FIRMA,
        self::ESTADO_PENDIENTE_FIRMAS,
        self::ESTADO_FIRMADA,
    ];

    // ── Orígenes ─────────────────────────────────────────────────────────

    public const ORIGEN_AGENDA  = 'agenda';
    public const ORIGEN_DIRECTA = 'directa';

    // ── Motivos de reingeniería directa ──────────────────────────────────

    public const MOTIVOS_DIRECTA = [
        'exigencia_normativa',
        'instruccion_institucional',
        'urgencia_operativa',
        'integracion_plataforma',
        'correccion_proceso_critico',
        'otro',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function tramite()      { return $this->belongsTo(Tramite::class); }
    public function accionAgenda() { return $this->belongsTo(AccionAgenda::class, 'agenda_accion_id'); }
    public function creador()      { return $this->belongsTo(User::class, 'created_by'); }
    public function editor()       { return $this->belongsTo(User::class, 'updated_by'); }

    /** Firmas de la reingeniería (polimórfico, reutiliza tabla firmas). */
    public function firmas() { return $this->morphMany(Firma::class, 'firmable'); }

    public function diagramas() { return $this->hasMany(Diagrama::class); }

    public function observaciones() { return $this->morphMany(Observacion::class, 'observable'); }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function estaFirmada(): bool
    {
        return $this->estado === self::ESTADO_FIRMADA;
    }

    public function tieneFirmaEnlace(): bool
    {
        return $this->firmas()
            ->activas()
            ->delTipo(Firma::TIPO_ACEPTACION_ENLACE)
            ->exists();
    }

    public function tieneFirmaSujeto(): bool
    {
        return $this->firmas()
            ->activas()
            ->delTipo(Firma::TIPO_ACEPTACION_SUJETO)
            ->exists();
    }

    public function firmasCompletas(): bool
    {
        return $this->tieneFirmaEnlace() && $this->tieneFirmaSujeto();
    }

    public function esDirecta(): bool
    {
        return $this->origen === self::ORIGEN_DIRECTA;
    }

    public function estadoLegible(): string
    {
        return match ($this->estado) {
            self::ESTADO_EN_REINGENIERIA     => 'En reingeniería',
            self::ESTADO_OBSERVADA           => 'Observada',
            self::ESTADO_APROBADA_PARA_FIRMA => 'Aprobada para firma',
            self::ESTADO_PENDIENTE_FIRMAS    => 'Pendiente de firmas',
            self::ESTADO_FIRMADA             => 'Firmada',
            default => ucfirst(str_replace('_', ' ', $this->estado)),
        };
    }
}
