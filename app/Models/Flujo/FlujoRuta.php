<?php

namespace App\Models\Flujo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dónde sigue el proceso después de una actividad.
 *
 * Una actividad normal tiene una sola ruta, con condición `siempre`. Una que revisa
 * tiene dos: `correcto` e `incorrecto`. Esa segunda es la que dibuja la flecha de
 * retorno, que es justo lo que un flujo lineal no puede expresar.
 *
 * El destino puede ser la siguiente actividad, una concreta, el inicio de la fase,
 * el inicio del proceso, o el fin con uno de los resultados posibles.
 */
class FlujoRuta extends Model
{
    public const CONDICION_SIEMPRE    = 'siempre';
    public const CONDICION_CORRECTO   = 'correcto';
    public const CONDICION_INCORRECTO = 'incorrecto';

    public const DESTINO_SIGUIENTE      = 'siguiente';
    public const DESTINO_ACTIVIDAD      = 'actividad';
    public const DESTINO_INICIO_FASE    = 'inicio_fase';
    public const DESTINO_INICIO_PROCESO = 'inicio_proceso';
    public const DESTINO_FIN            = 'fin';

    protected $table = 'flujo_rutas';

    protected $fillable = [
        'actividad_id',
        'condicion',
        'destino_tipo',
        'destino_actividad_id',
        'resultado_id',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(FlujoActividad::class, 'actividad_id');
    }

    public function destinoActividad(): BelongsTo
    {
        return $this->belongsTo(FlujoActividad::class, 'destino_actividad_id');
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(FlujoResultado::class, 'resultado_id');
    }

    /** Texto legible del destino, según el catálogo de config. */
    public function destinoLabel(): string
    {
        return config("punta.flujo.destinos_ruta.{$this->destino_tipo}", $this->destino_tipo);
    }

    /** ¿Esta ruta vuelve hacia atrás? Son las que el diagrama dibuja punteadas. */
    public function esRetorno(): bool
    {
        return in_array($this->destino_tipo, [
            self::DESTINO_INICIO_FASE,
            self::DESTINO_INICIO_PROCESO,
        ], true) || $this->condicion === self::CONDICION_INCORRECTO;
    }
}
