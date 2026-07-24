<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba la señal de "ley desactualizada": el helper estaDesactualizada() y el
 * scope desactualizadas(), que detectan leyes estructuradas con una versión
 * anterior del pipeline (o desconocida) para que no fallen en silencio.
 */
class RegulacionDesactualizadaTest extends TestCase
{
    use RefreshDatabase;

    private function leyConVersion(?int $version): Regulacion
    {
        return Regulacion::factory()->create(['pipeline_version' => $version]);
    }

    public function test_esta_al_dia_si_tiene_la_version_vigente(): void
    {
        $ley = $this->leyConVersion(Regulacion::PIPELINE_VERSION);

        $this->assertFalse($ley->estaDesactualizada());
    }

    public function test_esta_desactualizada_si_su_version_es_menor(): void
    {
        $ley = $this->leyConVersion(Regulacion::PIPELINE_VERSION - 1);

        $this->assertTrue($ley->estaDesactualizada());
    }

    public function test_esta_desactualizada_si_no_tiene_version(): void
    {
        // NULL = estructurada antes de que existiera el registro, o nunca bien. Se trata
        // como desactualizada por seguridad.
        $ley = $this->leyConVersion(null);

        $this->assertTrue($ley->estaDesactualizada());
    }

    public function test_el_scope_trae_solo_las_desactualizadas(): void
    {
        $alDia = $this->leyConVersion(Regulacion::PIPELINE_VERSION);
        $vieja = $this->leyConVersion(Regulacion::PIPELINE_VERSION - 1);
        $nula  = $this->leyConVersion(null);

        $ids = Regulacion::desactualizadas()->pluck('id');

        $this->assertContains($vieja->id, $ids);
        $this->assertContains($nula->id, $ids);
        $this->assertNotContains($alDia->id, $ids, 'Una ley al día no debe salir en el scope.');
    }
}
