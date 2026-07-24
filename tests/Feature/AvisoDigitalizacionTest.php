<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Reingenieria;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use App\Notifications\AvisoPunta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Flujo web del aviso de digitalización.
 *
 * Cuando una reingeniería DIRECTA (no venida de agenda) queda firmada por enlace y
 * sujeto, se avisa a los enlaces de la dependencia del trámite para que inicien la
 * agenda SyD. Las reingenierías que vienen de AGENDA no disparan este aviso —tienen
 * su propio flujo (vincularDesdeAgenda)—: los dos coexisten.
 *
 * Se prueba de punta a punta por HTTP: se firma como sujeto y como enlace (POST a
 * firmas.firmar), y se verifica qué notificación se envió.
 */
class AvisoDigitalizacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AclSeeder::class);
        Cache::flush();
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

    /** Crea una reingeniería lista para la última firma (pendiente de firmas). */
    private function reingenieria(Tramite $tramite, string $origen): Reingenieria
    {
        return Reingenieria::create([
            'tramite_id'  => $tramite->id,
            'origen'      => $origen,
            'estado'      => Reingenieria::ESTADO_PENDIENTE_FIRMAS,
            'flujo_to_be' => ['pasos' => []],
        ]);
    }

    private function firmar(User $quien, Reingenieria $r, string $tipoFirma): void
    {
        $this->actingAs($quien)->post(
            route('firmas.firmar', ['tipo' => 'reingenieria', 'id' => $r->id]),
            ['tipo_firma' => $tipoFirma]
        );
    }

    public function test_al_firmar_una_reingenieria_directa_se_avisa_al_enlace(): void
    {
        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);
        $reingenieria = $this->reingenieria($tramite, Reingenieria::ORIGEN_DIRECTA);

        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        // Se completan las dos firmas (el orden no importa).
        $this->firmar($sujeto, $reingenieria, 'aceptacion_sujeto');
        $this->firmar($enlace, $reingenieria, 'aceptacion_enlace');

        // La reingeniería quedó firmada...
        $this->assertSame(Reingenieria::ESTADO_FIRMADA, $reingenieria->fresh()->estado);

        // ...y el enlace recibió el aviso para iniciar la agenda SyD.
        Notification::assertSentTo(
            $enlace,
            AvisoPunta::class,
            fn ($notificacion) => str_contains($notificacion->titulo, 'digitalizar')
                || str_contains($notificacion->mensaje, 'Simplificación')
        );
    }

    public function test_una_reingenieria_venida_de_agenda_no_dispara_este_aviso(): void
    {
        // Coexistencia: la de agenda tiene su propio flujo; aquí NO debe salir el aviso
        // "el sujeto quiere digitalizar". El guard esDirecta() es lo que los separa.
        Notification::fake();

        $dependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);
        $reingenieria = $this->reingenieria($tramite, Reingenieria::ORIGEN_AGENDA);

        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->firmar($sujeto, $reingenieria, 'aceptacion_sujeto');
        $this->firmar($enlace, $reingenieria, 'aceptacion_enlace');

        // Se firmó igual, pero SIN el aviso de digitalización directa.
        $this->assertSame(Reingenieria::ESTADO_FIRMADA, $reingenieria->fresh()->estado);

        Notification::assertNotSentTo(
            $enlace,
            AvisoPunta::class,
            fn ($notificacion) => str_contains($notificacion->titulo, 'digitalizar')
        );
    }

    public function test_el_enlace_puede_abrir_el_alta_de_agenda_a_la_que_lleva_el_aviso(): void
    {
        // El aviso lleva a agenda.create. El enlace (destinatario) debe poder abrirla.
        $dependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('agenda.create', ['tramite_id' => $tramite->id, 'tipo' => 'ambas']))
            ->assertOk();
    }

    public function test_el_sujeto_no_puede_crear_agenda_por_eso_el_aviso_va_al_enlace(): void
    {
        // Documenta la razón de diseño: el sujeto NO tiene agenda.crear, así que la
        // oferta de crear la agenda SyD se dirige al enlace, no a él.
        $dependencia = Dependencia::factory()->create();
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->actingAs($sujeto)
            ->get(route('agenda.create'))
            ->assertForbidden();
    }
}
