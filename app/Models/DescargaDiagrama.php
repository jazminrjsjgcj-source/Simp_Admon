<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de bitácora de descargas de diagramas.
 *
 * Cada vez que un digitalizador descarga un diagrama en cualquier formato
 * (PDF oficial, JPG, PNG, SVG, DrawIO) se registra una fila aquí.
 * No es una nueva versión — es historial de acceso.
 */
class DescargaDiagrama extends Model
{
    public $timestamps = false;

    protected $table = 'descargas_diagrama';

    protected $fillable = [
        'diagrama_id',
        'reingenieria_id',
        'usuario_id',
        'formato',
        'hash_archivo_generado',
        'ip',
        'user_agent',
    ];

    public const FORMATO_PDF    = 'pdf';
    public const FORMATO_JPG    = 'jpg';
    public const FORMATO_PNG    = 'png';
    public const FORMATO_SVG    = 'svg';
    public const FORMATO_DRAWIO = 'drawio';

    public const FORMATOS = [
        self::FORMATO_PDF,
        self::FORMATO_JPG,
        self::FORMATO_PNG,
        self::FORMATO_SVG,
        self::FORMATO_DRAWIO,
    ];

    public function diagrama()     { return $this->belongsTo(Diagrama::class); }
    public function reingenieria() { return $this->belongsTo(Reingenieria::class); }
    public function usuario()      { return $this->belongsTo(User::class, 'usuario_id'); }
}
