<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Models\TramiteDerecho;
use App\Services\CostoBurocraticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del cálculo del Costo Burocrático.
 *
 * Según la metodología (Ecuación 4), el Costo Burocrático Directo es la suma de
 * montos CONCRETOS: derechos + copias + requisitos con costo. Por eso un derecho
 * marcado como "variable" (cuyo monto no está determinado) NO puede sumarse: si se
 * contara, se estaría metiendo al total un número que no es un costo real, y el
 * indicador dejaría de ser comparable.
 *
 * Es la parte más delicada del sistema: un error aquí desajusta en silencio el CBD,
 * el CBU y el CBT.
 */
class CostoBurocraticoTest extends TestCase
{
    use RefreshDatabase;

    private CostoBurocraticoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(CostoBurocraticoService::class);
    }

    public function test_los_derechos_fijos_se_suman_al_costo_directo(): void
    {
        $tramite = Tramite::factory()->create();

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho por licencia',
            'monto'       => 500,
            'unidad'      => 'PESOS',
            'es_variable' => false,
        ]);

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho por inspección',
            'monto'       => 300,
            'unidad'      => 'PESOS',
            'es_variable' => false,
        ]);

        $total = TramiteDerecho::totalEnPesos($tramite->derechos->toArray());

        $this->assertEqualsWithDelta(800.0, $total, 0.01, 'Los dos derechos fijos deben sumar 800.');
    }

    public function test_un_derecho_variable_no_se_suma_al_costo(): void
    {
        // El caso del predial: el monto depende de cada caso, no es una cifra fija.
        // Si se sumara, inflaría el Costo Burocrático con un dato que no es real.
        $tramite = Tramite::factory()->create();

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho fijo',
            'monto'       => 500,
            'unidad'      => 'PESOS',
            'es_variable' => false,
        ]);

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho variable (predial)',
            'monto'       => 9999, // aunque tenga un monto capturado...
            'unidad'      => 'PESOS',
            'es_variable' => true, // ...al ser variable, NO cuenta
        ]);

        $total = TramiteDerecho::totalEnPesos($tramite->derechos->toArray());

        $this->assertEqualsWithDelta(
            500.0,
            $total,
            0.01,
            'Solo debe contar el derecho fijo: el variable se excluye del total.'
        );
    }

    public function test_el_calculo_marca_que_el_tramite_tiene_costos_variables(): void
    {
        $tramite = Tramite::factory()->create();

        TramiteDerecho::create([
            'tramite_id'  => $tramite->id,
            'concepto'    => 'Derecho variable',
            'monto'       => 0,
            'unidad'      => 'PESOS',
            'es_variable' => true,
        ]);

        $costos = $this->servicio->calcularCostos($tramite->fresh());

        $this->assertTrue(
            (bool) $costos['tiene_costos_variables'],
            'El desglose debe avisar que hay montos variables, para que el usuario lo sepa.'
        );
    }

    public function test_un_tramite_sin_derechos_tiene_costo_de_derechos_cero(): void
    {
        $tramite = Tramite::factory()->create();

        $total = TramiteDerecho::totalEnPesos($tramite->derechos->toArray());

        $this->assertEqualsWithDelta(0.0, $total, 0.01);
    }

    public function test_el_calculo_devuelve_el_desglose_completo(): void
    {
        $tramite = Tramite::factory()->create();

        $costos = $this->servicio->calcularCostos($tramite);

        // El desglose alimenta la ficha del trámite y los indicadores del tablero.
        $this->assertIsArray($costos);
        $this->assertArrayHasKey('tiene_costos_variables', $costos);
    }
}
