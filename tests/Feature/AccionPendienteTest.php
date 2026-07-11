<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de las acciones de agenda que ESPERAN a su trámite.
 *
 * Desde la agenda se puede registrar un trámite nuevo y, en el mismo paso, la acción
 * de mejora sobre él. Pero ese trámite todavía tiene que pasar por revisión y firma
 * antes de existir de forma oficial.
 *
 * Mientras eso no ocurre, la acción queda INACTIVA:
 *   - no aparece en los listados ajenos, ni en el calendario, ni en los indicadores;
 *   - su autor sí la ve, marcada como pendiente (si no, habría escrito algo que no
 *     aparece en ninguna parte y no sabría por qué);
 *   - se activa SOLA en cuanto el trámite queda completado.
 *
 * La idea de fondo: no se puede dar por comprometida la mejora de un trámite que
 * formalmente todavía no existe.
 */
class AccionPendienteTest extends TestCase
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

    private function datosAccion(Tramite $tramite): array
    {
        return [
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $tramite->dependencia_id,
            'tipo'           => 'simplificacion',
            'descripcion'    => 'Reducir los requisitos del trámite.',
            'meta'           => 'Pasar de ocho a cuatro requisitos.',
        ];
    }

    // ── Nace inactiva si el trámite no está listo ────────────────────────

    public function test_una_accion_sobre_un_tramite_en_borrador_nace_inactiva(): void
    {
        $tramiteBorrador = Tramite::factory()->create([
            'estatus' => Tramite::ESTATUS_BORRADOR,
        ]);

        $accion = $this->servicio->crearAccion(
            $this->datosAccion($tramiteBorrador),
            $this->autor->id
        );

        $this->assertFalse((bool) $accion->activa);
        $this->assertTrue($accion->estaPendienteDelTramite());
    }

    public function test_una_accion_sobre_un_tramite_en_revision_nace_inactiva(): void
    {
        // Enviado no es lo mismo que firmado: sigue sin ser oficial.
        $tramite = Tramite::factory()->create([
            'estatus' => Tramite::ESTATUS_EN_OBSERVACION,
        ]);

        $accion = $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);

        $this->assertFalse((bool) $accion->activa);
    }

    public function test_una_accion_sobre_un_tramite_completado_nace_activa(): void
    {
        // El camino normal: el trámite ya existe formalmente.
        $tramite = Tramite::factory()->completado()->create();

        $accion = $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);

        $this->assertTrue((bool) $accion->activa);
        $this->assertFalse($accion->estaPendienteDelTramite());
    }

    // ── Se activa sola cuando el trámite se completa ─────────────────────

    public function test_al_completarse_el_tramite_su_accion_se_activa_sola(): void
    {
        // Este es el corazón del mecanismo: nadie tiene que acordarse de activarla.
        $tramite = Tramite::factory()->create(['estatus' => Tramite::ESTATUS_EN_FIRMA]);

        $accion = $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);
        $this->assertFalse((bool) $accion->activa, 'Nace inactiva.');

        // El trámite termina su camino y queda firmado.
        $tramite->update(['estatus' => Tramite::ESTATUS_COMPLETADO]);

        $this->assertTrue(
            (bool) $accion->fresh()->activa,
            'Al completarse el trámite, la acción que lo esperaba debe activarse sola.'
        );
    }

    public function test_editar_un_tramite_ya_completado_no_reactiva_nada(): void
    {
        // Salvaguarda: el observer solo actúa cuando el estatus ACABA de cambiar a
        // completado, no en cada guardado de un trámite que ya lo estaba.
        $tramite = Tramite::factory()->completado()->create();

        $accion = $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);
        $accion->update(['activa' => false]); // se desactiva a mano, por lo que sea

        $tramite->update(['objetivo' => 'Un objetivo distinto']); // edición cualquiera

        $this->assertFalse(
            (bool) $accion->fresh()->activa,
            'Una edición cualquiera no debe reactivar acciones.'
        );
    }

    // ── Visibilidad ──────────────────────────────────────────────────────

    public function test_las_acciones_inactivas_no_salen_en_el_listado_general(): void
    {
        $tramite = Tramite::factory()->create(['estatus' => Tramite::ESTATUS_BORRADOR]);
        $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);

        $this->assertSame(0, AccionAgenda::activas()->count(), 'Una acción pendiente no cuenta.');
        $this->assertSame(1, AccionAgenda::count(), 'Pero existe en la base.');
    }

    public function test_el_autor_si_ve_su_propia_accion_pendiente(): void
    {
        // Si no la viera, habría escrito algo que no aparece en ninguna parte y no
        // sabría por qué. La ve, marcada como pendiente del trámite.
        $tramite = Tramite::factory()->create(['estatus' => Tramite::ESTATUS_BORRADOR]);
        $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);

        $this->assertSame(
            1,
            AccionAgenda::visiblesPara($this->autor)->count(),
            'El autor sí ve su acción, aunque esté esperando al trámite.'
        );
    }

    public function test_el_listado_de_agenda_no_muestra_la_accion_pendiente_a_otros(): void
    {
        // Prueba de extremo a extremo sobre la pantalla real, no solo sobre el scope.
        $this->seed(\Database\Seeders\AclSeeder::class);

        $tramite = Tramite::factory()->create(['estatus' => Tramite::ESTATUS_BORRADOR]);

        $autor = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $rol   = Role::where('codigo', User::ROL_ENLACE)->firstOrFail();
        $autor->roles()->attach($rol->id);
        $autor->olvidarPermisosCache();

        $accion = $this->servicio->crearAccion([
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $tramite->dependencia_id,
            'tipo'           => 'simplificacion',
            'descripcion'    => 'Accion que espera al tramite',
        ], $autor->id);

        // Otra persona de la misma dependencia abre el listado.
        $otro = User::factory()->create([
            'rol'            => User::ROL_ENLACE,
            'dependencia_id' => $tramite->dependencia_id,
        ]);
        $otro->roles()->attach($rol->id);
        $otro->olvidarPermisosCache();

        $this->actingAs($otro)
            ->get(route('agenda.index'))
            ->assertOk()
            ->assertDontSee('Accion que espera al tramite');
    }

    public function test_otra_persona_no_ve_la_accion_pendiente(): void
    {
        $tramite = Tramite::factory()->create(['estatus' => Tramite::ESTATUS_BORRADOR]);
        $this->servicio->crearAccion($this->datosAccion($tramite), $this->autor->id);

        $otraPersona = User::factory()->create();

        $this->assertSame(
            0,
            AccionAgenda::visiblesPara($otraPersona)->count(),
            'Para los demás, la acción pendiente no existe todavía.'
        );
    }
}
