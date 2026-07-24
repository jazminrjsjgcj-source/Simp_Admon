<?php

namespace App\Models\Flujo;

use App\Models\Reingenieria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Etapa del proceso que agrupa actividades: captura, pago, revisión, entrega.
 *
 * Sirve para dos cosas: dar estructura al diagrama, y ser destino de retorno —una
 * revisión que sale mal puede mandar al inicio de la fase en vez de al principio de
 * todo—.
 */
class FlujoFase extends Model
{
    protected $table = 'flujo_fases';

    protected $fillable = ['reingenieria_id', 'nombre', 'nota', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function reingenieria(): BelongsTo
    {
        return $this->belongsTo(Reingenieria::class);
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(FlujoActividad::class, 'fase_id')->orderBy('orden');
    }
}
