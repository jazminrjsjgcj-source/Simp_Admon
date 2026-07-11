<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de los roles SUJETO OBLIGADO y JURÍDICO.
 *
 * Los dos firman, pero su alcance es muy distinto:
 *
 *   - SUJETO OBLIGADO: es el titular que FIRMA. Solo consulta (ver) y firma. No
 *     captura, no aprueba, no gestiona catálogos.
 *
 *   - JURÍDICO: gestiona las REGULACIONES (crear, editar, eliminar) y OBSERVA los
 *     trámites y las agendas —puede señalar lo que hay que corregir—, pero no los
 *     aprueba: eso es de la revisora. Por eso no tiene visión transversal del
 *     módulo, aunque sí observe.
 *
 * Ese último matiz (observar ≠ aprobar) es sutil y conviene dejarlo por escrito: el
 * permiso de observar NO da visión total del módulo.
 */
class RolSujetoYJuridicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los poderes de estos roles son puros permisos (no hay métodos especiales
        // en User), y viven en la base. Sin el ACL sembrado no tendrían ninguno.
        $this->seed(\Database\Seeders\AclSeeder::class);
    }

    /** Crea un usuario con su rol y sus permisos ya enganchados. */
    private function usuarioCon(string $rol): User
    {
        $usuario = User::factory()->create(['rol' => $rol]);

        $registro = Role::where('codigo', $rol)->firstOrFail();
        $usuario->roles()->attach($registro->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    // ── Sujeto obligado: consulta y firma ────────────────────────────────

    public function test_el_sujeto_obligado_puede_firmar(): void
    {
        // Es su razón de ser: el titular que da fe con su firma.
        $this->assertTrue($this->usuarioCon(User::ROL_SUJETO)->tienePermiso('firmas.firmar'));
    }

    public function test_el_sujeto_obligado_puede_consultar_tramites(): void
    {
        $this->assertTrue($this->usuarioCon(User::ROL_SUJETO)->tienePermiso('tramites.ver'));
    }

    public function test_el_sujeto_obligado_NO_puede_crear_tramites(): void
    {
        // El sujeto obligado no captura: eso es del enlace.
        $this->assertFalse($this->usuarioCon(User::ROL_SUJETO)->tienePermiso('tramites.crear'));
    }

    public function test_el_sujeto_obligado_NO_aprueba(): void
    {
        // Firmar no es aprobar: la aprobación es de la revisora.
        $this->assertFalse($this->usuarioCon(User::ROL_SUJETO)->tienePermiso('tramites.aprobar'));
    }

    public function test_el_sujeto_obligado_no_ve_todo_el_modulo(): void
    {
        // Sin permiso de aprobar no hay visión transversal: solo lo de su área.
        $this->assertFalse($this->usuarioCon(User::ROL_SUJETO)->veTodoElModulo('tramites'));
    }

    // ── Jurídico: gestiona regulaciones y observa ────────────────────────

    public function test_el_juridico_gestiona_las_regulaciones(): void
    {
        // El catálogo normativo es su terreno.
        $juridico = $this->usuarioCon(User::ROL_JURIDICO);

        $this->assertTrue($juridico->tienePermiso('regulaciones.crear'));
        $this->assertTrue($juridico->tienePermiso('regulaciones.editar'));
        $this->assertTrue($juridico->tienePermiso('regulaciones.eliminar'));
    }

    public function test_el_juridico_puede_observar_una_propuesta_regulatoria(): void
    {
        // Puede señalar lo que hay que corregir en una propuesta.
        $this->assertTrue(
            $this->usuarioCon(User::ROL_JURIDICO)->tienePermiso('agenda_regulatoria.observar')
        );
    }

    public function test_el_juridico_observa_pero_NO_aprueba(): void
    {
        // El matiz que define su papel: puede objetar, pero el visto bueno final no
        // es suyo. Si esta prueba fallara, el jurídico estaría aprobando sin serlo.
        $juridico = $this->usuarioCon(User::ROL_JURIDICO);

        $this->assertTrue($juridico->tienePermiso('tramites.observar'));
        $this->assertFalse($juridico->tienePermiso('tramites.aprobar'));
    }

    public function test_el_juridico_no_tiene_vision_transversal_del_modulo(): void
    {
        // Observar NO da visión total: el jurídico trabaja sobre su propia área, no
        // sobre todas las dependencias (eso es de la revisora y del admin).
        $this->assertFalse($this->usuarioCon(User::ROL_JURIDICO)->veTodoElModulo('tramites'));
    }

    public function test_el_juridico_no_ve_el_borrador_de_otra_persona(): void
    {
        $autor = $this->usuarioCon(User::ROL_ENLACE);

        $borrador = Tramite::factory()->create([
            'estatus'    => Tramite::ESTATUS_BORRADOR,
            'created_by' => $autor->id,
        ]);

        $this->assertFalse(
            $this->usuarioCon(User::ROL_JURIDICO)->puedeVerRegistro($borrador, 'tramites'),
            'El trabajo en proceso es privado de quien lo escribe.'
        );
    }

    public function test_el_juridico_tambien_firma(): void
    {
        $this->assertTrue($this->usuarioCon(User::ROL_JURIDICO)->tienePermiso('firmas.firmar'));
    }
}
