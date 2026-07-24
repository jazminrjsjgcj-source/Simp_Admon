<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Role;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Flujo WEB completo de trámites por rol: petición HTTP real → middleware de
 * autenticación → controlador → respuesta. A diferencia de RolAdminTest/etc. (que
 * prueban los métodos de permiso del modelo en aislamiento), esto ejercita la ruta
 * de verdad y verifica el status que recibe cada rol.
 *
 * La autorización de trámites NO está en el middleware de la ruta, sino DENTRO del
 * controlador (tienePermiso('tramites.crear') → abort 403, y el listado se filtra
 * por dependencia). Estas pruebas fijan ese comportamiento visto desde fuera.
 *
 * Matriz de permisos (config/acl.php), sembrada por AclSeeder:
 *   tramites.ver   → todos los roles
 *   tramites.crear → solo admin y enlace
 */
class TramitesFlujoWebTest extends TestCase
{
    use RefreshDatabase;

    /** Roles que pueden dar de alta un trámite (tienen tramites.crear). */
    private const PUEDEN_CREAR = [User::ROL_ADMIN, User::ROL_ENLACE];

    /** Roles que NO pueden dar de alta (sin tramites.crear → 403). */
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

        // Los permisos y roles reales del sistema. Sin esto, ningún rol tendría permisos
        // (permisosActuales() los lee de los roles adjuntos) y todo daría 403.
        $this->seed(\Database\Seeders\AclSeeder::class);
        Cache::flush(); // permisosActuales() se cachea por usuario; que un test no herede otro.
    }

    /**
     * Crea un usuario con un rol REAL adjunto (no solo la columna 'rol'): el controlador
     * usa tienePermiso(), que lee de los roles adjuntos. El admin funciona porque su rol
     * sembrado trae permisos '*'.
     */
    private function usuarioConRol(string $codigo, ?Dependencia $dependencia = null): User
    {
        $user = User::factory()->create([
            'rol'            => $codigo,
            'dependencia_id' => $dependencia?->id,
        ]);

        $rol = Role::where('codigo', $codigo)->firstOrFail();
        $user->roles()->attach($rol->id);
        $user->olvidarPermisosCache();

        return $user;
    }

    public function test_un_invitado_no_entra_al_listado_y_es_redirigido(): void
    {
        // Sin autenticar, el middleware 'auth' redirige (302), no muestra el listado.
        $this->get(route('tramites.index'))->assertStatus(302);
    }

    public function test_todos_los_roles_autenticados_ven_el_listado(): void
    {
        // El listado no tiene candado de permiso (solo filtra por dependencia), así que
        // cualquier rol autenticado entra con 200. Lo que ve, filtrado, se prueba aparte.
        foreach (self::TODOS as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('tramites.index'))
                ->assertOk();
        }
    }

    public function test_solo_admin_y_enlace_pueden_abrir_el_alta_de_tramite(): void
    {
        foreach (self::PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('tramites.create'))
                ->assertOk();
        }
    }

    public function test_los_demas_roles_no_pueden_abrir_el_alta_de_tramite(): void
    {
        foreach (self::NO_PUEDEN_CREAR as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('tramites.create'))
                ->assertForbidden(); // 403: no tiene tramites.crear
        }
    }

    // ── show: la privacidad real es por BORRADOR, no por dependencia ────────
    //
    // Un borrador es privado de su creador (o del admin). Un trámite ya publicado
    // (no borrador) se puede VER desde cualquier dependencia en modo lectura; la
    // restricción por dependencia aplica a editar/gestionar, no a ver.

    public function test_un_borrador_ajeno_no_es_visible_ni_por_url_directa(): void
    {
        $dependencia = Dependencia::factory()->create();
        $borradorAjeno = Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'estatus'        => Tramite::ESTATUS_BORRADOR,
            'created_by'     => User::factory()->create()->id, // lo creó otra persona
        ]);

        // Un enlace de la MISMA dependencia, pero que NO es el autor, no debe verlo.
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $this->actingAs($enlace)
            ->get(route('tramites.show', $borradorAjeno))
            ->assertForbidden();
    }

    public function test_el_autor_si_ve_su_propio_borrador(): void
    {
        $dependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $dependencia);

        $miBorrador = Tramite::factory()->create([
            'dependencia_id' => $dependencia->id,
            'estatus'        => Tramite::ESTATUS_BORRADOR,
            'created_by'     => $enlace->id, // es su propio borrador
        ]);

        $this->actingAs($enlace)
            ->get(route('tramites.show', $miBorrador))
            ->assertOk();
    }

    public function test_un_tramite_publicado_se_ve_desde_otra_dependencia(): void
    {
        // Regla real (puedeVerRegistro): un trámite no-borrador es visible en lectura
        // desde cualquier dependencia. Esto documenta ese comportamiento.
        $tramitePublicado = Tramite::factory()->create([
            'dependencia_id' => Dependencia::factory()->create()->id,
            'estatus'        => Tramite::ESTATUS_COMPLETADO,
        ]);

        $enlaceDeOtra = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlaceDeOtra)
            ->get(route('tramites.show', $tramitePublicado))
            ->assertOk();
    }

    // ── index: el listado SÍ está acotado por dependencia ──────────────────

    public function test_el_enlace_solo_lista_tramites_de_su_dependencia(): void
    {
        $miDependencia   = Dependencia::factory()->create();
        $otraDependencia = Dependencia::factory()->create();

        // Ambos NO borrador, para aislar la regla de DEPENDENCIA (si fueran borradores,
        // se activaría además el filtro de borrador y no sabríamos cuál excluyó cuál).
        Tramite::factory()->create([
            'dependencia_id' => $miDependencia->id,
            'estatus'        => Tramite::ESTATUS_COMPLETADO,
            'nombre_oficial' => 'Tramite Propio Alfa',
        ]);
        Tramite::factory()->create([
            'dependencia_id' => $otraDependencia->id,
            'estatus'        => Tramite::ESTATUS_COMPLETADO,
            'nombre_oficial' => 'Tramite Ajeno Beta',
        ]);

        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $miDependencia);

        $respuesta = $this->actingAs($enlace)->get(route('tramites.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Tramite Propio Alfa');
        $respuesta->assertDontSee('Tramite Ajeno Beta');
    }

    public function test_puedo_ver_un_tramite_requisito_publicado_de_otra_dependencia(): void
    {
        // Escenario real: un trámite MÍO cita como relacionado (requisito) un trámite
        // de OTRA dependencia. Si ese trámite ya está publicado (no borrador), debo
        // poder abrirlo desde el detalle del mío, sin 403 —así funcionan los enlaces
        // "Ver" de la sección de trámites relacionados—.
        $miDependencia   = Dependencia::factory()->create();
        $otraDependencia = Dependencia::factory()->create();
        $enlace = $this->usuarioConRol(User::ROL_ENLACE, $miDependencia);

        $requisitoAjeno = Tramite::factory()->create([
            'dependencia_id' => $otraDependencia->id,
            'estatus'        => Tramite::ESTATUS_COMPLETADO, // publicado
        ]);

        $miTramite = Tramite::factory()->create([
            'dependencia_id' => $miDependencia->id,
            'estatus'        => Tramite::ESTATUS_COMPLETADO,
            'created_by'     => $enlace->id,
        ]);
        $miTramite->relacionados()->attach($requisitoAjeno->id);

        // Abrir directamente el trámite-requisito de la otra dependencia: permitido.
        $this->actingAs($enlace)
            ->get(route('tramites.show', $requisitoAjeno))
            ->assertOk();
    }

    public function test_un_tramite_requisito_en_borrador_de_otra_dependencia_no_se_ve(): void
    {
        // El reverso: si el requisito de otra dependencia AÚN es borrador, sigue siendo
        // privado de su autor —la privacidad del borrador gana sobre la relación—.
        $otraDependencia = Dependencia::factory()->create();

        $requisitoBorrador = Tramite::factory()->create([
            'dependencia_id' => $otraDependencia->id,
            'estatus'        => Tramite::ESTATUS_BORRADOR,
            'created_by'     => User::factory()->create()->id, // otro autor
        ]);

        $enlace = $this->usuarioConRol(User::ROL_ENLACE, Dependencia::factory()->create());

        $this->actingAs($enlace)
            ->get(route('tramites.show', $requisitoBorrador))
            ->assertForbidden();
    }
}
