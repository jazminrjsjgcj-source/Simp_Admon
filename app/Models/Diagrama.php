<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Diagrama generado a partir de una reingeniería firmada.
 *
 * Almacena el código Mermaid (generado automáticamente desde el flujo
 * estructurado) y opcionalmente el XML de Draw.io (si el digitalizador
 * hizo ajustes visuales en el editor embebido).
 *
 * El diagrama se puede regenerar, descargar en varios formatos, y su
 * hash permite verificar que corresponde a la versión firmada.
 */
class Diagrama extends Model
{
    protected $table = 'diagramas';

    protected $fillable = [
        'tramite_id',
        'reingenieria_id',
        'tipo_diagrama',
        'contenido_mermaid',
        'contenido_drawio_xml',
        'hash_diagrama',
        'estado',
        'created_by',
        'updated_by',
    ];

    // ── Estados ──────────────────────────────────────────────────────────

    public const ESTADO_SIN_DIAGRAMA         = 'sin_diagrama';
    public const ESTADO_GENERADO             = 'diagrama_generado';
    public const ESTADO_AJUSTADO             = 'diagrama_ajustado';
    public const ESTADO_LISTO                = 'listo_para_descarga';
    public const ESTADO_SUSTITUIDO           = 'diagrama_sustituido';
    public const ESTADO_REQUIERE_ACTUALIZACION = 'requiere_actualizacion';

    public const ESTADOS = [
        self::ESTADO_SIN_DIAGRAMA,
        self::ESTADO_GENERADO,
        self::ESTADO_AJUSTADO,
        self::ESTADO_LISTO,
        self::ESTADO_SUSTITUIDO,
        self::ESTADO_REQUIERE_ACTUALIZACION,
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function tramite()       { return $this->belongsTo(Tramite::class); }
    public function reingenieria()  { return $this->belongsTo(Reingenieria::class); }
    public function creador()       { return $this->belongsTo(User::class, 'created_by'); }
    public function descargas()     { return $this->hasMany(DescargaDiagrama::class); }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function tieneMermaid(): bool
    {
        return !empty($this->contenido_mermaid);
    }

    public function tieneDrawio(): bool
    {
        return !empty($this->contenido_drawio_xml);
    }

    public function estaListo(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_GENERADO,
            self::ESTADO_AJUSTADO,
            self::ESTADO_LISTO,
        ]);
    }

    public function estadoLegible(): string
    {
        return match ($this->estado) {
            self::ESTADO_SIN_DIAGRAMA            => 'Sin diagrama',
            self::ESTADO_GENERADO                => 'Generado',
            self::ESTADO_AJUSTADO                => 'Ajustado visualmente',
            self::ESTADO_LISTO                   => 'Listo para descarga',
            self::ESTADO_SUSTITUIDO              => 'Sustituido',
            self::ESTADO_REQUIERE_ACTUALIZACION  => 'Requiere actualización',
            default => ucfirst(str_replace('_', ' ', $this->estado)),
        };
    }
}
