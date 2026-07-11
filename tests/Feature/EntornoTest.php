<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\UnidadAdministrativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba de humo: comprueba que el entorno de pruebas está bien montado.
 *
 * No prueba reglas de negocio; solo que:
 *   - las pruebas corren contra PostgreSQL (no contra SQLite),
 *   - las migraciones se aplican,
 *   - las factories crean datos válidos.
 *
 * Si esta prueba falla, ninguna de las demás tiene sentido: primero hay que
 * arreglar el entorno.
 */
class EntornoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_pruebas_corren_contra_postgresql(): void
    {
        // Si esto falla, phpunit.xml está apuntando a otro motor y las pruebas no
        // servirían: el sistema se despliega en PostgreSQL y los motores difieren.
        $this->assertSame('pgsql', config('database.default'));
    }

    public function test_la_factory_de_dependencia_crea_una_con_siglas(): void
    {
        $dependencia = Dependencia::factory()->create();

        $this->assertNotNull($dependencia->id);
        $this->assertNotEmpty($dependencia->siglas, 'La dependencia debe nacer con siglas.');
    }

    public function test_la_factory_de_unidad_crea_tambien_su_dependencia(): void
    {
        $unidad = UnidadAdministrativa::factory()->create();

        $this->assertNotNull($unidad->dependencia_id);
        $this->assertNotEmpty($unidad->siglas);
    }

    public function test_la_factory_de_tramite_crea_un_borrador_con_dependencia_y_unidad(): void
    {
        $tramite = Tramite::factory()->create();

        $this->assertSame(Tramite::ESTATUS_BORRADOR, $tramite->estatus);
        $this->assertNotNull($tramite->dependencia_id);
        $this->assertNotNull($tramite->unidad_id, 'Sin unidad no se puede formar la homoclave.');
    }

    public function test_la_factory_de_accion_de_agenda_nace_como_borrador_sin_folio(): void
    {
        $accion = AccionAgenda::factory()->create();

        $this->assertSame(AccionAgenda::ESTATUS_BORRADOR, $accion->estatus);
        $this->assertNull($accion->folio, 'El folio se asigna al enviar a revisión, no al guardar el borrador.');
        $this->assertNotNull($accion->tramite_id);
    }
}
