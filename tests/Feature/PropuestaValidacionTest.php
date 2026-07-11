<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\PropuestaRegulatoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la validación al crear una propuesta regulatoria.
 *
 * Una propuesta regulatoria es la puerta de entrada al Análisis de Impacto
 * Regulatorio (AIR): un procedimiento formal con consecuencias. Por eso, al
 * enviarla a revisión, no puede tener huecos.
 *
 * Como en el resto del sistema, hay dos niveles:
 *   - BORRADOR: se guarda a medias (es trabajo en proceso).
 *   - ENVÍO:    se exige todo lo que sustenta la propuesta.
 */
class PropuestaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos viven en la base (roles → permisos) y RefreshDatabase la deja
        // vacía: sin sembrar el ACL, el controlador respondería 403.
        $this->seed(\Database\Seeders\AclSeeder::class);

        $this->usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);
        $rol = Role::where('codigo', User::ROL_ENLACE)->firstOrFail();
        $this->usuario->roles()->attach($rol->id);
        $this->usuario->olvidarPermisosCache();
    }

    /** Una propuesta completa, lista para enviarse a revisión. */
    private function propuestaCompleta(): array
    {
        $dependencia = Dependencia::factory()->create();

        return [
            'accion'                     => 'enviar',
            'nombre'                     => 'Reglamento de Comercio en Vía Pública',
            'tipo_regulacion'            => 'Reglamento',
            'dependencia_id'             => $dependencia->id,
            'justificacion'              => 'Se requiere ordenar el comercio en la vía pública del centro histórico.',
            'fecha_tentativa'            => now()->addMonths(2)->toDateString(),
            'impacta_comercio_inversion' => '0',
            'genera_costos_burocraticos' => '0',
        ];
    }

    // ── Borrador ─────────────────────────────────────────────────────────

    public function test_se_puede_guardar_un_borrador_con_solo_el_nombre_y_la_dependencia(): void
    {
        // Un borrador es trabajo en proceso: se guarda a medias. Solo hace falta lo
        // mínimo para reconocerlo en el listado y saber de quién es.
        $dependencia = Dependencia::factory()->create();

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), [
                'accion'         => 'borrador',
                'nombre'         => 'Reglamento en redacción',
                'dependencia_id' => $dependencia->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('propuestas_regulatorias', 1);
    }

    public function test_ni_siquiera_un_borrador_puede_ir_sin_nombre(): void
    {
        // El nombre es NOT NULL en la base: sin él la propuesta no existe.
        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), ['accion' => 'borrador'])
            ->assertSessionHasErrors('nombre');
    }

    // ── Envío a revisión ─────────────────────────────────────────────────

    public function test_al_enviar_se_exige_el_tipo_de_regulacion(): void
    {
        // Reglamento, acuerdo, lineamiento... De esto depende el trámite que sigue.
        $datos = $this->propuestaCompleta();
        unset($datos['tipo_regulacion']);

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $datos)
            ->assertSessionHasErrors('tipo_regulacion');
    }

    public function test_al_enviar_se_exige_la_dependencia(): void
    {
        $datos = $this->propuestaCompleta();
        unset($datos['dependencia_id']);

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $datos)
            ->assertSessionHasErrors('dependencia_id');
    }

    public function test_al_enviar_se_exige_la_justificacion(): void
    {
        // Sin justificación no hay propuesta: es el sustento de por qué se regula.
        $datos = $this->propuestaCompleta();
        unset($datos['justificacion']);

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $datos)
            ->assertSessionHasErrors('justificacion');
    }

    public function test_al_enviar_se_exige_la_fecha_tentativa(): void
    {
        $datos = $this->propuestaCompleta();
        unset($datos['fecha_tentativa']);

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $datos)
            ->assertSessionHasErrors('fecha_tentativa');
    }

    public function test_al_enviar_se_exige_declarar_si_impacta_comercio(): void
    {
        // Es la pregunta que decide si la propuesta requiere un AIR: no puede quedar
        // sin responder.
        $datos = $this->propuestaCompleta();
        unset($datos['impacta_comercio_inversion']);

        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $datos)
            ->assertSessionHasErrors('impacta_comercio_inversion');
    }

    public function test_una_propuesta_completa_se_envia_y_recibe_folio(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('propuestas.store'), $this->propuestaCompleta())
            ->assertSessionHasNoErrors();

        $propuesta = PropuestaRegulatoria::first();

        $this->assertNotNull($propuesta, 'La propuesta debe guardarse.');
        $this->assertNotNull($propuesta->folio, 'Al enviarla a revisión debe recibir folio.');
        $this->assertStringStartsWith('LPZ-', $propuesta->folio);
    }
}
