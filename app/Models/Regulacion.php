<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GeneraFolio;

class Regulacion extends Model
{
    use GeneraFolio;

    protected $table   = 'regulaciones';

    /**
     * Columnas asignables en masa (sin id, timestamps ni deleted_at). Incluye
     * los campos de conversión a Markdown y el índice JSON. folio lo asigna
     * GeneraFolio. Reconstruido desde las migraciones de regulaciones.
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'dependencia_id',
        'sector_id',
        'fecha_publicacion',
        'fecha_vigencia',
        'estatus',
        'archivo_pdf',
        'resumen',
        'created_by',
        'folio',
        'archivo_original',
        'archivo_markdown',
        'conversion_estatus',
        'conversion_error',
        'extension_original',
        'materia',
        'fundamento_juridico',
        'objetivo',
        'palabras_clave',
        'deroga_otra',
        'regulacion_derogada',
        'indice',
    ];

    /** Prefijo de tipo para el folio: LPZ-REG-... */
    protected function folioTipo(): string { return 'REG'; }

    /** Estatus de la regulación. */
    public const ESTATUS_VIGENTE     = 'vigente';
    public const ESTATUS_EN_REVISION = 'en_revision';
    public const ESTATUS_DEROGADA    = 'derogada';

    public const ESTATUS_TODOS = [
        self::ESTATUS_VIGENTE,
        self::ESTATUS_EN_REVISION,
        self::ESTATUS_DEROGADA,
    ];

    /** Conversión a Markdown. */
    public const CONVERSION_PENDIENTE  = 'pendiente';
    public const CONVERSION_PROCESANDO = 'procesando';
    public const CONVERSION_LISTO      = 'listo';
    public const CONVERSION_ERROR      = 'error';

    public const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'doc'];

    /** Materias disponibles (Art. 153 fracc. II Lineamientos). */
    public const MATERIAS = [
        'Comercio',
        'Desarrollo Urbano',
        'Protección Civil',
        'Seguridad',
        'Medio Ambiente',
        'Hacienda',
        'Gobierno',
        'Digitalización',
        'Servicios Públicos',
        'Tránsito y Transporte',
        'Salud',
        'Educación',
        'Otra',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'fecha_vigencia'    => 'date',
        'deroga_otra'       => 'boolean',
        'indice'            => 'array',
    ];

    // ========== Relaciones ==========

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
    public function creador()     { return $this->belongsTo(User::class, 'created_by'); }
    public function sector()      { return $this->belongsTo(SectorScian::class, 'sector_id'); }

    // ========== Helpers de estado ==========

    public function conversionListaParaCitar(): bool
    {
        return $this->conversion_estatus === self::CONVERSION_LISTO
            && !empty($this->archivo_markdown);
    }

    public function estaVigente(): bool
    {
        return $this->estatus === self::ESTATUS_VIGENTE;
    }

    public function tieneIndice(): bool
    {
        return !empty($this->indice) && is_array($this->indice);
    }

    /**
     * Devuelve las palabras clave como array limpio.
     */
    public function palabrasClaveComoArray(): array
    {
        if (empty($this->palabras_clave)) {
            return [];
        }

        return array_map('trim', explode(',', $this->palabras_clave));
    }
}
