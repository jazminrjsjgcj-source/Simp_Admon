<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GeneraFolio;

class AccionAgenda extends Model
{
    use GeneraFolio;

    // Estatus del flujo de una acción de agenda.
    // Homologados con Tramite: ambos módulos comparten vocabulario.
    public const ESTATUS_BORRADOR       = 'borrador';
    public const ESTATUS_EN_OBSERVACION = 'en_observacion';
    public const ESTATUS_EN_CORRECCION  = 'en_correccion';
    public const ESTATUS_EN_FIRMA       = 'en_firma';
    public const ESTATUS_COMPLETADO     = 'completado';

    public const ESTATUS_TODOS = [
        self::ESTATUS_BORRADOR,
        self::ESTATUS_EN_OBSERVACION,
        self::ESTATUS_EN_CORRECCION,
        self::ESTATUS_EN_FIRMA,
        self::ESTATUS_COMPLETADO,
    ];

    protected $table   = 'acciones_agenda';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Incluye folio y
     * periodo_id (los asigna el modelo en booted()/GeneraFolio) y los JSON
     * de acciones. Reconstruido desde las migraciones de acciones_agenda.
     */
    protected $fillable = [
        'tramite_id',
        'tipo',
        'descripcion',
        'meta',
        'fecha_inicio',
        'fecha_compromiso',
        'responsable',
        'dependencia_id',
        'unidad_id',
        'indicador',
        'indicador_avance',
        'estatus',
        'created_by',
        'folio',
        'periodo_id',
        'acciones_simplificacion',
        'acciones_digitalizacion',
        'nivel_actual',
        'nivel_meta',
    ];

    // Paquete 3: catálogos oficiales guardados como JSON.
    protected $casts = [
        'acciones_simplificacion' => 'array',
        'acciones_digitalizacion' => 'array',
    ];

    /**
     * #12: al crear una acción se le asigna automáticamente el periodo SyD
     * activo, salvo que ya venga uno explícito.
     */
    protected static function booted(): void
    {
        static::creating(function (AccionAgenda $accion) {
            if (empty($accion->periodo_id)) {
                $accion->periodo_id = Periodo::activo()->syd()->value('id');
            }
        });
    }

    /** #12: periodo (Agenda SyD) al que pertenece la acción. */
    public function periodo() { return $this->belongsTo(Periodo::class); }

    /** Prefijo de tipo para el folio: LPZ-AGD-... */
    protected function folioTipo(): string { return 'AGD'; }

    public function dependencia() { return $this->belongsTo(Dependencia::class); }

    public function unidad() { return $this->belongsTo(UnidadAdministrativa::class, 'unidad_id'); }

    public function tramite() { return $this->belongsTo(Tramite::class); }

    public function creador() { return $this->belongsTo(User::class, 'created_by'); }

    public function observaciones() { return $this->morphMany(Observacion::class, 'observable'); }

    public function firmas() { return $this->morphMany(Firma::class, 'firmable'); }

    public function hitos() { return $this->hasMany(HitoAgenda::class, 'accion_agenda_id')->orderBy('orden'); }

    /** Porcentaje de avance según hitos completados (0-100). */
    public function porcentajeAvance(): int
    {
        $total = $this->hitos()->count();
        if ($total === 0) {
            return 0;
        }
        return (int) round($this->hitos()->where('completado', true)->count() / $total * 100);
    }
}