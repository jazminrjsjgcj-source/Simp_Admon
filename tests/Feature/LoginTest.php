<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas del INICIO Y CIERRE DE SESIÓN.
 *
 * Es la puerta del sistema y no tenía ninguna prueba. Si un día deja de
 * funcionar, no falla una pantalla: no entra nadie. Y si falla al revés —dejando
 * pasar a quien no debe— nadie se entera hasta que ya pasó algo.
 *
 * Aquí quedan fijadas las cuatro cosas que no pueden romperse:
 *
 *   1. Con credenciales correctas se entra y se llega al dashboard.
 *   2. Con credenciales incorrectas NO se entra, y el mensaje no revela si el
 *      correo existe o si solo falló la contraseña.
 *   3. Una cuenta DESACTIVADA no entra, aunque la contraseña sea correcta. Es la
 *      forma de dar de baja a alguien sin borrar su historial, así que tiene que
 *      ser efectiva de verdad.
 *   4. Al cerrar sesión se invalida la sesión, no solo se redirige.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'contrasena-de-prueba';

    private function usuario(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'ana@lapaz.gob.mx',
            'password' => Hash::make(self::CLAVE),
            'activo'   => true,
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────
    //  Entrar
    // ─────────────────────────────────────────────────────────────

    public function test_la_pantalla_de_login_carga(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_se_entra_con_las_credenciales_correctas(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', [
            'email'    => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_no_se_entra_con_la_contrasena_equivocada(): void
    {
        $this->usuario();

        $this->post('/login', [
            'email'    => 'ana@lapaz.gob.mx',
            'password' => 'la-que-no-es',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_no_se_entra_con_un_correo_que_no_existe(): void
    {
        $this->post('/login', [
            'email'    => 'nadie@lapaz.gob.mx',
            'password' => self::CLAVE,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * El mensaje de error es el MISMO tanto si el correo no existe como si la
     * contraseña está mal. Si fueran distintos, cualquiera podría averiguar qué
     * correos tienen cuenta en el sistema probándolos uno a uno.
     */
    public function test_el_error_no_revela_si_el_correo_existe(): void
    {
        $this->usuario();

        // El mismo mensaje en los dos casos, sea cual sea: lo que no puede pasar es
        // que difieran, porque entonces el error delataría qué correos existen.
        $mensaje = 'Credenciales incorrectas.';

        $this->post('/login', [
            'email'    => 'ana@lapaz.gob.mx',
            'password' => 'la-que-no-es',
        ])->assertSessionHasErrors(['email' => $mensaje]);

        $this->post('/login', [
            'email'    => 'nadie@lapaz.gob.mx',
            'password' => 'la-que-no-es',
        ])->assertSessionHasErrors(['email' => $mensaje]);
    }

    public function test_el_correo_y_la_contrasena_son_obligatorios(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_un_correo_mal_formado_se_rechaza(): void
    {
        $this->post('/login', ['email' => 'esto-no-es-un-correo', 'password' => 'x'])
            ->assertSessionHasErrors('email');
    }

    // ─────────────────────────────────────────────────────────────
    //  Cuentas desactivadas
    // ─────────────────────────────────────────────────────────────

    /**
     * Dar de baja a alguien se hace desactivando su cuenta, no borrándola, para
     * que su historial siga teniendo a quién apuntar. Si un usuario desactivado
     * pudiera entrar, esa baja no serviría de nada.
     */
    public function test_una_cuenta_desactivada_no_entra_aunque_la_clave_sea_correcta(): void
    {
        $this->usuario(['activo' => false]);

        $this->post('/login', [
            'email'    => 'ana@lapaz.gob.mx',
            'password' => self::CLAVE,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ─────────────────────────────────────────────────────────────
    //  Salir
    // ─────────────────────────────────────────────────────────────

    public function test_al_cerrar_sesion_se_sale_y_se_vuelve_al_login(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Después de salir, volver a una pantalla interna no debe funcionar: si la
     * sesión no se invalidara de verdad, bastaría el botón "atrás" del navegador.
     */
    public function test_despues_de_salir_no_se_entra_a_una_pantalla_interna(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->post(route('logout'));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // ─────────────────────────────────────────────────────────────
    //  Después de entrar
    // ─────────────────────────────────────────────────────────────

    public function test_quien_ya_entro_llega_al_dashboard(): void
    {
        // Con dependencia: el dashboard de un enlace muestra lo de su área, así que
        // sin ella la prueba mediría un caso que no se da en el sistema real.
        $usuario = $this->usuario([
            'rol'            => User::ROL_ENLACE,
            'dependencia_id' => Dependencia::factory()->create()->id,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
