<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas del módulo ACL (admin/acl): roles, sus permisos y a quién se le asignan.
 *
 * Es la otra mitad de la administración. Mientras la pantalla de usuarios da
 * permisos DIRECTOS a una persona, esta trabaja con ROLES: se define qué puede
 * hacer un rol y luego se le cuelga a quien corresponda. Cambiar un rol afecta de
 * golpe a todos los que lo tienen, y por eso conviene tenerlo fijado por escrito.
 *
 * Lo que se protege aquí:
 *
 *   1. Solo un admin llega a estas pantallas.
 *   2. Asignar un rol tiene EFECTO REAL: el usuario pasa a poder entrar donde antes
 *      no podía. No basta con que quede la fila en la tabla.
 *   3. Quitar un rol lo retira de verdad, y quitar permisos de un rol se los quita
 *      a todos los que lo tienen.
 *   4. Cada cambio queda registrado en la bitácora ACL. Un sistema donde alguien se
 *      puede dar privilegios sin dejar rastro no es auditable.
 */
class AdminAclTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Los roles y permisos viven en la base, no en el código.
        $this->seed(\Database\Seeders\AclSeeder::class);

        $this->admin = User::factory()->create([
            'rol'    => User::ROL_ADMIN,
            'activo' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Acceso
    // ─────────────────────────────────────────────────────────────

    public function test_el_admin_entra_a_las_pantallas_de_acl(): void
    {
        foreach (['admin.acl.index', 'admin.acl.usuarios', 'admin.acl.bitacora'] as $ruta) {
            $this->actingAs($this->admin)
                ->get(route($ruta))
                ->assertOk();
        }
    }

    public function test_quien_no_es_admin_no_entra_al_acl(): void
    {
        $enlace = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->actingAs($enlace)
            ->get(route('admin.acl.index'))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────
    //  Asignar y quitar roles a un usuario
    // ─────────────────────────────────────────────────────────────

    public function test_asignar_un_rol_a_un_usuario_lo_deja_registrado(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $rol     = Role::where('codigo', 'revisora')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [$rol->id]])
            ->assertRedirect(route('admin.acl.usuarios'));

        $this->assertEqualsCanonicalizing(
            [$rol->id],
            $usuario->roles()->pluck('roles.id')->all()
        );
    }

    /**
     * Lo que de verdad importa no es la fila en la tabla, sino que el permiso
     * FUNCIONE: el middleware consulta primero los roles ACL, así que un enlace con
     * el rol "admin" asignado debe poder entrar a la administración.
     */
    public function test_asignar_el_rol_admin_da_acceso_real_a_la_administracion(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        // Antes: no pasa.
        $this->actingAs($usuario)
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();

        $rolAdmin = Role::where('codigo', 'admin')->firstOrFail();
        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [$rolAdmin->id]]);

        // Después: entra.
        $this->actingAs($usuario->fresh())
            ->get(route('admin.usuarios.index'))
            ->assertOk();
    }

    public function test_quitar_un_rol_lo_retira(): void
    {
        $usuario  = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $revisora = Role::where('codigo', 'revisora')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [$revisora->id]]);
        $this->assertCount(1, $usuario->roles()->get());

        // Sin la clave 'roles', el formulario está diciendo "ninguno marcado".
        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), []);

        $this->assertCount(0, $usuario->roles()->get());
    }

    public function test_no_se_puede_asignar_un_rol_que_no_existe(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [999999]])
            ->assertSessionHasErrors('roles.0');

        $this->assertCount(0, $usuario->roles()->get());
    }

    // ─────────────────────────────────────────────────────────────
    //  Permisos de un rol
    // ─────────────────────────────────────────────────────────────

    public function test_cambiar_los_permisos_de_un_rol_agrega_y_quita(): void
    {
        $rol = Role::where('codigo', 'revisora')->firstOrFail();

        $permisos = Permiso::query()->limit(3)->pluck('id')->all();
        $primera  = array_slice($permisos, 0, 2);
        $segunda  = array_slice($permisos, 2, 1);

        $this->actingAs($this->admin)
            ->put(route('admin.acl.actualizar-rol', $rol), ['permisos' => $primera])
            ->assertRedirect(route('admin.acl.index'));

        $this->assertEqualsCanonicalizing($primera, $rol->permisos()->pluck('permisos.id')->all());

        $this->actingAs($this->admin)
            ->put(route('admin.acl.actualizar-rol', $rol), ['permisos' => $segunda]);

        $this->assertEqualsCanonicalizing(
            $segunda,
            $rol->permisos()->pluck('permisos.id')->all(),
            'Los permisos desmarcados deberían haberse retirado del rol.'
        );
    }

    /**
     * Quitarle un permiso a un rol tiene que llegar a la gente que lo tiene puesto.
     * Si el sistema cachea permisos y no invalida ese caché al cambiarlos, alguien
     * seguiría entrando con un privilegio ya revocado.
     */
    public function test_quitar_un_permiso_del_rol_se_lo_quita_a_quien_lo_tiene(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $rol     = Role::where('codigo', 'revisora')->firstOrFail();
        $permiso = Permiso::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.acl.actualizar-rol', $rol), ['permisos' => [$permiso->id]]);
        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [$rol->id]]);

        $this->assertTrue(
            $usuario->fresh()->tienePermiso($permiso->codigo),
            'Con el rol puesto, el usuario debería tener el permiso.'
        );

        // Se le vacían los permisos al rol.
        $this->actingAs($this->admin)
            ->put(route('admin.acl.actualizar-rol', $rol), ['permisos' => []]);

        $this->assertFalse(
            $usuario->fresh()->tienePermiso($permiso->codigo),
            'Al quitar el permiso del rol, el usuario ya no debería tenerlo.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Rastro auditable
    // ─────────────────────────────────────────────────────────────

    public function test_cada_cambio_de_roles_queda_en_la_bitacora(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $rol     = Role::where('codigo', 'revisora')->firstOrFail();

        $this->assertSame(0, DB::table('acl_bitacora')->count());

        $this->actingAs($this->admin)
            ->put(route('admin.acl.guardar-roles', $usuario), ['roles' => [$rol->id]]);

        $registro = DB::table('acl_bitacora')->latest('id')->first();

        $this->assertNotNull($registro, 'Asignar un rol debe dejar rastro.');
        $this->assertSame($usuario->id, $registro->usuario_afectado_id);
        $this->assertSame($rol->id, $registro->role_id);
        $this->assertSame($this->admin->id, $registro->ejecutado_por);
    }

    public function test_cambiar_permisos_de_un_rol_tambien_queda_registrado(): void
    {
        $rol     = Role::where('codigo', 'revisora')->firstOrFail();
        $permiso = Permiso::query()->firstOrFail();

        DB::table('acl_bitacora')->delete();

        $this->actingAs($this->admin)
            ->put(route('admin.acl.actualizar-rol', $rol), ['permisos' => [$permiso->id]]);

        $this->assertGreaterThan(
            0,
            DB::table('acl_bitacora')->where('role_id', $rol->id)->count(),
            'Cambiar los permisos de un rol debe quedar en la bitácora.'
        );
    }
}
