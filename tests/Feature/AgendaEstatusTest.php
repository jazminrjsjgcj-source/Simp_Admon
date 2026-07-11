<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del flujo de estados de una acción de la Agenda SyD.
 *
 * El ciclo previsto es:
 *   borrador → en observación → en firma → (completado)
 * con la vuelta atrás:
 *   en corrección → en observación
 *
 * Las transiciones permitidas están declaradas en AccionAgenda::TRANSICIONES, y
 * puedeTransicionarA() es la que decide si un cambio de estado es legal.
 */
class AgendaEstatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_borrador_puede_enviarse_a_revision(): void
    {
        $accion = AccionAgenda::factory()->create(['estatus' => AccionAgenda::ESTATUS_BORRADOR]);

        $this->assertTrue($accion->puedeTransicionarA(AccionAgenda::ESTATUS_EN_OBSERVACION));
    }

    public function test_un_borrador_no_puede_saltar_directo_a_firma(): void
    {
        // No se puede firmar algo que nadie ha revisado: hay que pasar por revisión.
        $accion = AccionAgenda::factory()->create(['estatus' => AccionAgenda::ESTATUS_BORRADOR]);

        $this->assertFalse($accion->puedeTransicionarA(AccionAgenda::ESTATUS_EN_FIRMA));
    }

    public function test_una_accion_en_observacion_puede_pasar_a_firma(): void
    {
        $accion = AccionAgenda::factory()->create(['estatus' => AccionAgenda::ESTATUS_EN_OBSERVACION]);

        $this->assertTrue($accion->puedeTransicionarA(AccionAgenda::ESTATUS_EN_FIRMA));
    }

    public function test_una_accion_en_correccion_vuelve_a_revision(): void
    {
        // Cuando la revisora devuelve la acción con observaciones, el enlace la
        // corrige y la manda de nuevo a revisión.
        $accion = AccionAgenda::factory()->create(['estatus' => AccionAgenda::ESTATUS_EN_CORRECCION]);

        $this->assertTrue($accion->puedeTransicionarA(AccionAgenda::ESTATUS_EN_OBSERVACION));
    }

    public function test_una_accion_completada_ya_no_cambia_de_estado(): void
    {
        $accion = AccionAgenda::factory()->completada()->create();

        foreach (AccionAgenda::ESTATUS_TODOS as $destino) {
            $this->assertFalse(
                $accion->puedeTransicionarA($destino),
                "Una acción completada no debería poder pasar a {$destino}."
            );
        }
    }

    public function test_desde_en_firma_no_hay_transicion_manual_a_completado(): void
    {
        // Deja constancia de una decisión de diseño: "completado" NO se alcanza con
        // el endpoint manual de cambio de estatus (TRANSICIONES lo deja vacío desde
        // 'en_firma'). Si en el futuro se quiere cerrar la acción desde la pantalla,
        // hay que añadir esa transición a propósito — esta prueba fallará y avisará.
        $accion = AccionAgenda::factory()->create(['estatus' => AccionAgenda::ESTATUS_EN_FIRMA]);

        $this->assertFalse($accion->puedeTransicionarA(AccionAgenda::ESTATUS_COMPLETADO));
    }
}
