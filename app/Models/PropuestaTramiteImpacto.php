<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * #7 — Impacto declarado de una propuesta regulatoria sobre un trámite.
 *
 * El jurídico indica al llenar la propuesta qué trámites (y opcionalmente qué
 * requisitos concretos) se verán afectados, y qué artículo/fracción de la
 * propuesta los modifica.
 */
class PropuestaTramiteImpacto extends Model
{
    protected $table   = 'propuesta_tramite_impacto';

    /**
     * Columnas asignables en masa (sin id ni timestamps).
     */
    protected $fillable = [
        'propuesta_id',
        'tramite_id',
        'accion',
        'requisito_id',
        'articulo_fraccion',
        'descripcion',
    ];

    public function propuesta()
    {
        return $this->belongsTo(PropuestaRegulatoria::class, 'propuesta_id');
    }

    public function tramite()
    {
        return $this->belongsTo(Tramite::class);
    }

    public function requisito()
    {
        return $this->belongsTo(Requisito::class);
    }
}
