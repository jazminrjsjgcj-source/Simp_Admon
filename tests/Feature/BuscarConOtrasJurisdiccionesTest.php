<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba end-to-end del "ver otras jurisdicciones" en el buscador: ruta →
 * controlador → BuscadorService → vista Blade.
 *
 * Es la única parte de la Fase 2 que se prueba a nivel HTTP y no de servicio,
 * porque lo que se verifica aquí es el CABLEADO completo: que el checkbox del
 * formulario (otras_jurisdicciones=1) llegue al controlador, este lo pase a
 * buscar(), el filtro se apague, y la marca "Otra jurisdicción" se pinte en la
 * página. Si cualquiera de esos eslabones se rompe, esta prueba lo caza.
 *
 * La instalación de pruebas está configurada como La Paz, BCS. El asistente se
 * apaga para que la búsqueda no intente llamar a ningún modelo externo.
 */
class BuscarConOtrasJurisdiccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La ruta 'buscar' exige sesión. Cualquier usuario autenticado sirve (sin rol).
        $this->actingAs(User::factory()->create());

        // Que la búsqueda no llame al asistente (evita salidas a internet en la prueba).
        config(['punta.asistente.activo' => false]);
    }

    /** Crea una ley con un artículo buscable sobre el predial, con un marcador en el texto. */
    private function leyDePredial(string $nombre, string $marcador, ?string $ambito, ?string $estado = null, ?string $municipio = null): void
    {
        $ley = Regulacion::factory()->create([
            'nombre'    => $nombre,
            'ambito'    => $ambito,
            'estado'    => $estado,
            'municipio' => $municipio,
        ]);

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '1',
            'texto'         => "El impuesto predial: {$marcador}. Se calcula sobre el valor catastral.",
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    private function sembrarDosLeyes(): void
    {
        $this->leyDePredial('Ley de Hacienda para el Municipio de La Paz', 'MARCALAPAZ', 'municipal', 'BCS', 'La Paz');
        $this->leyDePredial('Ley de Hacienda del Estado de Sonora', 'MARCASONORA', 'estatal', 'Sonora');
    }

    public function test_sin_el_toggle_no_aparece_la_ley_de_otro_estado(): void
    {
        $this->sembrarDosLeyes();

        // todos=1 fuerza el modo completo (corren todas las fuentes, entre ellas el articulado).
        $respuesta = $this->get(route('buscar', ['q' => 'predial', 'todos' => 1]));

        $respuesta->assertOk();
        $respuesta->assertSee('MARCALAPAZ');           // la de La Paz sí aparece
        $respuesta->assertDontSee('MARCASONORA');      // la de Sonora queda filtrada
        $respuesta->assertDontSee('Otra jurisdicción'); // y no hay ninguna marca
    }

    public function test_con_el_toggle_aparece_la_ley_de_otro_estado_marcada(): void
    {
        $this->sembrarDosLeyes();

        $respuesta = $this->get(route('buscar', ['q' => 'predial', 'todos' => 1, 'otras_jurisdicciones' => 1]));

        $respuesta->assertOk();
        $respuesta->assertSee('MARCALAPAZ');            // la local sigue apareciendo
        $respuesta->assertSee('MARCASONORA');           // ahora la de Sonora también
        $respuesta->assertSee('Otra jurisdicción');     // marcada como fuera de jurisdicción
    }

    public function test_el_checkbox_esta_en_el_formulario(): void
    {
        // La página del buscador ofrece el control, apagado por defecto.
        $respuesta = $this->get(route('buscar'));

        $respuesta->assertOk();
        $respuesta->assertSee('otras_jurisdicciones');            // el campo del checkbox
        $respuesta->assertSee('Incluir leyes de otras jurisdicciones'); // su etiqueta
    }
}
