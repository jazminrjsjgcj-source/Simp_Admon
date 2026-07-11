<?php

namespace Database\Factories;

use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea trámites para las pruebas.
 *
 * Por defecto el trámite nace como BORRADOR y sin homoclave: así es como se crea
 * de verdad en el sistema (la homoclave se genera al enviarlo a revisión). Para
 * probar otros estados existen los métodos completado(), enFirma() y servicio().
 *
 * @extends Factory<Tramite>
 */
class TramiteFactory extends Factory
{
    protected $model = Tramite::class;

    public function definition(): array
    {
        // La unidad se crea junto con su dependencia, y el trámite usa las dos:
        // así la homoclave (LPZ-T-{siglas dep}-{siglas unidad}-{N}) puede formarse.
        $unidad = UnidadAdministrativa::factory()->create();

        return [
            'nombre_oficial' => 'Trámite de ' . fake()->unique()->words(3, true),
            'naturaleza'     => 'tramite',
            'dependencia_id' => $unidad->dependencia_id,
            'unidad_id'      => $unidad->id,
            'estatus'        => Tramite::ESTATUS_BORRADOR,
            'objetivo'       => fake()->sentence(),
            'dirigido_a'     => 'ambas',
            'volumen_anual'  => fake()->numberBetween(10, 1000),
        ];
    }

    /** Un servicio en vez de un trámite (cambia la letra de la homoclave: S en vez de T). */
    public function servicio(): static
    {
        return $this->state(fn () => [
            'naturaleza'    => 'servicio',
            'tipo_servicio' => 'Servicio público',
        ]);
    }

    /** Trámite ya terminado (el único que puede vincularse a una acción de agenda). */
    public function completado(): static
    {
        return $this->state(fn () => ['estatus' => Tramite::ESTATUS_COMPLETADO]);
    }

    public function enFirma(): static
    {
        return $this->state(fn () => ['estatus' => Tramite::ESTATUS_EN_FIRMA]);
    }

    /** Trámite cuya dependencia y unidad NO tienen siglas: la homoclave no se puede formar. */
    public function sinSiglas(): static
    {
        return $this->state(function () {
            $dependencia = Dependencia::factory()->sinSiglas()->create();
            $unidad      = UnidadAdministrativa::factory()
                ->sinSiglas()
                ->create(['dependencia_id' => $dependencia->id]);

            return [
                'dependencia_id' => $dependencia->id,
                'unidad_id'      => $unidad->id,
            ];
        });
    }
}
