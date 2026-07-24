<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Permiso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas del módulo de ADMINISTRACIÓN DE USUARIOS.
 *
 * Aquí se decide quién entra al sistema y qué puede hacer dentro, así que un fallo
 * silencioso en esta pantalla no se nota hasta que alguien ve —o borra— algo que no
 * le tocaba. Estas pruebas fijan por escrito las cuatro cosas que no pueden fallar:
 *
 *   1. Solo un admin llega a esta sección.
 *   2. Crear un usuario lo deja utilizable: activo, con contraseña cifrada y con
 *      exactamente los permisos que se marcaron.
 *   3. Cambiar permisos QUITA los que se desmarcan, no solo agrega los nuevos.
 *      (Una sincronización que solo suma deja privilegios de por vida.)
 *   4. Eliminar manda a papelera y no destruye el historial, y nadie puede borrarse
 *      a sí mismo y dejar al sistema sin administrador.
 */
class AdminUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos no viven en el código sino en la base (tabla `permisos`), así
        // que sin sembrar el ACL no habría nada que asignar ni que comprobar.
        $this->seed(\Database\Seeders\AclSeeder::class);

        $this->admin = User::factory()->create([
            'rol'    => User::ROL_ADMIN,
            'activo' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Acceso
    // ─────────────────────────────────────────────────────────────

    public function test_el_admin_entra_a_la_lista_de_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk();
    }

    public function test_quien_no_es_admin_no_entra(): void
    {
        $enlace = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->actingAs($enlace)
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();   // 403: lo corta el middleware role:admin
    }

    public function test_sin_sesion_no_se_entra(): void
    {
        $this->get(route('admin.usuarios.index'))
            ->assertRedirect();    // manda al login
    }

    // ─────────────────────────────────────────────────────────────
    //  Crear
    // ─────────────────────────────────────────────────────────────

    public function test_el_admin_crea_un_usuario_y_queda_utilizable(): void
    {
        $dependencia = Dependencia::factory()->create();
        $permisos    = Permiso::query()->limit(2)->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), [
                'name'                  => 'Ana Torres',
                'email'                 => 'ana.torres@lapaz.gob.mx',
                'password'              => 'secreto-largo-123',
                'password_confirmation' => 'secreto-largo-123',
                'rol'                   => User::ROL_ENLACE,
                'cargo'                 => 'Enlace de mejora regulatoria',
                'dependencia_id'        => $dependencia->id,
                'permisos'              => $permisos,
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $usuario = User::where('email', 'ana.torres@lapaz.gob.mx')->first();

        $this->assertNotNull($usuario, 'El usuario debería existir después de crearlo.');
        $this->assertTrue($usuario->activo, 'Un usuario recién creado debe quedar activo.');
        $this->assertSame(User::ROL_ENLACE, $usuario->rol);
        $this->assertSame($dependencia->id, $usuario->dependencia_id);

        // La contraseña NUNCA debe quedar guardada en claro.
        $this->assertNotSame('secreto-largo-123', $usuario->password);
        $this->assertTrue(Hash::check('secreto-largo-123', $usuario->password));

        // Y debe tener exactamente los permisos que se marcaron.
        $this->assertEqualsCanonicalizing(
            $permisos,
            $usuario->permisosDirectos()->pluck('permisos.id')->all()
        );
    }

    public function test_no_se_permiten_dos_usuarios_con_el_mismo_correo(): void
    {
        User::factory()->create(['email' => 'repetido@lapaz.gob.mx']);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), [
                'name'                  => 'Otro',
                'email'                 => 'repetido@lapaz.gob.mx',
                'password'              => 'secreto-largo-123',
                'password_confirmation' => 'secreto-largo-123',
                'rol'                   => User::ROL_ENLACE,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'repetido@lapaz.gob.mx')->count());
    }

    public function test_la_contrasena_debe_venir_confirmada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), [
                'name'                  => 'Sin confirmar',
                'email'                 => 'sin.confirmar@lapaz.gob.mx',
                'password'              => 'secreto-largo-123',
                'password_confirmation' => 'otra-cosa-distinta',
                'rol'                   => User::ROL_ENLACE,
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'sin.confirmar@lapaz.gob.mx']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Cambiar rol y permisos
    // ─────────────────────────────────────────────────────────────

    public function test_el_admin_cambia_el_rol_de_un_usuario(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $usuario), [
                'name'  => $usuario->name,
                'email' => $usuario->email,
                'rol'   => User::ROL_REVISORA,
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame(User::ROL_REVISORA, $usuario->fresh()->rol);
    }

    /**
     * El caso que de verdad importa: al guardar, los permisos que se DESMARCAN
     * tienen que desaparecer. Si la sincronización solo añadiera, un usuario
     * conservaría para siempre cualquier privilegio que se le diera una vez.
     */
    public function test_al_cambiar_permisos_se_quitan_los_que_se_desmarcan(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $todos    = Permiso::query()->limit(3)->pluck('id')->all();
        $iniciales = array_slice($todos, 0, 2);   // se le dan dos
        $finales   = array_slice($todos, 2, 1);   // luego se le deja solo otro

        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name'     => $usuario->name,
            'email'    => $usuario->email,
            'rol'      => $usuario->rol,
            'permisos' => $iniciales,
        ]);

        $this->assertEqualsCanonicalizing(
            $iniciales,
            $usuario->permisosDirectos()->pluck('permisos.id')->all()
        );

        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name'     => $usuario->name,
            'email'    => $usuario->email,
            'rol'      => $usuario->rol,
            'permisos' => $finales,
        ]);

        $this->assertEqualsCanonicalizing(
            $finales,
            $usuario->permisosDirectos()->pluck('permisos.id')->all(),
            'Los permisos desmarcados deberían haberse retirado.'
        );
    }

    public function test_quitar_todos_los_permisos_deja_al_usuario_sin_ninguno(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $usuario->permisosDirectos()->sync(Permiso::query()->limit(2)->pluck('id')->all());

        // Sin la clave 'permisos', el formulario está diciendo "ninguno marcado".
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name'  => $usuario->name,
            'email' => $usuario->email,
            'rol'   => $usuario->rol,
        ]);

        $this->assertCount(0, $usuario->permisosDirectos()->get());
    }

    // ─────────────────────────────────────────────────────────────
    //  Eliminar
    // ─────────────────────────────────────────────────────────────

    public function test_el_admin_manda_un_usuario_a_papelera(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_ENLACE, 'activo' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.usuarios.destroy', $usuario))
            ->assertRedirect(route('admin.usuarios.index'));

        // Borrado suave: desaparece de las consultas normales pero la fila sigue,
        // porque su historial (trámites, firmas, bitácora) tiene que seguir teniendo
        // a quién apuntar.
        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
        $this->assertFalse(User::withTrashed()->find($usuario->id)->activo);
    }

    public function test_un_admin_no_puede_eliminarse_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.usuarios.destroy', $this->admin));

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    public function test_quien_no_es_admin_no_puede_eliminar_usuarios(): void
    {
        $enlace  = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $victima = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $this->actingAs($enlace)
            ->delete(route('admin.usuarios.destroy', $victima))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $victima->id]);
    }
}
