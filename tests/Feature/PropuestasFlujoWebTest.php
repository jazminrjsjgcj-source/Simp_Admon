<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\PropuestaRegulatoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Flujo WEB completo de propuestas (agenda regulatoria) por rol: petición HTTP real →
 * middleware → controlador → respuesta. Mismo enfoque que TramitesFlujoWebTest y
 * AgendaFlujoWebTest.
 *
 * Matriz de permisos (config/acl.php), sembrada por AclSeeder:
 *   agenda_regulatoria.ver   → todos los roles
 *   agenda_regulatoria.crear → solo admin y enlace
 *   agenda_regulatoria.editar→ solo admin y enlace
 *
 * Reglas verificadas en AgendaRegulatoriaController:
 *   - create/store: 403 sin agenda_regulatoria.crear
 *   - show: puedeVerRegistro (borrador privado de su autor; publicada abierta en lectura)
 *   - index: se filtra por dependencia salvo admin o quien aprueba
 *   - edit/update: exige agenda_regulatoria.editar Y dependencia (o admin) — arreglado:
 *     antes bastaba con la dependencia, así jurídico/revisora/sujeto podían editar.
 */
class PropuestasFlujoWebTest extends TestCase
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

    /** Una propuesta en una dependencia, con estatus, autor y nombre opcionales. */
    private function propuesta(Dependencia $dependencia, string $estatus, ?User $autor = null, string $nombre = 'Propuesta de prueba'): PropuestaRegulatoria
    {
        return PropuestaRegulatoria::create([
            'nombre'         => $nombre,
            'dependencia_id' => $dependencia->id,
            'estatus'        => $estatus,
            'created_by'     => $autor?->id,
        ]);
    }

    // ── Acceso al listado ──────────────────────────────────────────────────

    public function test_un_invitado_no_entra_al_listado_y_es_redirigido(): void
    {
        $this->get(route('agenda-regulatoria.index'))->assertStatus(302);
    }

    public function test_todos_los_roles_autenticados_ven_el_listado(): void
    {
        foreach (self::TODOS as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('agenda-regulatoria.index'))
                ->assertOk();
        }
    }

    public function test_el_enlace_solo_lista_propuestas_de_su_dependencia(): void
    {
        $miDependencia   = Dependencia::factory()->create();
        $otraDependencia = Dependencia::factory()->create();

        $this->propuesta($miDependencia, PropuestaRegulatoria::ESTATUS_CONSULTA, null, 'Propuesta Propia Alfa');
        $this->propuesta($otraDependencia, PropuestaRegulatoria::ESTATUS_CONSULTA, null, 'Propuesta Ajena Beta');

        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $miDependencia);

        $respuesta = $this->actingAs($enlace)->get(route('agenda-regulatoria.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Propuesta Propia Alfa');
        $respuesta->assertDontSee('Propuesta Ajena Beta');
    }

    // ── Alta ───────────────────────────────────────────────────────────────

    public function test_solo_admin_y_enlace_pueden_abrir_el_alta(): void
    {
        foreach (self::PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('propuestas.create'))
                ->assertOk();
        }
    }

    public function test_los_demas_roles_no_pueden_abrir_el_alta(): void
    {
        foreach (self::NO_PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('propuestas.create'))
                ->assertForbidden();
        }
    }

    public function test_un_rol_sin_permiso_no_puede_guardar_un_alta(): void
    {
        // Payload válido (nombre + dependencia) para pasar la validación y llegar al
        // control de permiso; un rol sin agenda_regulatoria.crear recibe 403.
        $dependencia = Dependencia::factory()->create();
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->actingAs($sujeto)
            ->post(route('propuestas.store'), [
                'nombre'         => 'Propuesta de prueba desde el test',
                'dependencia_id' => $dependencia->id,
            ])
            ->assertForbidden();
    }

    // ── show: privacidad de borrador ───────────────────────────────────────

    public function test_un_borrador_ajeno_no_es_visible(): void
    {
        $dependencia = Dependencia::factory()->create();
        $borrador = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_BORRADOR, User::factory()->create());

        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('propuestas.show', $borrador))
            ->assertForbidden();
    }

    public function test_el_autor_ve_su_propio_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $miBorrador = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_BORRADOR, $enlace);

        $this->actingAs($enlace)
            ->get(route('propuestas.show', $miBorrador))
            ->assertOk();
    }

    public function test_una_propuesta_publicada_se_ve_desde_otra_dependencia(): void
    {
        $publicada = $this->propuesta(Dependencia::factory()->create(), PropuestaRegulatoria::ESTATUS_PUBLICADA);

        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->get(route('propuestas.show', $publicada))
            ->assertOk();
    }

    // ── edit/update: solo enlace edita (arreglo del permiso) ───────────────

    public function test_el_enlace_de_la_dependencia_puede_abrir_la_edicion(): void
    {
        $dependencia = Dependencia::factory()->create();
        $propuesta = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_CONSULTA);
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('propuestas.edit', $propuesta))
            ->assertOk();
    }

    public function test_un_sujeto_de_la_dependencia_no_puede_editar(): void
    {
        // Arreglo: editar exige agenda_regulatoria.editar. El sujeto (solo ver), aunque
        // sea de la dependencia, no edita: edit redirige y update da 403.
        $dependencia = Dependencia::factory()->create();
        $propuesta = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_CONSULTA);
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);

        $this->actingAs($sujeto)
            ->get(route('propuestas.edit', $propuesta))
            ->assertRedirect(route('propuestas.show', $propuesta));

        $this->actingAs($sujeto)
            ->put(route('propuestas.update', $propuesta), [])
            ->assertForbidden();
    }

    public function test_una_revisora_de_la_dependencia_no_puede_editar(): void
    {
        // La revisora aprueba y observa, pero NO edita (no tiene agenda_regulatoria.editar).
        // Separación de funciones: quien aprueba no modifica lo que aprueba.
        $dependencia = Dependencia::factory()->create();
        $propuesta = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_CONSULTA);
        $revisora = $this->usuarioConRol(User::ROL_REVISORA, $dependencia);

        $this->actingAs($revisora)
            ->put(route('propuestas.update', $propuesta), [])
            ->assertForbidden();
    }

    public function test_un_enlace_de_otra_dependencia_no_puede_actualizar(): void
    {
        // El enlace tiene el permiso, pero no es de la dependencia → no edita la ajena.
        $propuestaAjena = $this->propuesta(Dependencia::factory()->create(), PropuestaRegulatoria::ESTATUS_CONSULTA);

        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->put(route('propuestas.update', $propuestaAjena), [])
            ->assertForbidden();
    }

    // ── destroy: solo su propio borrador (enlace) o admin ──────────────────

    public function test_el_admin_puede_eliminar_cualquier_propuesta(): void
    {
        $propuesta = $this->propuesta(Dependencia::factory()->create(), PropuestaRegulatoria::ESTATUS_CONSULTA);

        $this->actingAs($this->usuarioConRol(User::ROL_ADMIN))
            ->delete(route('propuestas.destroy', $propuesta))
            ->assertRedirect(route('agenda-regulatoria.index'));

        $this->assertSoftDeleted('propuestas_regulatorias', ['id' => $propuesta->id]);
    }

    public function test_el_enlace_elimina_su_propio_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $miBorrador = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_BORRADOR, $enlace);

        $this->actingAs($enlace)
            ->delete(route('propuestas.destroy', $miBorrador))
            ->assertRedirect(route('agenda-regulatoria.index'));

        $this->assertSoftDeleted('propuestas_regulatorias', ['id' => $miBorrador->id]);
    }

    public function test_el_enlace_no_elimina_un_borrador_ajeno(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);
        $borradorAjeno = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_BORRADOR, User::factory()->create());

        $this->actingAs($enlace)
            ->delete(route('propuestas.destroy', $borradorAjeno))
            ->assertForbidden();
    }

    public function test_un_sujeto_no_puede_eliminar_ninguna_propuesta(): void
    {
        $dependencia = Dependencia::factory()->create();
        $sujeto = $this->usuarioConRol(User::ROL_SUJETO, $dependencia);
        $propuesta = $this->propuesta($dependencia, PropuestaRegulatoria::ESTATUS_BORRADOR, $sujeto);

        $this->actingAs($sujeto)
            ->delete(route('propuestas.destroy', $propuesta))
            ->assertForbidden();
    }
}
