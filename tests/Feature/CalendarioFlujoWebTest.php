<?php

namespace Tests\Feature;

use App\Models\CalendarioEvento;
use App\Models\Dependencia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Flujo WEB del calendario por rol: petición HTTP real → middleware → controlador →
 * respuesta.
 *
 * El calendario es pequeño (2 rutas):
 *   - GET  calendario                    → index, abierto a cualquier autenticado
 *   - PATCH calendario/{evento}/avance   → actualizarAvance, exige calendario.ver
 *
 * NOTA (hallazgo): actualizar el avance —una escritura— solo pide calendario.ver, el
 * permiso de MIRAR. Como TODOS los roles tienen calendario.ver, en la práctica todos
 * pueden actualizar avances. No existe un permiso calendario.editar separado. Estas
 * pruebas fijan ese comportamiento actual; si se quisiera un permiso de edición aparte,
 * sería un cambio de diseño (crear el permiso), no un simple arreglo.
 */
class CalendarioFlujoWebTest extends TestCase
{
    use RefreshDatabase;

    private const TODOS = [
        User::ROL_ADMIN,
        User::ROL_ENLACE,
        User::ROL_JURIDICO,
        User::ROL_REVISORA,
        User::ROL_SUJETO,
        User::ROL_DIGITALIZADOR,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AclSeeder::class);
        Cache::flush();
    }

    private function usuarioConRol(string $codigo): User
    {
        $user = User::factory()->create(['rol' => $codigo]);
        $user->roles()->attach(Role::where('codigo', $codigo)->firstOrFail()->id);
        $user->olvidarPermisosCache();

        return $user;
    }

    private function evento(int $avance = 0): CalendarioEvento
    {
        return CalendarioEvento::create([
            'tipo'    => 'agenda',
            'titulo'  => 'Evento de prueba',
            'fecha'   => now()->toDateString(),
            'estatus' => 'pendiente',
            'avance'  => $avance,
        ]);
    }

    // ── index: abierto a cualquier autenticado ─────────────────────────────

    public function test_un_invitado_no_ve_el_calendario_y_es_redirigido(): void
    {
        $this->get(route('calendario'))->assertStatus(302);
    }

    public function test_todos_los_roles_autenticados_ven_el_calendario(): void
    {
        foreach (self::TODOS as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('calendario'))
                ->assertOk();
        }
    }

    // ── actualizarAvance: requiere calendario.ver ──────────────────────────

    public function test_un_rol_con_calendario_ver_puede_actualizar_el_avance(): void
    {
        // El sujeto (que solo "ve") también puede actualizar avances, porque la acción
        // se gobierna con calendario.ver. Se comprueba por efecto.
        $evento = $this->evento(0);

        $this->actingAs($this->usuarioConRol(User::ROL_SUJETO))
            ->patch(route('calendario.avance', $evento), ['avance' => 40])
            ->assertRedirect();

        $this->assertSame(40, $evento->fresh()->avance);
    }

    public function test_un_usuario_sin_permiso_no_puede_actualizar_el_avance(): void
    {
        // Un usuario sin ningún rol adjunto no tiene calendario.ver → 403, y el avance
        // no cambia. Confirma que el guard del controlador funciona.
        $sinPermisos = User::factory()->create(['rol' => User::ROL_SUJETO]); // sin adjuntar rol
        $evento = $this->evento(15);

        $this->actingAs($sinPermisos)
            ->patch(route('calendario.avance', $evento), ['avance' => 90])
            ->assertForbidden();

        $this->assertSame(15, $evento->fresh()->avance);
    }

    public function test_el_avance_se_acota_a_100_y_marca_el_evento_como_cumplido(): void
    {
        // Lógica de negocio: un avance por encima de 100 se recorta a 100 y el evento
        // pasa a "cumplido".
        $evento = $this->evento(0);

        $this->actingAs($this->usuarioConRol(User::ROL_ENLACE))
            ->patch(route('calendario.avance', $evento), ['avance' => 150]);

        $fresco = $evento->fresh();
        $this->assertSame(100, $fresco->avance);
        $this->assertSame('cumplido', $fresco->estatus);
    }
}
