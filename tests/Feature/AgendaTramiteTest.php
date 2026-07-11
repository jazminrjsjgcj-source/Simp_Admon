<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del vínculo entre una acción de la Agenda SyD y su trámite.
 *
 * Una acción de simplificación o digitalización se hace SOBRE un trámite que ya
 * existe formalmente. Por eso el buscador de la agenda solo debe ofrecer trámites
 * firmados o completados: no tiene sentido programar la mejora de un borrador.
 */
class AgendaTramiteTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create();
    }

    public function test_una_accion_conoce_su_tramite(): void
    {
        $accion = AccionAgenda::factory()->create();

        $this->assertNotNull($accion->tramite);
        $this->assertSame($accion->tramite_id, $accion->tramite->id);
    }

    public function test_el_buscador_de_la_agenda_ofrece_los_tramites_completados(): void
    {
        $completado = Tramite::factory()->completado()->create([
            'nombre_oficial' => 'Licencia de funcionamiento comercial',
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->getJson(route('api.tramites.buscar', ['q' => 'Licencia']));

        $respuesta->assertOk();
        $this->assertContains(
            $completado->id,
            collect($respuesta->json('resultados'))->pluck('id')->all(),
            'Un trámite completado debe aparecer en el buscador de la agenda.'
        );
    }

    public function test_el_buscador_de_la_agenda_no_ofrece_borradores(): void
    {
        // Un borrador no existe formalmente: no se puede programar su mejora.
        $borrador = Tramite::factory()->create([
            'nombre_oficial' => 'Licencia en borrador',
            'estatus'        => Tramite::ESTATUS_BORRADOR,
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->getJson(route('api.tramites.buscar', ['q' => 'Licencia']));

        $respuesta->assertOk();
        $this->assertNotContains(
            $borrador->id,
            collect($respuesta->json('resultados'))->pluck('id')->all(),
            'Un borrador NO debe aparecer en el buscador de la agenda.'
        );
    }

    public function test_el_buscador_de_la_agenda_no_ofrece_tramites_en_revision(): void
    {
        $enRevision = Tramite::factory()->create([
            'nombre_oficial' => 'Licencia en revision',
            'estatus'        => Tramite::ESTATUS_EN_OBSERVACION,
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->getJson(route('api.tramites.buscar', ['q' => 'Licencia']));

        $respuesta->assertOk();
        $this->assertNotContains(
            $enRevision->id,
            collect($respuesta->json('resultados'))->pluck('id')->all(),
            'Un trámite que sigue en revisión no está listo para la agenda.'
        );
    }

    public function test_el_buscador_necesita_al_menos_dos_letras(): void
    {
        Tramite::factory()->completado()->create(['nombre_oficial' => 'Licencia']);

        $respuesta = $this->actingAs($this->usuario())
            ->getJson(route('api.tramites.buscar', ['q' => 'L']));

        $respuesta->assertOk();
        $this->assertSame([], $respuesta->json('resultados'), 'Con una sola letra no se busca.');
    }
}
