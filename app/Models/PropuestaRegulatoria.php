<?php

namespace App\Models;

use App\Models\Concerns\CongelaCatalogos;
use App\Models\Concerns\GeneraFolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PropuestaRegulatoria extends Model
{
    use CongelaCatalogos, GeneraFolio, SoftDeletes;
    protected $table   = 'propuestas_regulatorias';

    /**
     * Columnas asignables en masa (sin id ni timestamps). folio lo asigna
     * el modelo en booted() al dejar de ser borrador. Reconstruido desde
     * las migraciones de propuestas_regulatorias.
     */
    protected $fillable = [
        'catalogos_al_firmar',
        'folio',
        'nombre',
        'tipo_regulacion',
        'dependencia_id',
        'sector_id',
        'subsector_id',
        'fecha_tentativa',
        'justificacion',
        'costo_burocratico',
        'poblacion_afectada',
        'determinacion_air',
        'estatus',
        'archivo_propuesta',
        'created_by',
        'genera_costos_burocraticos',
        'impacta_comercio_inversion',
        'impacta_tramites_existentes',
    ];

    /** Estatus de la propuesta. */
    public const ESTATUS_BORRADOR    = 'borrador';
    public const ESTATUS_CONSULTA    = 'consulta';
    public const ESTATUS_DETERMINADA = 'determinada';
    public const ESTATUS_DICTAMINADA = 'dictaminada';
    public const ESTATUS_PUBLICADA   = 'publicada';

    /** Resultado de la determinación AIR. */
    public const AIR_PENDIENTE    = 'pendiente';
    public const AIR_REQUIERE_AIR = 'requiere_air';
    public const AIR_EXENTO       = 'exento';

    protected $casts = [
        'catalogos_al_firmar' => 'array',
        'fecha_tentativa'            => 'date',
        'genera_costos_burocraticos' => 'boolean',
        'impacta_comercio_inversion' => 'boolean',
        'impacta_tramites_existentes'=> 'boolean',
    ];

    /**
     * Eventos del modelo: genera el folio automáticamente cuando la
     * propuesta deja de ser borrador (se envía a revisión) y aún no
     * tiene uno. Formato: PROP-{SIGLAS}-{AÑO}-{consecutivo}.
     */
    protected static function booted(): void
    {
        static::updating(function (PropuestaRegulatoria $propuesta) {
            $dejaBorrador = $propuesta->isDirty('estatus')
                && $propuesta->getOriginal('estatus') === self::ESTATUS_BORRADOR
                && $propuesta->estatus !== self::ESTATUS_BORRADOR;

            if ($dejaBorrador && empty($propuesta->folio)) {
                $propuesta->folio = $propuesta->generarFolio();
            }
        });
    }

    /**
     * Genera un folio único para la propuesta.
     * Formato: PROP-{SIGLAS_DEPENDENCIA}-{AÑO}-{consecutivo de 3 dígitos}.
     * El consecutivo se reinicia por dependencia y por año.
     */
    /** Prefijo de tipo del folio: LPZ-PROP-... (usa el trait GeneraFolio). */
    protected function folioTipo(): string { return 'PROP'; }

    // ========== Relaciones ==========

    public function dependencia()   { return $this->belongsTo(Dependencia::class); }
    public function creador()       { return $this->belongsTo(User::class, 'created_by'); }
    public function sector()        { return $this->belongsTo(SectorScian::class,    'sector_id'); }
    public function subsector()     { return $this->belongsTo(SubsectorScian::class, 'subsector_id'); }
    public function observaciones() { return $this->morphMany(Observacion::class,    'observable'); }
    public function firmas()        { return $this->morphMany(Firma::class,          'firmable'); }
    public function air()           { return $this->hasOne(AnalisisImpactoRegulatorio::class, 'propuesta_id'); }
    /** #7: trámites y requisitos que esta propuesta declaró que va a modificar. */
    public function impactos()      { return $this->hasMany(PropuestaTramiteImpacto::class, 'propuesta_id'); }

    /**
     * B18 — Rubros 13 y 14: acciones de Agenda SyD (simplificación/digitalización)
     * vinculadas a esta propuesta, a través de la pivote propuesta_accion_syd.
     * El campo 'tipo' del pivote distingue si la acción es de simplificación o
     * de digitalización en el contexto de esta propuesta.
     */
    public function accionesSyd()
    {
        return $this->belongsToMany(AccionAgenda::class, 'propuesta_accion_syd', 'propuesta_id', 'accion_agenda_id')
            ->withPivot('tipo')
            ->withTimestamps();
    }
    public function exencion()      { return $this->hasOne(ExencionAir::class, 'propuesta_id'); }

    // ========== Helpers de determinación AIR ==========

    /**
     * Criterio 1 (Art. 35 fracc. I LNETB / Art. 71 Lineamientos):
     * La propuesta establece nuevos costos burocráticos.
     */
    public function estableceCostosBurocraticos(): bool
    {
        return $this->genera_costos_burocraticos === true;
    }

    /**
     * Criterio 2 (Art. 35 fracc. II LNETB / Art. 72 Lineamientos):
     * La propuesta impacta directamente en alguna actividad económica.
     * Se cumple si afecta comercio/inversión O modifica trámites existentes.
     */
    public function impactaActividadEconomica(): bool
    {
        return $this->impacta_comercio_inversion === true
            || $this->impacta_tramites_existentes === true;
    }

    /**
     * Criterio 3 (Art. 35 fracc. III LNETB / Art. 73 Lineamientos):
     * El costo burocrático estimado supera el umbral de proporcionalidad
     * del sector SCIAN al que pertenece la propuesta.
     */
    public function superaUmbralProporcionalidad(): bool
    {
        if (!$this->costo_burocratico || !$this->sector_id) {
            return false;
        }

        $umbral = DB::table('configuracion_sistema')
            ->where('clave', 'umbral_proporcionalidad')
            ->value('valor');

        if (!$umbral) {
            return false;
        }

        return $this->costo_burocratico > (float) $umbral;
    }

    /**
     * Determina si la propuesta es candidata a requerir AIR.
     *
     * Los tres criterios deben cumplirse simultáneamente (Art. 35 LNETB).
     * No evalúa las exenciones del Art. 36 — eso lo decide el enlace.
     */
    public function esCandidataAir(): bool
    {
        return $this->estableceCostosBurocraticos()
            && $this->impactaActividadEconomica()
            && $this->superaUmbralProporcionalidad();
    }

    /**
     * Indica si la determinación AIR ya fue resuelta
     * (el enlace ya eligió AIR o exención).
     */
    public function tieneDeterminacion(): bool
    {
        return $this->determinacion_air !== self::AIR_PENDIENTE;
    }

    /**
     * Indica si los tres campos booleanos ya fueron capturados.
     * Sin ellos no se puede calcular la candidatura.
     */
    public function tieneCamposAirCompletos(): bool
    {
        return !is_null($this->genera_costos_burocraticos)
            && !is_null($this->impacta_comercio_inversion)
            && !is_null($this->impacta_tramites_existentes);
    }

    /**
     * Catálogos que se congelan al firmar la propuesta: los nombres que aparecen en
     * el documento. Si el catálogo cambia después, la propuesta firmada sigue
     * diciendo lo que decía (ver el trait CongelaCatalogos).
     */
    public function catalogosCongelables(): array
    {
        return [
            'dependencia' => 'nombre',
            'sector'      => 'nombre',
            'subsector'   => 'nombre',
        ];
    }
}
