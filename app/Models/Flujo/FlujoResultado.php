<?php

namespace App\Models\Flujo;

use App\Models\Reingenieria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una de las formas en que puede terminar el proceso.
 *
 * No todos los finales son el documento emitido: también se termina porque la
 * solicitud no procede, porque se cancela, o porque el proceso concluye sin emitir
 * nada. Cada uno es un final distinto y el diagrama tiene que poder mostrarlo.
 */
class FlujoResultado extends Model
{
    protected $table = 'flujo_resultados';

    protected $fillable = ['reingenieria_id', 'nombre', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function reingenieria(): BelongsTo
    {
        return $this->belongsTo(Reingenieria::class);
    }
}
