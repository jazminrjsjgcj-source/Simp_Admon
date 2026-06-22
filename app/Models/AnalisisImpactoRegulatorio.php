<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GeneraFolio;

/**
 * AIR — Análisis de Impacto Regulatorio.
 *
 * Corresponde a los 7 elementos del Art. 38 LNETB.
 * Flujo de estatus: borrador → enviado → en_dictamen → dictaminado
 */
class AnalisisImpactoRegulatorio extends Model
{
    use GeneraFolio;

    protected $table   = 'analisis_impacto_regulatorio';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Cubre los 7
     * elementos del Art. 38 LNETB más los campos de dictamen. folio lo
     * asigna GeneraFolio. Reconstruido desde las migraciones del AIR.
     */
    protected $fillable = [
        'propuesta_id',
        'folio',
        'problematica',
        'objetivos',
        'alternativas',
        'costos_implementacion',
        'beneficios',
        'impacto_estimado',
        'impacta_tramites',
        'sector_scian',
        'subsector_scian',
        'poblacion_volumen',
        'ambito_aplicacion',
        'consulta_publica',
        'acciones_derivadas',
        'anexos',
        'estatus',
        'created_by',
        'dictamen',
        'dictamen_observaciones',
        'dictamen_fecha',
        'dictaminado_por',
    ];

    /** Prefijo de tipo para el folio: LPZ-AIR-... */
    protected function folioTipo(): string { return 'AIR'; }

    /** El AIR toma las siglas de la dependencia de su propuesta. */
    protected function folioSiglas(): string
    {
        $dep = $this->propuesta->dependencia ?? null;
        if ($dep && !empty($dep->siglas)) return $dep->siglas;
        if ($dep && !empty($dep->nombre)) return strtoupper(substr($dep->nombre, 0, 4));
        return 'GRAL';
    }

    /** Estatus del análisis. */
    public const ESTATUS_BORRADOR     = 'borrador';
    public const ESTATUS_ENVIADO      = 'enviado';
    public const ESTATUS_EN_DICTAMEN  = 'en_dictamen';
    public const ESTATUS_DICTAMINADO  = 'dictaminado';

    /** Resultado del dictamen. */
    public const DICTAMEN_PENDIENTE    = 'pendiente';
    public const DICTAMEN_FAVORABLE    = 'favorable';
    public const DICTAMEN_NO_FAVORABLE = 'no_favorable';

    /**
     * Etiquetas legibles de los 7 elementos del Art. 38 LNETB.
     * Usadas en el formulario y en el show.
     */
    public const ELEMENTOS_ART38 = [
        'problematica'          => '1. Problemática a resolver',
        'objetivos'             => '2. Objetivos y alcance',
        'alternativas'          => '3. Alternativas consideradas',
        'costos_implementacion' => '4. Costos de implementación',
        'beneficios'            => '5. Beneficios esperados',
        'impacto_estimado'      => '6. Impacto estimado en trámites',
        'consulta_publica'      => '7. Mecanismo de consulta pública',
    ];

    // ─── Relaciones ───────────────────────────────────────────────

    public function propuesta()
    {
        return $this->belongsTo(PropuestaRegulatoria::class, 'propuesta_id');
    }

    public function dictaminadoPor()
    {
        return $this->belongsTo(User::class, 'dictaminado_por');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function estaCompleto(): bool
    {
        foreach (array_keys(self::ELEMENTOS_ART38) as $campo) {
            if (empty($this->{$campo})) {
                return false;
            }
        }
        return true;
    }

    public function estadoBadge(): string
    {
        return match ($this->estatus) {
            self::ESTATUS_DICTAMINADO => 'success-b',
            self::ESTATUS_EN_DICTAMEN => '',
            self::ESTATUS_ENVIADO     => 'info-b',
            default                   => '',
        };
    }
}
