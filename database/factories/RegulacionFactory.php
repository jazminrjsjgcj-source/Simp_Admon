<?php

namespace Database\Factories;

use App\Models\Dependencia;
use App\Models\Regulacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea regulaciones para las pruebas.
 *
 * Por defecto la regulación nace SIN convertir: no tiene archivo original ni
 * Markdown, y su conversion_estatus es 'pendiente'. Así es como nace de verdad
 * en el sistema, justo después de que el usuario sube el archivo y antes de que
 * el job de conversión lo procese.
 *
 * Para probar los pasos siguientes hay dos estados:
 *
 *   convertida()   → tiene Markdown en disco y conversion_estatus = 'listo'.
 *                    Es el único estado en el que obtenerContenidoMarkdown()
 *                    devuelve algo (lo exige conversionListaParaCitar()).
 *
 *   conError()     → la conversión falló. Sirve para probar que el sistema no
 *                    intenta estructurar un documento que nunca se pudo leer.
 *
 * @extends Factory<Regulacion>
 */
class RegulacionFactory extends Factory
{
    protected $model = Regulacion::class;

    public function definition(): array
    {
        return [
            'nombre'             => 'Reglamento de ' . fake()->unique()->words(3, true),
            'tipo'               => 'reglamento',
            'dependencia_id'     => Dependencia::factory(),
            'estatus'            => 'vigente',
            'fecha_publicacion'  => now()->subYear()->toDateString(),
            'conversion_estatus' => Regulacion::CONVERSION_PENDIENTE,
            'archivo_original'   => null,
            'archivo_markdown'   => null,
            'extension_original' => null,
            'estructurada'       => false,
        ];
    }

    /**
     * Regulación ya convertida a Markdown.
     *
     * OJO: esto solo pone la RUTA en la base. El archivo hay que escribirlo en el
     * disco falso desde la prueba:
     *
     *   Storage::fake('local');
     *   $regulacion = Regulacion::factory()->convertida()->create();
     *   Storage::disk('local')->put($regulacion->archivo_markdown, $markdown);
     *
     * Se hace así a propósito: cada prueba decide QUÉ Markdown quiere probar.
     */
    public function convertida(): static
    {
        return $this->state(fn () => [
            'conversion_estatus' => Regulacion::CONVERSION_LISTO,
            'archivo_markdown'   => 'regulaciones/markdown/' . fake()->unique()->uuid() . '.md',
            'archivo_original'   => 'regulaciones/originales/' . fake()->unique()->uuid() . '.pdf',
            'extension_original' => 'pdf',
        ]);
    }

    /** La conversión del archivo falló: no hay Markdown que estructurar ni que citar. */
    public function conError(): static
    {
        return $this->state(fn () => [
            'conversion_estatus' => Regulacion::CONVERSION_ERROR,
            'conversion_error'   => 'No se pudo extraer texto legible del PDF.',
            'archivo_markdown'   => null,
        ]);
    }
}
