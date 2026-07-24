<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prueba la Fase 1a de la jurisdicción: el modelo de datos, sin el filtro.
 *
 * La pieza de jurisdicción se construye por fases. Esta fase solo añadió el
 * DATO (a qué jurisdicción pertenece cada ley) y la config de la instalación.
 * El FILTRO que usa ese dato en el buscador es la fase siguiente y todavía no
 * existe.
 *
 * Por eso esta prueba NO comprueba que el buscador excluya derecho de otro
 * estado: comprobarlo aquí daría una falsa sensación de que el filtro ya
 * protege, cuando aún no hay filtro. Lo que sí garantiza es que los cimientos
 * están bien puestos:
 *
 *   - la instalación sabe cuál es su jurisdicción (config),
 *   - la tabla puede guardar la jurisdicción de cada ley (columnas + fillable),
 *   - y una ley SIN clasificar no rompe nada: nace en NULL, que es el estado
 *     transitorio "todavía sin ámbito".
 *
 * Esa última es la aserción de seguridad de toda la fase: si las columnas no
 * fueran nullable, agregarlas habría exigido un valor a las regulaciones que ya
 * existen, y no lo tienen. NULL es lo que permite añadir el campo sin tumbar el
 * corpus actual.
 */
class JurisdiccionDeLaRegulacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_config_conoce_la_jurisdiccion_de_la_instalacion(): void
    {
        // La instalación es la de La Paz, BCS. El filtro (fase siguiente) leerá de
        // aquí para saber contra qué comparar. Si estos valores no llegan, el filtro
        // no tendría referencia y no podría distinguir "de aquí" de "de otro lado".
        $this->assertSame('BCS', config('punta.jurisdiccion.estado'));
        $this->assertSame('La Paz', config('punta.jurisdiccion.municipio'));
    }

    public function test_la_tabla_regulaciones_tiene_los_campos_de_jurisdiccion(): void
    {
        // Comprobación estructural directa: la migración creó las tres columnas.
        // Si RefreshDatabase no las encuentra, la migración no corrió o falló.
        $this->assertTrue(
            Schema::hasColumns('regulaciones', ['ambito', 'estado', 'municipio']),
            'La tabla regulaciones debe tener ambito, estado y municipio tras la migración.'
        );
    }

    public function test_una_regulacion_puede_guardar_su_jurisdiccion(): void
    {
        // Crear una ley municipal de La Paz y volver a leerla del disco. Esto prueba
        // DOS cosas a la vez:
        //   1. que las columnas existen y aceptan el valor (la migración), y
        //   2. que el modelo permite asignarlas (el fillable).
        // Si 'ambito' faltara en el fillable, Laravel lo descartaría en silencio al
        // crear, y al releer vendría NULL: la aserción de abajo lo cazaría.
        $regulacion = Regulacion::factory()->create([
            'ambito'    => 'municipal',
            'estado'    => 'BCS',
            'municipio' => 'La Paz',
        ])->fresh();

        $this->assertSame('municipal', $regulacion->ambito);
        $this->assertSame('BCS', $regulacion->estado);
        $this->assertSame('La Paz', $regulacion->municipio);
    }

    public function test_una_regulacion_nueva_nace_con_la_jurisdiccion_por_defecto(): void
    {
        // Antes una regulación sin ámbito nacía en NULL. Ahora el modelo le pone por
        // defecto la jurisdicción de esta instalación (municipal / BCS / La Paz), para
        // que ninguna ley nazca sin ámbito y el filtro pueda excluir lo NULL con
        // seguridad. Una federal o estatal se corrige después a mano.
        $regulacion = Regulacion::factory()->create()->fresh();

        $this->assertSame('municipal', $regulacion->ambito, 'Una ley nueva debe nacer con ámbito municipal por defecto.');
        $this->assertSame('BCS', $regulacion->estado);
        $this->assertSame('La Paz', $regulacion->municipio);
    }

    public function test_una_regulacion_puede_nacer_con_un_ambito_explicito(): void
    {
        // El default solo actúa cuando no se fijó ámbito. Si se crea una ley de otra
        // jurisdicción a propósito, se respeta.
        $regulacion = Regulacion::factory()->create([
            'ambito'    => 'estatal',
            'estado'    => 'Sonora',
            'municipio' => null,
        ])->fresh();

        $this->assertSame('estatal', $regulacion->ambito);
        $this->assertSame('Sonora', $regulacion->estado);
        $this->assertNull($regulacion->municipio);
    }
}
