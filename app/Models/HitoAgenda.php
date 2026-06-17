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
    protected $guarded = ['id'];
    protected $table   = 'hitos_agenda';

    protected $casts = [
        'completado'       => 'boolean',
        'fecha_completado' => 'date',
    ];

    public function accionAgenda()
    {
        return $this->belongsTo(AccionAgenda::class, 'accion_agenda_id');
    }

    public function completadoPor()
    {
        return $this->belongsTo(User::class, 'completado_por');
    }
}
