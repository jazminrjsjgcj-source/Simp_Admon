<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los privilegios del rol ADMIN.
 *
 * El admin es la única figura que atraviesa todas las barreras del sistema:
 *
 *   - Ve y edita registros de CUALQUIER dependencia (los demás roles solo ven la
 *     suya).
 *   - Ve los BORRADORES de otras personas (los borradores son trabajo en proceso y
 *     son privados de quien los creó; ni siquiera la revisora los ve).
 *   - Puede eliminar trámites, acciones de agenda y propuestas.
 *
 * Estas pruebas fijan esos límites por escrito: si alguien cambia la lógica de
 * permisos y sin querer abre (o cierra) una puerta, aquí se nota.
 */
class RolAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => User::ROL_ADMIN]);
    }

    private function enlaceDe(Dependencia $dependencia): User
    {
        return User::factory()->create([
            'rol'            => User::ROL_ENLACE,
            'dependencia_id' => $dependencia->id,
        ]);
    }

    // ── Ver: el admin no tiene fronteras ─────────────────────────────────

    public function test_el_admin_ve_registros_de_cualquier_dependencia(): void
    {
        $otraDependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->completado()->create([
            'dependencia_id' => $otraDependencia->id,
        ]);

        $this->assertTrue(
            $this->admin()->puedeVerRegistro($tramite, 'tramites'),
            'El admin ve los registros de cualquier dependencia.'
        );
    }

    public function test_el_admin_ve_los_borradores_de_otras_personas(): void
    {
        // Un borrador es trabajo en proceso y es PRIVADO de quien lo creó: ni la
        // revisora lo ve. El admin es la única excepción.
        $otroUsuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $borradorAjeno = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $otroUsuario->id,
        ]);

        $this->assertTrue(
            $this->admin()->puedeVerRegistro($borradorAjeno, 'tramites'),
            'El admin sí puede ver un borrador ajeno.'
        );
    }

    public function test_un_enlace_no_ve_el_borrador_de_otra_persona(): void
    {
        // La contraparte de la prueba anterior: el borrador ajeno está cerrado para
        // los demás. Si esta prueba fallara, se estaría filtrando trabajo privado.
        $autor = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $otro  = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $borrador = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $autor->id,
        ]);

        $this->assertFalse(
            $otro->puedeVerRegistro($borrador, 'tramites'),
            'El borrador de otra persona NO debe verse.'
        );
    }

    public function test_el_autor_si_ve_su_propio_borrador(): void
    {
        $autor = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $borrador = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $autor->id,
        ]);

        $this->assertTrue($autor->puedeVerRegistro($borrador, 'tramites'));
    }

    // ── Alcance: el admin ve todos los módulos y dependencias ────────────

    public function test_el_admin_ve_todo_el_modulo(): void
    {
        $this->assertTrue($this->admin()->veTodoElModulo('tramites'));
        $this->assertTrue($this->admin()->veTodoElModulo('agenda'));
    }

    public function test_el_admin_ve_varias_dependencias(): void
    {
        $this->assertTrue($this->admin()->veVariasDependencias());
    }

    public function test_un_enlace_no_ve_varias_dependencias(): void
    {
        // El enlace solo trabaja con lo suyo.
        $enlace = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->assertFalse($enlace->veVariasDependencias());
    }

    // ── Editar y eliminar ────────────────────────────────────────────────

    public function test_el_admin_edita_tramites_de_otras_dependencias(): void
    {
        $otraDependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->create(['dependencia_id' => $otraDependencia->id]);

        $this->assertTrue($this->admin()->puedeEditarTramite($tramite));
    }

    public function test_el_admin_puede_eliminar_un_tramite(): void
    {
        $tramite = Tramite::factory()->create();

        $this->assertTrue($this->admin()->puedeEliminarTramite($tramite));
    }

    public function test_el_admin_puede_eliminar_una_accion_de_agenda(): void
    {
        $accion = AccionAgenda::factory()->create();

        $this->assertTrue($this->admin()->puedeEliminarAgenda($accion));
    }

    public function test_un_enlace_no_puede_eliminar_el_tramite_de_otra_dependencia(): void
    {
        $dependenciaAjena = Dependencia::factory()->create();
        $tramiteAjeno = Tramite::factory()->create(['dependencia_id' => $dependenciaAjena->id]);

        $enlace = $this->enlaceDe(Dependencia::factory()->create());

        $this->assertFalse(
            $enlace->puedeEliminarTramite($tramiteAjeno),
            'Un enlace no debe poder borrar lo de otra dependencia.'
        );
    }
}
