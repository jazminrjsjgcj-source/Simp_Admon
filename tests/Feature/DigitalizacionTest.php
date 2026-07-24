<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la BIBLIOTECA DIGITAL (digitalización de trámites).
 *
 * ── Qué protege este módulo ──
 *
 * Digitalizar un trámite no es apretar un botón: antes hay que tener el flujo
 * aprobado, la reingeniería TO-BE firmada por el enlace y el sujeto obligado, y su
 * diagrama generado. Ese orden existe porque digitalizar sobre un proceso que
 * nadie validó es automatizar el desorden: queda un trámite en línea que no
 * corresponde a ningún procedimiento aprobado, y deshacerlo cuesta mucho más que
 * no haberlo hecho.
 *
 * El controlador implementa esa puerta como una lista de comprobaciones. Estas
 * pruebas fijan que la puerta siga cerrada, porque es el tipo de validación que se
 * "simplifica" con buena intención y deja pasar cualquier cosa.
 *
 * También cubren los permisos del módulo, que están separados a propósito: ver la
 * biblioteca, redactar reingenierías y digitalizar son tres cosas distintas y no
 * tienen por qué recaer en la misma persona.
 */
class DigitalizacionTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AclSeeder::class);

        $this->dependencia = Dependencia::factory()->create();
    }

    /**
     * Los permisos salen de los roles del ACL, no de la columna `rol`: sin asignar
     * el rol correspondiente el usuario existe pero no puede hacer nada.
     */
    private function usuario(string $rol): User
    {
        $usuario = User::factory()->create([
            'rol'            => $rol,
            'activo'         => true,
            'dependencia_id' => $this->dependencia->id,
        ]);

        $usuario->roles()->attach(Role::where('codigo', $rol)->firstOrFail()->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    private function tramite(array $extra = []): Tramite
    {
        return Tramite::factory()->create(array_merge([
            'dependencia_id' => $this->dependencia->id,
        ], $extra));
    }

    /**
     * Trámite con su proceso actual ya aprobado.
     *
     * Hace falta porque el controlador comprueba esa puerta ANTES de validar el
     * formulario: sin ella corta con un aviso y las reglas de validación no llegan
     * a ejecutarse nunca.
     */
    private function tramiteConFlujoAprobado(): Tramite
    {
        return $this->tramite(['flujo_estado' => Tramite::FLUJO_APROBADO]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Acceso
    // ─────────────────────────────────────────────────────────────

    public function test_el_digitalizador_entra_a_la_biblioteca(): void
    {
        $usuario = $this->usuario(User::ROL_DIGITALIZADOR);

        $this->actingAs($usuario)->get(route('digitalizacion.index'))->assertOk();
        $this->actingAs($usuario)->get(route('digitalizacion.dashboard'))->assertOk();
    }

    public function test_sin_sesion_no_se_entra_a_la_biblioteca(): void
    {
        $this->get(route('digitalizacion.index'))->assertRedirect();
    }

    public function test_se_puede_consultar_la_ficha_de_digitalizacion_de_un_tramite(): void
    {
        $tramite = $this->tramite();

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->get(route('digitalizacion.show', $tramite))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────
    //  La puerta: no se digitaliza cualquier cosa
    // ─────────────────────────────────────────────────────────────

    /**
     * Un trámite recién capturado no tiene flujo aprobado, ni reingeniería firmada,
     * ni diagrama. Iniciar su digitalización debe rebotar, y —lo importante— el
     * estado NO debe moverse: un mensaje de error con el estado ya cambiado sería
     * lo peor de los dos mundos.
     */
    public function test_no_se_digitaliza_un_tramite_que_no_cumple_los_requisitos(): void
    {
        $tramite = $this->tramite(['digitalizacion_estado' => Tramite::DIG_NO_INICIADA]);

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.iniciar', $tramite))
            ->assertSessionHas('error');

        $this->assertSame(
            Tramite::DIG_NO_INICIADA,
            $tramite->fresh()->digitalizacion_estado,
            'El estado no debe moverse si la validación falló.'
        );
    }

    public function test_no_se_reinicia_la_digitalizacion_de_un_tramite_ya_digitalizado(): void
    {
        $tramite = $this->tramite(['digitalizacion_estado' => Tramite::DIG_DIGITALIZADO]);

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.iniciar', $tramite))
            ->assertSessionHas('error');

        $this->assertSame(Tramite::DIG_DIGITALIZADO, $tramite->fresh()->digitalizacion_estado);
    }

    // ─────────────────────────────────────────────────────────────
    //  Cerrar la digitalización
    // ─────────────────────────────────────────────────────────────

    public function test_se_completa_la_digitalizacion_de_un_tramite_en_proceso(): void
    {
        $tramite = $this->tramite(['digitalizacion_estado' => Tramite::DIG_EN_DIGITALIZACION]);

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.completar', $tramite), [
                'notas_cierre' => 'Publicado en el portal municipal.',
            ]);

        $this->assertSame(Tramite::DIG_DIGITALIZADO, $tramite->fresh()->digitalizacion_estado);
    }

    /**
     * No se puede cerrar lo que nunca se abrió: saltarse el paso intermedio dejaría
     * trámites marcados como digitalizados sin haber pasado por el proceso.
     */
    public function test_no_se_completa_una_digitalizacion_que_no_habia_empezado(): void
    {
        $tramite = $this->tramite(['digitalizacion_estado' => Tramite::DIG_NO_INICIADA]);

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.completar', $tramite))
            ->assertSessionHas('error');

        $this->assertSame(Tramite::DIG_NO_INICIADA, $tramite->fresh()->digitalizacion_estado);
    }

    // ─────────────────────────────────────────────────────────────
    //  Permisos separados
    // ─────────────────────────────────────────────────────────────

    /**
     * Los permisos del módulo están separados a propósito: ver la biblioteca,
     * redactar reingenierías y digitalizar son tres cosas distintas. El sujeto
     * obligado no tiene ninguno de ellos, así que ni consulta ni mueve estados.
     */
    public function test_quien_no_tiene_permiso_no_entra_ni_digitaliza(): void
    {
        $tramite = $this->tramite(['digitalizacion_estado' => Tramite::DIG_EN_DIGITALIZACION]);
        $sujeto  = $this->usuario(User::ROL_SUJETO);

        $this->actingAs($sujeto)
            ->get(route('digitalizacion.index'))
            ->assertForbidden();

        $this->actingAs($sujeto)
            ->post(route('digitalizacion.completar', $tramite))
            ->assertForbidden();

        $this->assertSame(Tramite::DIG_EN_DIGITALIZACION, $tramite->fresh()->digitalizacion_estado);
    }

    public function test_quien_no_tiene_el_permiso_no_crea_reingenierias(): void
    {
        $tramite = $this->tramite();

        $this->actingAs($this->usuario(User::ROL_SUJETO))
            ->post(route('digitalizacion.reingenieria.guardar', $tramite), [
                'origen' => 'agenda',
            ])
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────
    //  Reingeniería
    // ─────────────────────────────────────────────────────────────

    /**
     * Una reingeniería "directa" es la que se pide sin pasar por la agenda, así que
     * tiene que venir motivada y justificada: es la excepción, no el camino normal.
     */
    public function test_la_reingenieria_directa_exige_motivo_y_justificacion(): void
    {
        $tramite = $this->tramiteConFlujoAprobado();

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.reingenieria.guardar', $tramite), [
                'origen' => 'directa',
            ])
            ->assertSessionHasErrors(['motivo_directa', 'justificacion']);
    }

    public function test_el_origen_de_la_reingenieria_esta_acotado(): void
    {
        $tramite = $this->tramiteConFlujoAprobado();

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.reingenieria.guardar', $tramite), [
                'origen' => 'inventado',
            ])
            ->assertSessionHasErrors('origen');
    }

    /**
     * No se redacta el proceso nuevo de un trámite cuyo proceso actual nadie ha
     * aprobado todavía: sería construir sobre un plano sin firmar.
     */
    public function test_no_se_crea_reingenieria_si_el_flujo_no_esta_aprobado(): void
    {
        $tramite = $this->tramite();

        $this->actingAs($this->usuario(User::ROL_DIGITALIZADOR))
            ->post(route('digitalizacion.reingenieria.guardar', $tramite), [
                'origen' => 'agenda',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $tramite->reingenierias()->count());
    }
}
