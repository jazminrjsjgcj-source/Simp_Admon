<?php

namespace Database\Factories;

use App\Models\AccionAgenda;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea acciones de la Agenda de Simplificación y Digitalización.
 *
 * Nace como BORRADOR y SIN FOLIO, que es como funciona el sistema: el folio se
 * asigna al enviar la acción a revisión, no al guardarla como borrador (así no se
 * gastan folios en trabajo que puede borrarse).
 *
 * Se vincula a un trámite COMPLETADO, porque una acción de agenda se hace sobre un
 * trámite que ya existe formalmente.
 *
 * @extends Factory<AccionAgenda>
 */
class AccionAgendaFactory extends Factory
{
    protected $model = AccionAgenda::class;

    public function definition(): array
    {
        $tramite = Tramite::factory()->completado()->create();

        return [
            'tramite_id'       => $tramite->id,
            'dependencia_id'   => $tramite->dependencia_id,
            'unidad_id'        => $tramite->unidad_id,
            'tipo'             => 'simplificacion',
            'descripcion'      => 'Reducir requisitos de ' . fake()->words(3, true),
            'meta'             => fake()->sentence(),
            'responsable'      => fake()->name(),
            'fecha_inicio'     => now()->toDateString(),
            'fecha_compromiso' => now()->addMonths(3)->toDateString(),
            'estatus'          => AccionAgenda::ESTATUS_BORRADOR,
            'folio'            => null, // se genera al enviar a revisión
        ];
    }

    public function digitalizacion(): static
    {
        return $this->state(fn () => ['tipo' => 'digitalizacion']);
    }

    /** Acción que abarca simplificación Y digitalización. */
    public function ambas(): static
    {
        return $this->state(fn () => ['tipo' => 'ambas']);
    }

    public function enObservacion(): static
    {
        return $this->state(fn () => ['estatus' => AccionAgenda::ESTATUS_EN_OBSERVACION]);
    }

    public function completada(): static
    {
        return $this->state(fn () => ['estatus' => AccionAgenda::ESTATUS_COMPLETADO]);
    }
}
