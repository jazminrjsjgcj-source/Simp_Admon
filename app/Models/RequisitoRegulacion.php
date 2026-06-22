<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cita de una regulación dentro de un requisito de trámite.
 * Tabla intermedia: un requisito puede citar varias regulaciones.
 */
class RequisitoRegulacion extends Model
{
    protected $table   = 'requisito_regulacion';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     */
    protected $fillable = [
        'requisito_id',
        'regulacion_id',
        'articulo_fraccion',
    ];

    public function requisito()
    {
        return $this->belongsTo(Requisito::class);
    }

    public function regulacion()
    {
        return $this->belongsTo(Regulacion::class);
    }
}
