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
        // La unidad (y con ella su dependencia) se crea SOLO si el llamador no la pasa.
        //
        // Antes se creaba SIEMPRE, de golpe, aunque la prueba ya diera unidad_id y
        // dependencia_id. Esa dependencia-fantasma nacía con un código aleatorio (100-999)
        // y chocaba ~1 de cada 900 veces con un código fijo de la prueba —el 110 de
        // IdentificadoresUnicosTest—, volviendo la suite flaky sin motivo real.
        //
        // Con atributos perezosos, si el llamador sobrescribe unidad_id/dependencia_id,
        // ni la unidad ni la dependencia fantasma se crean. Si no los pasa, se crea una
        // unidad nueva (con su dependencia) y ambos ids salen de ella, consistentes.
        return [
            'nombre_oficial' => 'Trámite de ' . fake()->unique()->words(3, true),
            'naturaleza'     => 'tramite',
            'unidad_id'      => UnidadAdministrativa::factory(),
            'dependencia_id' => function (array $attributes) {
                // Con unidad: la dependencia SALE de ella (deben ser consistentes).
                if (isset($attributes['unidad_id'])) {
                    return UnidadAdministrativa::findOrFail($attributes['unidad_id'])->dependencia_id;
                }

                // Sin unidad (caso de prueba "sin unidad no hay homoclave"): el trámite
                // aún necesita una dependencia válida, porque la columna es NOT NULL. Se
                // crea una dependencia suelta —sin la unidad fantasma que causaba el flaky—.
                return Dependencia::factory()->create()->id;
            },
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
