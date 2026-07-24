<?php

namespace Tests\Feature;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\HitoAgenda;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Flujo WEB completo del módulo de agenda por rol: petición HTTP real → middleware →
 * controlador → respuesta. Mismo enfoque que TramitesFlujoWebTest.
 *
 * Matriz de permisos (config/acl.php), sembrada por AclSeeder:
 *   agenda.ver   → todos los roles
 *   agenda.crear → solo admin y enlace
 *
 * Reglas verificadas en AgendaController:
 *   - create/store: 403 sin agenda.crear
 *   - show: puedeVerRegistro (borrador privado de su autor; completado abierto en lectura)
 *   - index: se filtra por dependencia salvo roles transversales
 *   - edit: REDIRIGE si no es admin ni de la dependencia; update: 403 en el mismo caso
 */
class AgendaFlujoWebTest extends TestCase
{
    use RefreshDatabase;

    private const PUEDEN_CREAR = [User::ROL_ADMIN, User::ROL_ENLACE];

    private const NO_PUEDEN_CREAR = [
        User::ROL_JURIDICO,
        User::ROL_REVISORA,
        User::ROL_SUJETO,
        User::ROL_DIGITALIZADOR,
    ];

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

    /** Una acción de agenda en una dependencia, con estatus y autor opcionales. */
    private function accion(Dependencia $dependencia, string $estatus, ?User $autor = null): AccionAgenda
    {
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);

        return AccionAgenda::factory()->create([
            'tramite_id'     => $tramite->id,
            'dependencia_id' => $dependencia->id,
            'estatus'        => $estatus,
            'created_by'     => $autor?->id,
        ]);
    }

    // ── Acceso al listado ──────────────────────────────────────────────────

    public function test_un_invitado_no_entra_al_listado_y_es_redirigido(): void
    {
        $this->get(route('agenda.index'))->assertStatus(302);
    }

    public function test_todos_los_roles_autenticados_ven_el_listado(): void
    {
        foreach (self::TODOS as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('agenda.index'))
                ->assertOk();
        }
    }

    // ── Alta ───────────────────────────────────────────────────────────────

    public function test_solo_admin_y_enlace_pueden_abrir_el_alta(): void
    {
        foreach (self::PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('agenda.create'))
                ->assertOk();
        }
    }

    public function test_los_demas_roles_no_pueden_abrir_el_alta(): void
    {
        foreach (self::NO_PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('agenda.create'))
                ->assertForbidden();
        }
    }

    // ── show: privacidad de borrador ───────────────────────────────────────

    public function test_un_borrador_ajeno_no_es_visible(): void
    {
        $dependencia = Dependencia::factory()->create();
        $otroAutor   = User::factory()->create();
        $borrador    = $this->accion($dependencia, AccionAgenda::ESTATUS_BORRADOR, $otroAutor);

        // Enlace de la misma dependencia, pero NO autor.
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('agenda.show', $borrador))
            ->assertForbidden();
    }

    public function test_el_autor_ve_su_propio_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace      = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $miBorrador  = $this->accion($dependencia, AccionAgenda::ESTATUS_BORRADOR, $enlace);

        $this->actingAs($enlace)
            ->get(route('agenda.show', $miBorrador))
            ->assertOk();
    }

    public function test_una_accion_completada_se_ve_desde_otra_dependencia(): void
    {
        $completada = $this->accion(Dependencia::factory()->create(), AccionAgenda::ESTATUS_COMPLETADO);

        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->get(route('agenda.show', $completada))
            ->assertOk();
    }

    // ── index: acotado por dependencia ─────────────────────────────────────

    public function test_el_enlace_solo_lista_acciones_de_su_dependencia(): void
    {
        $miDependencia   = Dependencia::factory()->create();
        $otraDependencia = Dependencia::factory()->create();

        $this->accion($miDependencia, AccionAgenda::ESTATUS_COMPLETADO)
            ->update(['descripcion' => 'Accion Propia Alfa']);
        $this->accion($otraDependencia, AccionAgenda::ESTATUS_COMPLETADO)
            ->update(['descripcion' => 'Accion Ajena Beta']);

        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $miDependencia);

        $respuesta = $this->actingAs($enlace)->get(route('agenda.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Accion Propia Alfa');
        $respuesta->assertDontSee('Accion Ajena Beta');
    }

    // ── edit/update: gate por dependencia ──────────────────────────────────

    public function test_un_enlace_de_otra_dependencia_no_puede_actualizar(): void
    {
        // La edición se gobierna por pertenencia a la dependencia (esDeSuDependencia),
        // no solo por el permiso: un enlace de OTRA dependencia —aunque tenga
        // agenda.editar— no puede actualizar la acción ajena.
        $accionAjena = $this->accion(Dependencia::factory()->create(), AccionAgenda::ESTATUS_COMPLETADO);

        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->put(route('agenda.update', $accionAjena), [])
            ->assertForbidden();
    }

    public function test_el_enlace_de_la_dependencia_si_puede_abrir_la_edicion(): void
    {
        // El caso positivo: el enlace (tiene agenda.editar) de la MISMA dependencia sí edita.
        $dependencia = Dependencia::factory()->create();
        $accion  = $this->accion($dependencia, AccionAgenda::ESTATUS_COMPLETADO);
        $enlace  = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('agenda.edit', $accion))
            ->assertOk();
    }

    public function test_un_sujeto_de_la_dependencia_no_puede_editar(): void
    {
        // El arreglo (b): editar exige el permiso agenda.editar, no solo pertenecer a la
        // dependencia. El sujeto (solo agenda.ver), aunque sea de la dependencia, NO edita.
        //   - edit  → redirige a la ficha (no muestra el formulario)
        //   - update → 403
        $dependencia = Dependencia::factory()->create();
        $accion = $this->accion($dependencia, AccionAgenda::ESTATUS_COMPLETADO);
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->actingAs($sujeto)
            ->get(route('agenda.edit', $accion))
            ->assertRedirect(route('agenda.show', $accion));

        $this->actingAs($sujeto)
            ->put(route('agenda.update', $accion), [])
            ->assertForbidden();
    }

    public function test_un_juridico_de_la_dependencia_no_puede_actualizar(): void
    {
        // Igual que el sujeto: el jurídico de la dependencia (agenda.ver/observar, sin
        // agenda.editar) no puede actualizar.
        $dependencia = Dependencia::factory()->create();
        $accion   = $this->accion($dependencia, AccionAgenda::ESTATUS_COMPLETADO);
        $juridico = $this->usuarioConRol(User::ROL_JURIDICO, $dependencia);

        $this->actingAs($juridico)
            ->put(route('agenda.update', $accion), [])
            ->assertForbidden();
    }

    // ── Hitos: aprobar/rechazar es solo agenda.aprobar (admin/revisora) ─────
    //
    // Estas acciones NO abortan con 403; redirigen con un mensaje de error. Por eso
    // se comprueba el EFECTO real (el hito queda aprobado o no), que es inequívoco.

    /** Un hito pendiente de visto bueno, colgado de una acción de la dependencia. */
    private function hitoPendiente(Dependencia $dependencia): HitoAgenda
    {
        $accion = $this->accion($dependencia, AccionAgenda::ESTATUS_EN_FIRMA);

        return HitoAgenda::create([
            'accion_agenda_id'  => $accion->id,
            'orden'             => 1,
            'clave'             => 'hito_prueba',
            'nombre'            => 'Hito de prueba',
            'completado'        => false,
            'estado_aprobacion' => 'pendiente',
        ]);
    }

    public function test_admin_y_revisora_pueden_aprobar_un_hito(): void
    {
        foreach ([User::ROL_ADMIN, User::ROL_REVISORA] as $rol) {
            $dependencia = Dependencia::factory()->create();
            $hito = $this->hitoPendiente($dependencia);

            $this->actingAs($this->usuarioConRol($rol, $dependencia))
                ->post(route('agenda.hito.aprobar', [
                    'agenda' => $hito->accion_agenda_id,
                    'hito'   => $hito->id,
                ]));

            $this->assertSame('aprobado', $hito->fresh()->estado_aprobacion,
                "El rol {$rol} (con agenda.aprobar) debe poder aprobar el hito.");
        }
    }

    public function test_los_roles_sin_agenda_aprobar_no_pueden_aprobar_un_hito(): void
    {
        // enlace, jurídico, sujeto y digitalizador NO tienen agenda.aprobar: el hito
        // debe quedarse pendiente aunque intenten aprobarlo.
        foreach ([User::ROL_ENLACE, User::ROL_JURIDICO, User::ROL_SUJETO, User::ROL_DIGITALIZADOR] as $rol) {
            $dependencia = Dependencia::factory()->create();
            $hito = $this->hitoPendiente($dependencia);

            $this->actingAs($this->usuarioConRol($rol, $dependencia))
                ->post(route('agenda.hito.aprobar', [
                    'agenda' => $hito->accion_agenda_id,
                    'hito'   => $hito->id,
                ]));

            $this->assertSame('pendiente', $hito->fresh()->estado_aprobacion,
                "El rol {$rol} (sin agenda.aprobar) NO debe poder aprobar el hito.");
        }
    }

    // ── store: guardar el alta ─────────────────────────────────────────────

    public function test_un_rol_sin_permiso_no_puede_guardar_un_alta(): void
    {
        // Con datos VÁLIDOS (para pasar la validación del FormRequest, cuyo authorize()
        // es true, y llegar así al control de permiso del controlador), un rol sin
        // agenda.crear recibe 403. Con datos inválidos rebotaría por validación antes,
        // por eso el payload aquí es completo.
        $dependencia = Dependencia::factory()->create();
        $tramite = Tramite::factory()->create(['dependencia_id' => $dependencia->id]);
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->actingAs($sujeto)
            ->post(route('agenda.store'), [
                'descripcion'    => 'Acción de prueba suficientemente larga',
                'dependencia_id' => $dependencia->id,
                'tramite_id'     => $tramite->id,
                'alcance'        => 'ambas',
            ])
            ->assertForbidden();
    }

    // ── destroy: eliminar (puedeEliminarAgenda) ────────────────────────────
    //
    // Regla: admin elimina cualquiera; el enlace SOLO su propio borrador de su
    // dependencia; los demás roles, nadie.

    public function test_el_admin_puede_eliminar_cualquier_accion(): void
    {
        $accion = $this->accion(Dependencia::factory()->create(), AccionAgenda::ESTATUS_COMPLETADO);

        $this->actingAs($this->usuarioConRol(User::ROL_ADMIN))
            ->delete(route('agenda.destroy', $accion))
            ->assertRedirect(route('agenda.index'));

        $this->assertSoftDeleted('acciones_agenda', ['id' => $accion->id]);
    }

    public function test_el_enlace_elimina_su_propio_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $miBorrador = $this->accion($dependencia, AccionAgenda::ESTATUS_BORRADOR, $enlace);

        $this->actingAs($enlace)
            ->delete(route('agenda.destroy', $miBorrador))
            ->assertRedirect(route('agenda.index'));

        $this->assertSoftDeleted('acciones_agenda', ['id' => $miBorrador->id]);
    }

    public function test_el_enlace_no_elimina_un_borrador_ajeno(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        // Mismo dependencia, pero creado por otra persona.
        $borradorAjeno = $this->accion($dependencia, AccionAgenda::ESTATUS_BORRADOR, User::factory()->create());

        $this->actingAs($enlace)
            ->delete(route('agenda.destroy', $borradorAjeno))
            ->assertForbidden();
    }

    public function test_el_enlace_no_elimina_una_accion_que_no_es_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        // Suya y de su dependencia, pero ya NO es borrador → no se puede eliminar.
        $completada = $this->accion($dependencia, AccionAgenda::ESTATUS_COMPLETADO, $enlace);

        $this->actingAs($enlace)
            ->delete(route('agenda.destroy', $completada))
            ->assertForbidden();
    }

    public function test_un_sujeto_no_puede_eliminar_ninguna_accion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);
        $accion = $this->accion($dependencia, AccionAgenda::ESTATUS_BORRADOR, $sujeto);

        $this->actingAs($sujeto)
            ->delete(route('agenda.destroy', $accion))
            ->assertForbidden();
    }

    // ── Hitos: rechazar (agenda.aprobar) y subir evidencia (por dependencia) ─

    public function test_una_revisora_puede_rechazar_un_hito_con_motivo(): void
    {
        // Rechazar exige agenda.aprobar Y un motivo (min 5 caracteres). Con ambos, el
        // hito queda 'rechazado'. Se comprueba por efecto.
        $dependencia = Dependencia::factory()->create();
        $hito = $this->hitoPendiente($dependencia);

        $this->actingAs($this->usuarioConRol(User::ROL_REVISORA, $dependencia))
            ->post(route('agenda.hito.rechazar', [
                'agenda' => $hito->accion_agenda_id,
                'hito'   => $hito->id,
            ]), ['motivo_rechazo' => 'Falta el soporte documental requerido.']);

        $this->assertSame('rechazado', $hito->fresh()->estado_aprobacion);
    }

    public function test_un_enlace_no_puede_rechazar_un_hito(): void
    {
        // El enlace no tiene agenda.aprobar: el hito se queda pendiente aunque mande motivo.
        $dependencia = Dependencia::factory()->create();
        $hito = $this->hitoPendiente($dependencia);

        $this->actingAs($this->usuarioConRol(User::ROL_ENLACE, $dependencia))
            ->post(route('agenda.hito.rechazar', [
                'agenda' => $hito->accion_agenda_id,
                'hito'   => $hito->id,
            ]), ['motivo_rechazo' => 'Un motivo cualquiera de prueba.']);

        $this->assertSame('pendiente', $hito->fresh()->estado_aprobacion);
    }

    public function test_un_enlace_de_la_dependencia_puede_subir_evidencia_de_un_hito(): void
    {
        // Subir evidencia se gobierna por dependencia (admin o de la dependencia).
        Storage::fake('local');

        $dependencia = Dependencia::factory()->create();
        $hito = $this->hitoPendiente($dependencia);
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->post(route('agenda.hito.evidencia', [
                'agenda' => $hito->accion_agenda_id,
                'hito'   => $hito->id,
            ]), ['evidencia' => UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf')])
            ->assertRedirect();

        // El hito quedó con su archivo de evidencia registrado.
        $this->assertNotNull($hito->fresh()->evidencia_archivo);
    }

    public function test_un_enlace_de_otra_dependencia_no_puede_subir_evidencia(): void
    {
        // Un enlace de OTRA dependencia no puede subir evidencia a un hito ajeno: el
        // guard es por dependencia. El hito no debe quedar con evidencia.
        Storage::fake('local');

        $hito = $this->hitoPendiente(Dependencia::factory()->create());
        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->post(route('agenda.hito.evidencia', [
                'agenda' => $hito->accion_agenda_id,
                'hito'   => $hito->id,
            ]), ['evidencia' => UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf')]);

        $this->assertNull($hito->fresh()->evidencia_archivo);
    }
}
