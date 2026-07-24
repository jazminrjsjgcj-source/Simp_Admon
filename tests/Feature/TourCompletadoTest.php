<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El registro de tours guiados completados.
 *
 * Lo que se protege aquí no es el tutorial en sí —eso es JavaScript y burbujas—,
 * sino la regla que decide si el tour vuelve a saltarle a alguien en la cara: si
 * este endpoint se rompe, o el tutorial no deja de aparecer nunca (molesto) o se
 * marca como visto sin que nadie lo haya visto (inútil).
 */
class TourCompletadoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create([
            'rol'            => User::ROL_ENLACE,
            'activo'         => true,
            'dependencia_id' => Dependencia::factory()->create()->id,
        ]);
    }

    public function test_registra_el_tour_completado(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->postJson(route('tours.completado'), ['tour' => 'tramites.create'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tours_vistos', [
            'user_id' => $usuario->id,
            'tour'    => 'tramites.create',
        ]);
    }

    /**
     * El usuario puede repetir el recorrido con el botón "¿Cómo funciona esto?"
     * cuantas veces quiera, y cada vez que llegue al final el navegador avisa otra
     * vez. Con un insert a secas, la segunda vuelta reventaría contra el índice
     * único (user_id, tour) y el usuario vería un error en consola sin motivo.
     */
    public function test_repetir_el_tour_no_duplica_ni_falla(): void
    {
        $usuario = $this->usuario();

        foreach (range(1, 3) as $vuelta) {
            $this->actingAs($usuario)
                ->postJson(route('tours.completado'), ['tour' => 'buscar'])
                ->assertOk();
        }

        $this->assertSame(
            1,
            DB::table('tours_vistos')->where('user_id', $usuario->id)->count(),
            'Repetir el recorrido debe actualizar la fila, no crear una nueva.'
        );
    }

    /**
     * Sin esta validación, cualquiera con sesión podría llenar la tabla con cadenas
     * inventadas mandando peticiones a mano. No es grave —solo ensucia una tabla
     * propia—, pero es basura que luego nadie sabría de dónde salió.
     */
    public function test_rechaza_un_tour_que_no_existe_en_la_configuracion(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->postJson(route('tours.completado'), ['tour' => 'inventado.por.alguien'])
            ->assertStatus(422);

        $this->assertDatabaseCount('tours_vistos', 0);
    }

    /** Cada quien marca lo suyo: la clave viene del cuerpo, pero el usuario de la sesión. */
    public function test_marcar_un_tour_no_afecta_a_otros_usuarios(): void
    {
        $unaPersona  = $this->usuario();
        $otraPersona = $this->usuario();

        $this->actingAs($unaPersona)
            ->postJson(route('tours.completado'), ['tour' => 'tramites.create'])
            ->assertOk();

        $this->assertDatabaseMissing('tours_vistos', [
            'user_id' => $otraPersona->id,
        ]);
    }

    public function test_sin_sesion_no_se_puede_registrar(): void
    {
        $this->postJson(route('tours.completado'), ['tour' => 'buscar'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('tours_vistos', 0);
    }
}
