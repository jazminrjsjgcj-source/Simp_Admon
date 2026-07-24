<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blinda el arreglo de la factory de trámites que volvía la suite flaky.
 *
 * TramiteFactory creaba SIEMPRE una unidad (con su dependencia) al definir el
 * trámite, aunque la prueba ya le pasara unidad_id y dependencia_id. Esa
 * dependencia-fantasma nacía con código aleatorio (100-999) y chocaba de vez en
 * cuando con un código fijo de otra prueba (el 110 de IdentificadoresUnicosTest),
 * disparando una violación de unicidad intermitente.
 *
 * El arreglo hizo perezosa la creación de la unidad. Estas pruebas lo fijan.
 */
class TramiteFactorySinFantasmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_crea_dependencias_fantasma_si_se_le_pasan_los_ids(): void
    {
        $dependencia = Dependencia::factory()->create();
        $unidad = UnidadAdministrativa::factory()->create(['dependencia_id' => $dependencia->id]);

        $antes = Dependencia::count();

        Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'unidad_id'      => $unidad->id,
        ]);

        // Con el bug, la factory creaba una dependencia extra aquí (y esa, con código
        // aleatorio, era la que colisionaba). Ahora, al pasarle los ids, no crea ninguna.
        $this->assertSame(
            $antes,
            Dependencia::count(),
            'La factory no debe crear una dependencia extra cuando se le pasan los ids.'
        );
    }

    public function test_sin_ids_crea_una_unidad_y_su_dependencia_consistentes(): void
    {
        // El otro lado: si NO se pasan ids, la factory sí crea la unidad, y el
        // dependencia_id del trámite debe ser el de esa unidad (no otro).
        $tramite = Tramite::factory()->create();

        $unidad = UnidadAdministrativa::findOrFail($tramite->unidad_id);

        $this->assertSame(
            $unidad->dependencia_id,
            $tramite->dependencia_id,
            'El dependencia_id del trámite debe coincidir con el de su unidad.'
        );
    }

    public function test_acepta_unidad_nula_pero_deja_una_dependencia_valida(): void
    {
        // El caso "sin unidad" (que usa HomoclaveTest): se puede crear un trámite sin
        // unidad, pero como dependencia_id es NOT NULL, debe quedar una dependencia
        // válida igualmente. Sin este cuidado, el arreglo del flaky rompía ese test.
        $tramite = Tramite::factory()->create(['unidad_id' => null]);

        $this->assertNull($tramite->unidad_id, 'La unidad debe poder quedar en null.');
        $this->assertNotNull($tramite->dependencia_id, 'La dependencia es obligatoria: debe quedar una válida.');
    }
}
