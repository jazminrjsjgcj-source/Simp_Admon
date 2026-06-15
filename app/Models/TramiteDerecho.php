<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Concepto de pago de derechos ligado a un trámite.
 *
 * Son cobros fiscales (ej. "Derecho de inspección: $250") independientes
 * del costo público del trámite. Un trámite puede tener varios.
 */
class TramiteDerecho extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'tramite_derechos';

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * El trámite al que pertenece este concepto de derecho.
     */
    public function tramite()
    {
        return $this->belongsTo(Tramite::class);
    }
}
