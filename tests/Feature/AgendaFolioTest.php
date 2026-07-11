<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Tramite;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del folio de las acciones de la Agenda SyD.
 *
 * El folio es el identificador oficial de la acción. Formato:
 *   LPZ-{SIM|DIG|SYD}-{siglas dependencia}-{año}-{NNN}
 *
 * Dos reglas importantes:
 *   1. Solo se asigna AL ENVIAR a revisión, nunca al guardar un borrador. Así no se
 *      gastan números de la serie oficial en trabajo que puede borrarse.
 *   2. El tipo del folio depende del tipo de acción: simplificación (SIM),
 *      digitalización (DIG) o ambas (SYD).
 */
class AgendaFolioTest extends TestCase
{
    use RefreshDatabase;

    private AgendaService $servicio;
    private User $autor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(AgendaService::class);
        $this->autor    = User::factory()->create();
    }

    /** Datos mínimos de una acción, vinculada a un trámite ya completado. */
    private function datosAccion(string $tipo = 'simplificacion'): array
    {
        $tramite = Tramite::factory()->completado()->create();

        return [
            'tramite_id'       => $tramite->id,
            'dependencia_id'   => $tramite->dependencia_id,
            'unidad_id'        => $tramite->unidad_id,
            'tipo'             => $tipo,
            'descripcion'      => 'Reducir requisitos del trámite.',
            'meta'             => 'Pasar de 8 a 4 requisitos.',
            'responsable'      => 'Jefa de la unidad',
            'fecha_inicio'     => now()->toDateString(),
            'fecha_compromiso' => now()->addMonths(3)->toDateString(),
        ];
    }

    public function test_una_accion_guardada_como_borrador_no_recibe_folio(): void
    {
        // El folio es un número de la serie oficial: un borrador que quizá se borre
        // no debe consumirlo, o la numeración quedaría con huecos.
        $accion = $this->servicio->crearAccion($this->datosAccion(), $this->autor->id, esEnvio: false);

        $this->assertNull($accion->folio, 'Un borrador no debe llevar folio.');
        $this->assertSame(AccionAgenda::ESTATUS_BORRADOR, $accion->estatus);
    }

    public function test_al_enviar_a_revision_se_asigna_el_folio(): void
    {
        $accion = $this->servicio->crearAccion($this->datosAccion(), $this->autor->id, esEnvio: true);

        $this->assertNotNull($accion->folio, 'Al enviar a revisión, la acción debe recibir su folio.');
        $this->assertSame(AccionAgenda::ESTATUS_EN_OBSERVACION, $accion->estatus);
    }

    public function test_una_accion_de_simplificacion_lleva_el_tipo_SIM(): void
    {
        $accion = $this->servicio->crearAccion(
            $this->datosAccion('simplificacion'),
            $this->autor->id,
            esEnvio: true
        );

        $this->assertStringContainsString('-SIM-', $accion->folio);
    }

    public function test_una_accion_de_digitalizacion_lleva_el_tipo_DIG(): void
    {
        $accion = $this->servicio->crearAccion(
            $this->datosAccion('digitalizacion'),
            $this->autor->id,
            esEnvio: true
        );

        $this->assertStringContainsString('-DIG-', $accion->folio);
    }

    public function test_una_accion_de_ambas_lleva_el_tipo_SYD(): void
    {
        // "Ambas" = simplificación y digitalización a la vez.
        $accion = $this->servicio->crearAccion(
            $this->datosAccion('ambas'),
            $this->autor->id,
            esEnvio: true
        );

        $this->assertStringContainsString('-SYD-', $accion->folio);
    }

    public function test_el_folio_empieza_con_el_prefijo_del_municipio(): void
    {
        $accion = $this->servicio->crearAccion($this->datosAccion(), $this->autor->id, esEnvio: true);

        $this->assertStringStartsWith('LPZ-', $accion->folio);
    }

    public function test_el_folio_incluye_el_anio_en_curso(): void
    {
        $accion = $this->servicio->crearAccion($this->datosAccion(), $this->autor->id, esEnvio: true);

        $this->assertStringContainsString('-' . now()->year . '-', $accion->folio);
    }
}
