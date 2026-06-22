<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un hito de avance de una acción de agenda.
 *
 * Cada acción de agenda tiene varios hitos (ver config/hitos.php). Esta clase
 * representa una fila concreta: el hito "Validación jurídica" de la acción #5,
 * por ejemplo, con su estado de completado, fecha y quién lo marcó.
 */
class HitoAgenda extends Model
{
    protected $table   = 'hitos_agenda';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Incluye los campos
     * de evidencia y visto bueno (Grupo 3) que asigna HitoAgendaService.
     * Reconstruido desde las migraciones de hitos_agenda.
     */
    protected $fillable = [
        'accion_agenda_id',
        'orden',
        'clave',
        'nombre',
        'completado',
        'fecha_completado',
        'completado_por',
        'evidencia_archivo',
        'evidencia_nombre',
        'estado_aprobacion',
        'aprobado_por',
        'fecha_aprobacion',
        'motivo_rechazo',
    ];

    protected $casts = [
        'completado'       => 'boolean',
        'fecha_completado' => 'date',
        'fecha_aprobacion' => 'date',
    ];

    public function accionAgenda()
    {
        return $this->belongsTo(AccionAgenda::class, 'accion_agenda_id');
    }

    public function completadoPor()
    {
        return $this->belongsTo(User::class, 'completado_por');
    }

    /** Grupo 3: revisora que dio (o negó) el visto bueno. */
    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
