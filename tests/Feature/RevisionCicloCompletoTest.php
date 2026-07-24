<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Observacion;
use App\Models\PropuestaRegulatoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ciclo de vida COMPLETO de revisión, con los dos caminos por los que una observación
 * deja de bloquear la aprobación:
 *
 *   - Camino limpio:   observar → el enlace subsana (atendida, ya no bloquea) → el
 *     revisor aprueba. Opcionalmente el revisor VALIDA (sello de "quedó bien").
 *   - Camino de autoridad: quedan observaciones pendientes → el revisor aprueba por
 *     encima CON justificación → esas observaciones quedan SOBRESEÍDAS (trazables).
 *
 * El flujo (RevisionController + RevisionService) es COMPARTIDO por trámites, agenda y
 * propuestas; aquí se ejercita con una propuesta.
 */
class RevisionCicloCompletoTest extends TestCase
{
    use RefreshDatabase;

    private const TIPO = 'propuesta_regulatoria';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AclSeeder::class);
        Cache::flush();
        Notification::fake();
    }

    private function usuarioConRol(string $codigo, ?Dependencia $dependencia = null): User
    {
        $user = User::factory()->create([
            'rol'            => $codigo,
            'dependencia_id' => $dependencia?->id,
        ]);
        $user->roles()->attach(Role::where('codigo', $codigo)->firstOrFail()->id);
        $user->olvidarPermisosCache();

        return $user;
    }

    private function propuestaEnConsulta(Dependencia $dependencia, ?User $autor = null): PropuestaRegulatoria
    {
        return PropuestaRegulatoria::create([
            'nombre'         => 'Propuesta en revisión',
            'dependencia_id' => $dependencia->id,
            'estatus'        => PropuestaRegulatoria::ESTATUS_CONSULTA,
            'created_by'     => $autor?->id,
        ]);
    }

    private function observar(User $revisor, PropuestaRegulatoria $propuesta, User $destinatario): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($revisor)->post(
            route('revision.observar', ['tipo' => self::TIPO, 'id' => $propuesta->id]),
            [
                'seccion'         => 'Datos generales',
                'texto'           => 'Falta precisar la justificación de la propuesta.',
                'destinatario_id' => $destinatario->id,
            ]
        );
    }

    private function aprobar(User $quien, PropuestaRegulatoria $propuesta, array $datos = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($quien)->post(
            route('revision.aprobar', ['tipo' => self::TIPO, 'id' => $propuesta->id]),
            $datos
        );
    }

    public function test_una_revisora_puede_observar_una_propuesta(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        $this->observar($revisora, $propuesta, $enlace);

        $this->assertSame(1, $propuesta->observaciones()->count());
        $this->assertTrue($propuesta->observaciones()->pendientes()->exists());
    }

    public function test_un_sujeto_no_puede_observar(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->observar($sujeto, $propuesta, $enlace)->assertForbidden();

        $this->assertSame(0, $propuesta->observaciones()->count());
    }

    public function test_no_se_puede_aprobar_con_pendientes_sin_justificacion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        $this->observar($revisora, $propuesta, $enlace);

        // Aprobar sin justificación, con una pendiente → NO procede.
        $this->aprobar($revisora, $propuesta);

        $this->assertNotSame('en_firma', $propuesta->fresh()->estatus);
    }

    public function test_camino_limpio_observar_subsanar_aprobar(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        // 1) La revisora observa.
        $this->observar($revisora, $propuesta, $enlace);
        $observacion = $propuesta->observaciones()->firstOrFail();

        // 2) El enlace (destinatario) subsana: marca atendida. Ya NO bloquea.
        $this->actingAs($enlace)
            ->post(route('revision.atendida', ['observacion' => $observacion->id]));
        $this->assertSame(Observacion::ESTATUS_ATENDIDA, $observacion->fresh()->estatus);

        // 3) Sin justificación, la revisora ya puede aprobar (no quedan pendientes vivas).
        $this->aprobar($revisora, $propuesta);
        $this->assertSame('en_firma', $propuesta->fresh()->estatus);
    }

    public function test_el_revisor_puede_validar_una_observacion_atendida(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        $this->observar($revisora, $propuesta, $enlace);
        $observacion = $propuesta->observaciones()->firstOrFail();

        $this->actingAs($enlace)
            ->post(route('revision.atendida', ['observacion' => $observacion->id]));

        // El revisor sella la subsanación como validada.
        $this->actingAs($revisora)
            ->post(route('revision.validar', ['observacion' => $observacion->id]))
            ->assertRedirect();

        $this->assertSame(Observacion::ESTATUS_VALIDADA, $observacion->fresh()->estatus);
        $this->assertSame($revisora->id, $observacion->fresh()->resuelta_por);
    }

    public function test_un_sujeto_no_puede_validar(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->observar($revisora, $propuesta, $enlace);
        $observacion = $propuesta->observaciones()->firstOrFail();

        $this->actingAs($sujeto)
            ->post(route('revision.validar', ['observacion' => $observacion->id]))
            ->assertForbidden();
    }

    public function test_el_revisor_aprueba_por_encima_sobreseyendo_con_justificacion(): void
    {
        // Camino de autoridad: quedan pendientes, pero el revisor aprueba justificando.
        // Las observaciones quedan SOBRESEÍDAS con motivo y quién, y la propuesta pasa a firma.
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $propuesta = $this->propuestaEnConsulta($dependencia, $enlace);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        $this->observar($revisora, $propuesta, $enlace);
        $observacion = $propuesta->observaciones()->firstOrFail();

        $this->aprobar($revisora, $propuesta, [
            'justificacion_sobreseimiento' => 'Se aprueba por urgencia; la observación se atenderá en la siguiente versión.',
        ]);

        $this->assertSame('en_firma', $propuesta->fresh()->estatus);

        $fresca = $observacion->fresh();
        $this->assertSame(Observacion::ESTATUS_SOBRESEIDA, $fresca->estatus);
        $this->assertSame($revisora->id, $fresca->resuelta_por);
        $this->assertNotNull($fresca->motivo_sobreseimiento);
    }
}
