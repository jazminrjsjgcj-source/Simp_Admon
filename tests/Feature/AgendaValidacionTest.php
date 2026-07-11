<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la validación al crear una acción de la Agenda SyD.
 *
 * La regla de fondo es la misma que en trámites:
 *
 *   - GUARDAR COMO BORRADOR: se permite dejar campos vacíos. Es trabajo en proceso
 *     y hay que poder guardarlo a medias.
 *   - ENVIAR A REVISIÓN: se exige todo. La acción pasa a ser un compromiso formal
 *     con folio, y no puede tener huecos.
 */
class AgendaValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos viven en la base (roles → permisos), y RefreshDatabase deja la
        // base vacía: sin sembrar el ACL, ningún usuario tendría permisos y el
        // controlador respondería 403 antes de llegar a guardar nada.
        $this->seed(\Database\Seeders\AclSeeder::class);

        // El enlace es quien captura las acciones de la agenda. El permiso no viene
        // del campo `rol` sino de la relación con la tabla de roles, así que hay que
        // engancharlo explícitamente.
        $this->usuario = User::factory()->create(['rol' => User::ROL_ENLACE]);

        $rolEnlace = Role::where('codigo', User::ROL_ENLACE)->firstOrFail();
        $this->usuario->roles()->attach($rolEnlace->id);
        $this->usuario->olvidarPermisosCache();
    }

    /** Una acción completa y válida, lista para enviarse a revisión. */
    private function accionCompleta(): array
    {
        $tramite = Tramite::factory()->completado()->create();

        return [
            'accion'           => 'enviar',
            'tramite_id'       => $tramite->id,
            'dependencia_id'   => $tramite->dependencia_id,
            'alcance'          => 'simplificacion',
            'tipo'             => 'simplificacion',
            'descripcion'      => 'Reducir los requisitos del trámite de ocho a cuatro.',
            'meta'             => 'Bajar el tiempo de atención a la mitad.',
            'responsable'      => 'Jefa de la unidad',
            'fecha_inicio'     => now()->toDateString(),
            'fecha_compromiso' => now()->addMonths(3)->toDateString(),
        ];
    }

    // ── Borrador: se permite guardar incompleto ──────────────────────────

    public function test_se_puede_guardar_un_borrador_con_datos_incompletos(): void
    {
        // Un borrador es trabajo en proceso: debe poder guardarse aunque le falten la
        // meta, el responsable o las fechas.
        //
        // Aun así, no puede quedar "en el aire": una acción pertenece a una
        // dependencia, se hace sobre un trámite y tiene un alcance (la columna `tipo`
        // es NOT NULL en la base). Esos datos, más la descripción, son el mínimo con
        // el que la acción existe y se reconoce en el listado.
        $tramite = Tramite::factory()->completado()->create();

        $respuesta = $this->actingAs($this->usuario)->post(route('agenda.store'), [
            'accion'         => 'borrador',
            'descripcion'    => 'Todavía estoy redactando esta acción.',
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $tramite->dependencia_id,
            'alcance'        => 'simplificacion',
            // Sin meta, sin responsable y sin fechas: eso se completa antes de enviar.
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseCount('acciones_agenda', 1);
    }

    // ── Envío: se exige todo ─────────────────────────────────────────────

    public function test_al_enviar_se_exige_el_tramite(): void
    {
        // Una acción de agenda se hace SOBRE un trámite: sin él no tiene sujeto.
        $datos = $this->accionCompleta();
        unset($datos['tramite_id']);

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors('tramite_id');
    }

    public function test_el_alcance_es_obligatorio_incluso_en_borrador(): void
    {
        // El alcance no es un adorno: la columna `tipo` de acciones_agenda es NOT NULL,
        // así que sin él la acción no se puede ni guardar. Y de él depende el tipo de
        // folio (SIM / DIG / SYD). Por eso se exige desde el borrador.
        $tramite = Tramite::factory()->completado()->create();

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), [
                'accion'         => 'borrador',
                'descripcion'    => 'Una acción sin alcance definido.',
                'tramite_id'     => $tramite->id,
                'dependencia_id' => $tramite->dependencia_id,
                // sin alcance
            ])
            ->assertSessionHasErrors('alcance');
    }

    public function test_al_enviar_se_exige_la_dependencia(): void
    {
        $datos = $this->accionCompleta();
        unset($datos['dependencia_id']);

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors('dependencia_id');
    }

    public function test_al_enviar_se_exige_el_responsable(): void
    {
        // Un compromiso sin responsable no se puede dar seguimiento.
        $datos = $this->accionCompleta();
        unset($datos['responsable']);

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors('responsable');
    }

    public function test_al_enviar_se_exigen_las_fechas(): void
    {
        $datos = $this->accionCompleta();
        unset($datos['fecha_inicio'], $datos['fecha_compromiso']);

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors(['fecha_inicio', 'fecha_compromiso']);
    }

    public function test_al_enviar_se_exige_la_meta(): void
    {
        $datos = $this->accionCompleta();
        unset($datos['meta']);

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors('meta');
    }

    public function test_la_fecha_de_compromiso_no_puede_ser_anterior_al_inicio(): void
    {
        $datos = $this->accionCompleta();
        $datos['fecha_inicio']     = now()->addMonth()->toDateString();
        $datos['fecha_compromiso'] = now()->toDateString(); // antes del inicio

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors('fecha_compromiso');
    }

    // ── Diagnóstico del trámite: solo al registrar uno nuevo ────────────

    public function test_con_un_tramite_existente_no_se_pide_el_diagnostico(): void
    {
        // El trámite ya tiene sus datos de operación (visitas, áreas, tiempos) en su
        // ficha: el formulario los hereda en solo lectura. Pedirlos otra vez sería
        // absurdo y bloquearía el envío.
        $datos = $this->accionCompleta(); // usa un trámite existente

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasNoErrors();
    }

    public function test_al_registrar_un_tramite_nuevo_se_exige_su_diagnostico(): void
    {
        // Aquí el trámite se crea desde la agenda, así que su línea base (cómo opera
        // hoy) hay que capturarla: es contra lo que se medirá la mejora.
        $datos = $this->accionCompleta();
        unset($datos['tramite_id']);
        $datos['modo_tramite'] = 'nuevo';

        $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $datos)
            ->assertSessionHasErrors([
                'tramite_visitas_requeridas',
                'tramite_num_areas',
                'tramite_nivel_digitalizacion',
            ]);
    }

    public function test_una_accion_completa_se_envia_sin_errores(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('agenda.store'), $this->accionCompleta());

        $respuesta->assertSessionHasNoErrors();

        $accion = AccionAgenda::first();
        $this->assertNotNull($accion->folio, 'Una acción enviada debe recibir folio.');
        $this->assertSame(AccionAgenda::ESTATUS_EN_OBSERVACION, $accion->estatus);
    }
}
