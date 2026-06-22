<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CalendarioEvento extends Model
{
    protected $table   = 'calendario_eventos';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     * eventable_type/eventable_id los llena morphTo al crear.
     */
    protected $fillable = [
        'tipo',
        'titulo',
        'accion',
        'meta',
        'fecha',
        'estatus',
        'avance',
        'responsable',
        'dependencia_id',
        'evidencia',
        'eventable_type',
        'eventable_id',
    ];

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
}
