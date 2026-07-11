<?php

namespace Database\Factories;

use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea dependencias para las pruebas.
 *
 * @extends Factory<Dependencia>
 */
class DependenciaFactory extends Factory
{
    protected $model = Dependencia::class;

    public function definition(): array
    {
        return [
            // Código y siglas son únicos: se usa unique() para que dos dependencias
            // creadas en la misma prueba no choquen.
            'codigo' => (string) fake()->unique()->numberBetween(100, 999),
            'nombre' => 'Dirección General de ' . fake()->unique()->word(),
            'siglas' => strtoupper(fake()->unique()->lexify('????')),
        ];
    }

    /**
     * Dependencia sin siglas. Sirve para comprobar que la homoclave NO se puede
     * generar cuando faltan (la homoclave se arma con las siglas).
     */
    public function sinSiglas(): static
    {
        return $this->state(fn () => ['siglas' => null]);
    }
}
