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

    protected $guarded = ['id'];
    protected $table   = 'acciones_agenda';

    /** Prefijo de tipo para el folio: LPZ-AGD-... */
    protected function folioTipo(): string { return 'AGD'; }

    public function dependencia() { return $this->belongsTo(Dependencia::class); }

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
