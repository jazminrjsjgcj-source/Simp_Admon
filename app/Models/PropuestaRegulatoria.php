<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropuestaRegulatoria extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'propuestas_regulatorias';

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
    public function generarFolio(): string
    {
        $siglas = $this->dependencia->siglas
            ?? strtoupper(substr($this->dependencia->nombre ?? 'DEP', 0, 4));
        $anio = now()->year;
        $prefijo = "PROP-{$siglas}-{$anio}-";

        // Busca el último consecutivo usado con este prefijo.
        $ultimo = static::where('folio', 'like', $prefijo . '%')
            ->orderByDesc('folio')
            ->value('folio');

        $siguiente = 1;
        if ($ultimo) {
            $numero = (int) substr($ultimo, strlen($prefijo));
            $siguiente = $numero + 1;
        }

        return $prefijo . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }

    // ========== Relaciones ==========

    public function dependencia()   { return $this->belongsTo(Dependencia::class); }
    public function creador()       { return $this->belongsTo(User::class, 'created_by'); }
    public function sector()        { return $this->belongsTo(SectorScian::class,    'sector_id'); }
    public function subsector()     { return $this->belongsTo(SubsectorScian::class, 'subsector_id'); }
    public function observaciones() { return $this->morphMany(Observacion::class,    'observable'); }
    public function firmas()        { return $this->morphMany(Firma::class,          'firmable'); }
    public function air()           { return $this->hasOne(AnalisisImpactoRegulatorio::class, 'propuesta_id'); }
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
}
