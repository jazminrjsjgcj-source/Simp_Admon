<?php

namespace App\Models\Flujo;

use App\Models\Reingenieria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quién interviene en un proceso: la persona solicitante, el sistema, Tesorería...
 *
 * Es lo que en un diagrama de flujo son los carriles. Se guarda como registro y no
 * como texto libre para poder responder qué procesos pasan por un área, y para que
 * el color del carril salga del tipo en vez de elegirlo alguien a mano.
 */
class FlujoParticipante extends Model
{
    protected $table = 'flujo_participantes';

    protected $fillable = ['reingenieria_id', 'nombre', 'tipo', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function reingenieria(): BelongsTo
    {
        return $this->belongsTo(Reingenieria::class);
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(FlujoActividad::class, 'participante_id');
    }

    /** Texto legible del tipo, según el catálogo de config. */
    public function tipoLabel(): string
    {
        return config("punta.flujo.tipos_participante.{$this->tipo}.label", $this->tipo);
    }

    /** Color del carril en el diagrama. Sale del tipo, no se captura. */
    public function color(): string
    {
        return config("punta.flujo.tipos_participante.{$this->tipo}.color", '#475569');
    }
}
