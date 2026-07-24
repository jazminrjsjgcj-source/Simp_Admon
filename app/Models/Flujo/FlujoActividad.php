<?php

namespace App\Models\Flujo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que se hace en una fase: quién lo hace y qué hace.
 *
 * Una actividad puede limitarse a ejecutar algo, o puede REVISAR. Cuando revisa
 * (tiene_decision), el proceso se bifurca: tiene una ruta para cuando sale bien y
 * otra para cuando sale mal. Esa bifurcación es la razón de ser de este modelo; un
 * flujo lineal no puede representar que una revisión devuelva el expediente.
 *
 * El campo `detalle` guarda en JSON lo que solo se lee al abrir esta actividad:
 *
 *     pago    => ['acciones' => [...], 'derecho_id' => 12, 'participante_id' => 3]
 *     nota    => ['titulo' => '...', 'texto' => '...', 'aplica' => 'actividad'|'fase']
 *     estado  => 'pendiente_de_pago'
 *
 * El pago referencia un concepto de `tramite_derechos` en vez de guardar su propio
 * importe: el costo oficial vive en el catálogo del trámite y no debe existir dos
 * veces, porque entonces pueden decir cosas distintas.
 */
class FlujoActividad extends Model
{
    protected $table = 'flujo_actividades';

    protected $fillable = [
        'fase_id',
        'participante_id',
        'descripcion',
        'tiene_decision',
        'que_revisa',
        'detalle',
        'orden',
    ];

    protected $casts = [
        'tiene_decision' => 'boolean',
        'detalle'        => 'array',
        'orden'          => 'integer',
    ];

    public function fase(): BelongsTo
    {
        return $this->belongsTo(FlujoFase::class, 'fase_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(FlujoParticipante::class, 'participante_id');
    }

    public function rutas(): HasMany
    {
        return $this->hasMany(FlujoRuta::class, 'actividad_id');
    }

    /** La ruta que aplica cuando la actividad no decide nada. */
    public function rutaUnica(): ?FlujoRuta
    {
        return $this->rutas->firstWhere('condicion', FlujoRuta::CONDICION_SIEMPRE);
    }

    /** Las dos salidas de una actividad que revisa. */
    public function rutaCorrecto(): ?FlujoRuta
    {
        return $this->rutas->firstWhere('condicion', FlujoRuta::CONDICION_CORRECTO);
    }

    public function rutaIncorrecto(): ?FlujoRuta
    {
        return $this->rutas->firstWhere('condicion', FlujoRuta::CONDICION_INCORRECTO);
    }

    /** ¿Interviene un pago en esta actividad? */
    public function tienePago(): bool
    {
        return ! empty($this->detalle['pago']['acciones'] ?? []);
    }
}
