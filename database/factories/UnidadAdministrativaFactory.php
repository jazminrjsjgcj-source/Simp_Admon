<?php

namespace Database\Factories;

use App\Models\Dependencia;
use App\Models\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea unidades administrativas para las pruebas.
 *
 * Si no se le pasa una dependencia, crea una nueva automáticamente.
 *
 * @extends Factory<UnidadAdministrativa>
 */
class UnidadAdministrativaFactory extends Factory
{
    protected $model = UnidadAdministrativa::class;

    public function definition(): array
    {
        return [
            'dependencia_id' => Dependencia::factory(),
            'codigo'         => (string) fake()->unique()->numberBetween(10, 99),
            'nombre'         => 'Dirección de ' . fake()->unique()->word(),
            'siglas'         => strtoupper(fake()->unique()->lexify('???')),
        ];
    }

    public function sinSiglas(): static
    {
        return $this->state(fn () => ['siglas' => null]);
    }
}
