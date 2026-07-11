<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los privilegios del rol REVISORA.
 *
 * La revisora es quien aprueba: revisa lo que envían las dependencias, observa lo
 * que hay que corregir y da el visto bueno. Su alcance es transversal —ve el módulo
 * completo, de todas las dependencias— pero tiene UN límite importante:
 *
 *   NO ve los borradores ajenos.
 *
 * Un borrador es trabajo en proceso, privado de quien lo escribe. Nadie tiene por
 * qué mirar por encima del hombro mientras se redacta: la revisora entra en escena
 * cuando la dependencia decide enviar. El admin es la única excepción.
 *
 * Ese matiz es fácil de romper sin querer al tocar los permisos, y por eso conviene
 * dejarlo fijado por escrito.
 */
class RolRevisoraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los poderes de la revisora no vienen del campo `rol` sino de sus PERMISOS
        // (tramites.aprobar, agenda.aprobar...), que viven en la base. Sin sembrar el
        // ACL, la revisora no tendría ninguno.
        $this->seed(\Database\Seeders\AclSeeder::class);
    }

    /** Una revisora con su rol y sus permisos ya enganchados. */
    private function revisora(): User
    {
        $usuario = User::factory()->create(['rol' => User::ROL_REVISORA]);

        $rol = Role::where('codigo', User::ROL_REVISORA)->firstOrFail();
        $usuario->roles()->attach($rol->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    private function enlace(): User
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $rol = Role::where('codigo', User::ROL_ENLACE)->firstOrFail();
        $usuario->roles()->attach($rol->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    // ── Alcance transversal ──────────────────────────────────────────────

    public function test_la_revisora_ve_todo_el_modulo_de_tramites(): void
    {
        $this->assertTrue($this->revisora()->veTodoElModulo('tramites'));
    }

    public function test_la_revisora_ve_varias_dependencias(): void
    {
        // Revisa lo de todas las dependencias, no solo lo de la suya.
        $this->assertTrue($this->revisora()->veVariasDependencias());
    }

    public function test_la_revisora_ve_un_tramite_enviado_de_otra_dependencia(): void
    {
        $otraDependencia = Dependencia::factory()->create();

        $tramite = Tramite::factory()->create([
            'dependencia_id' => $otraDependencia->id,
            'estatus'        => Tramite::ESTATUS_EN_OBSERVACION, // ya fue enviado
        ]);

        $this->assertTrue(
            $this->revisora()->puedeVerRegistro($tramite, 'tramites'),
            'La revisora debe poder revisar lo que le envían, sea de la dependencia que sea.'
        );
    }

    // ── El límite: los borradores ajenos ─────────────────────────────────

    public function test_la_revisora_NO_ve_el_borrador_de_otra_persona(): void
    {
        // Este es el límite que define al rol. Un borrador es trabajo en proceso: la
        // revisora entra cuando la dependencia decide enviarlo, no antes.
        $autor = $this->enlace();

        $borrador = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $autor->id,
        ]);

        $this->assertFalse(
            $this->revisora()->puedeVerRegistro($borrador, 'tramites'),
            'Ni siquiera la revisora ve un borrador ajeno: es trabajo en proceso.'
        );
    }

    public function test_el_admin_si_ve_ese_mismo_borrador(): void
    {
        // La contraparte, para que quede clara la diferencia entre los dos roles
        // transversales: el admin es la única excepción a la privacidad del borrador.
        $autor = $this->enlace();

        $borrador = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $autor->id,
        ]);

        $admin = User::factory()->create(['rol' => User::ROL_ADMIN]);

        $this->assertTrue($admin->puedeVerRegistro($borrador, 'tramites'));
    }

    // ── Frente al enlace ─────────────────────────────────────────────────

    public function test_un_enlace_no_ve_todo_el_modulo(): void
    {
        // El enlace captura lo suyo; no tiene visión transversal.
        $this->assertFalse($this->enlace()->veTodoElModulo('tramites'));
    }

    public function test_un_enlace_no_ve_varias_dependencias(): void
    {
        $this->assertFalse($this->enlace()->veVariasDependencias());
    }
}
