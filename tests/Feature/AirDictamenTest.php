<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\PropuestaRegulatoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del módulo AIR (Análisis de Impacto Regulatorio) y sus EXENCIONES.
 *
 * ── Qué se juega aquí ──
 *
 * Antes de que un ayuntamiento saque una regulación nueva tiene que analizar qué
 * cuesta y a quién afecta. Eso es el AIR. Y hay casos en que la ley permite
 * saltárselo: eso es la exención del artículo 36 de la LNETB.
 *
 * Las dos decisiones son de peso, y por eso están repartidas entre dos manos
 * distintas: quien REDACTA el análisis no puede ser quien lo APRUEBA. Si esa
 * separación se rompiera, una dependencia podría dictaminarse a sí misma su
 * propia propuesta, que es exactamente lo que el trámite pretende evitar.
 *
 * Estas pruebas fijan tres cosas: que solo quien tiene el permiso correcto ejecuta
 * cada paso, que una dependencia no toca el AIR de otra, y que rechazar una
 * exención devuelve la propuesta al camino largo en vez de dejarla en el aire.
 */
class AirDictamenTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos viven en la base; sin el ACL nadie podría hacer nada.
        $this->seed(\Database\Seeders\AclSeeder::class);

        $this->dependencia = Dependencia::factory()->create();
    }

    /**
     * Los permisos salen de los roles del ACL, no de la columna `rol`: sin asignar
     * el rol correspondiente el usuario existe pero no puede hacer nada.
     */
    private function usuario(string $rol, ?Dependencia $dependencia = null): User
    {
        $usuario = User::factory()->create([
            'rol'            => $rol,
            'activo'         => true,
            'dependencia_id' => ($dependencia ?? $this->dependencia)->id,
        ]);

        $usuario->roles()->attach(Role::where('codigo', $rol)->firstOrFail()->id);
        $usuario->olvidarPermisosCache();

        return $usuario;
    }

    private function propuesta(?Dependencia $dependencia = null, ?User $autor = null): PropuestaRegulatoria
    {
        return PropuestaRegulatoria::create([
            'nombre'         => 'Reglamento de anuncios publicitarios',
            'dependencia_id' => ($dependencia ?? $this->dependencia)->id,
            'estatus'        => PropuestaRegulatoria::ESTATUS_CONSULTA,
            'created_by'     => $autor?->id,
        ]);
    }

    /** Contenido mínimo aceptable de un AIR. */
    private function datosAir(array $extra = []): array
    {
        return array_merge([
            'problematica' => 'Los anuncios en vía pública carecen de regulación uniforme.',
            'objetivos'    => 'Ordenar la instalación de anuncios y reducir riesgos.',
            'beneficios'   => 'Mayor seguridad vial y certeza para los comercios.',
        ], $extra);
    }

    // ─────────────────────────────────────────────────────────────
    //  Redactar el AIR
    // ─────────────────────────────────────────────────────────────

    public function test_el_enlace_abre_el_formulario_de_su_propuesta(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)
            ->get(route('air.formulario', $propuesta))
            ->assertOk();
    }

    public function test_el_enlace_guarda_el_air_como_borrador(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)
            ->post(route('air.guardar', $propuesta), $this->datosAir());

        $air = $propuesta->fresh()->air;

        $this->assertNotNull($air, 'Debería haberse creado el AIR.');
        $this->assertSame('borrador', $air->estatus);
    }

    public function test_enviar_el_air_lo_deja_marcado_como_enviado(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)
            ->post(route('air.guardar', $propuesta), $this->datosAir(['accion' => 'enviar']));

        $this->assertSame('enviado', $propuesta->fresh()->air->estatus);
    }

    /**
     * Cada dependencia responde por lo suyo. Sin este límite, cualquier enlace
     * podría reescribir el análisis de otra área.
     */
    public function test_una_dependencia_no_toca_el_air_de_otra(): void
    {
        $otraDependencia = Dependencia::factory()->create();
        $ajeno           = $this->usuario(User::ROL_ENLACE, $otraDependencia);
        $propuesta       = $this->propuesta();   // es de $this->dependencia

        $this->actingAs($ajeno)
            ->post(route('air.guardar', $propuesta), $this->datosAir())
            ->assertForbidden();

        $this->assertNull($propuesta->fresh()->air);
    }

    /**
     * La revisora es transversal: revisa el AIR de todas las dependencias, no solo
     * el de la suya. Es lo contrario del caso anterior, y conviene tener los dos.
     */
    public function test_la_revisora_entra_al_air_de_cualquier_dependencia(): void
    {
        $otraDependencia = Dependencia::factory()->create();
        $revisora        = $this->usuario(User::ROL_REVISORA, $otraDependencia);
        $propuesta       = $this->propuesta();

        $this->actingAs($revisora)
            ->get(route('air.formulario', $propuesta))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────
    //  Dictaminar
    // ─────────────────────────────────────────────────────────────

    public function test_la_revisora_dictamina_y_la_propuesta_queda_dictaminada(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);
        $this->actingAs($enlace)->post(route('air.guardar', $propuesta), $this->datosAir());

        $revisora = $this->usuario(User::ROL_REVISORA);

        $this->actingAs($revisora)
            ->post(route('air.dictaminar', $propuesta), [
                'dictamen'               => 'favorable',
                'dictamen_observaciones' => 'Cumple con los requisitos.',
            ])
            ->assertRedirect(route('propuestas.show', $propuesta));

        $air = $propuesta->fresh()->air;

        $this->assertSame('dictaminado', $air->estatus);
        $this->assertSame('favorable', $air->dictamen);
        $this->assertSame($revisora->id, $air->dictaminado_por);
        $this->assertSame(
            PropuestaRegulatoria::ESTATUS_DICTAMINADA,
            $propuesta->fresh()->estatus
        );
    }

    /**
     * Quien redacta no dictamina. Es la separación que da sentido al trámite: si un
     * enlace pudiera aprobar su propio análisis, el dictamen no valdría nada.
     */
    public function test_un_enlace_no_puede_dictaminar_su_propio_air(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);
        $this->actingAs($enlace)->post(route('air.guardar', $propuesta), $this->datosAir());

        $this->actingAs($enlace)
            ->post(route('air.dictaminar', $propuesta), ['dictamen' => 'favorable'])
            ->assertForbidden();

        $this->assertNotSame('dictaminado', $propuesta->fresh()->air->estatus);
    }

    public function test_no_se_dictamina_una_propuesta_sin_air(): void
    {
        $propuesta = $this->propuesta();
        $revisora  = $this->usuario(User::ROL_REVISORA);

        $this->actingAs($revisora)
            ->post(route('air.dictaminar', $propuesta), ['dictamen' => 'favorable'])
            ->assertSessionHas('error');

        $this->assertNotSame(
            PropuestaRegulatoria::ESTATUS_DICTAMINADA,
            $propuesta->fresh()->estatus
        );
    }

    public function test_el_dictamen_solo_admite_favorable_o_no_favorable(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);
        $this->actingAs($enlace)->post(route('air.guardar', $propuesta), $this->datosAir());

        $this->actingAs($this->usuario(User::ROL_REVISORA))
            ->post(route('air.dictaminar', $propuesta), ['dictamen' => 'mas_o_menos'])
            ->assertSessionHasErrors('dictamen');
    }

    // ─────────────────────────────────────────────────────────────
    //  Exención del artículo 36
    // ─────────────────────────────────────────────────────────────

    public function test_el_enlace_solicita_una_exencion(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)
            ->post(route('air.exencion.guardar', $propuesta), [
                'fracciones'    => [1, 3],
                'justificacion' => 'La modificación es de forma y no genera costos de cumplimiento.',
            ]);

        $this->assertNotNull($propuesta->fresh()->exencion);
    }

    /**
     * Pedir exención sin explicar por qué convertiría el trámite en un formulismo.
     * De ahí el mínimo de treinta caracteres y de una fracción.
     */
    public function test_la_exencion_exige_fraccion_y_justificacion_suficiente(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)
            ->post(route('air.exencion.guardar', $propuesta), [
                'fracciones'    => [],
                'justificacion' => 'porque sí',
            ])
            ->assertSessionHasErrors(['fracciones', 'justificacion']);

        $this->assertNull($propuesta->fresh()->exencion);
    }

    public function test_las_fracciones_estan_acotadas_al_catalogo_del_articulo_36(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        // El artículo 36 tiene ocho fracciones; la novena no existe.
        $this->actingAs($enlace)
            ->post(route('air.exencion.guardar', $propuesta), [
                'fracciones'    => [9],
                'justificacion' => 'La modificación es de forma y no genera costos de cumplimiento.',
            ])
            ->assertSessionHasErrors('fracciones.0');
    }

    /**
     * El caso que más importa de la exención: si se RECHAZA, la propuesta no puede
     * quedarse en el limbo. Vuelve a consulta y marcada como que requiere AIR, es
     * decir, al camino largo.
     */
    public function test_rechazar_la_exencion_devuelve_la_propuesta_al_camino_largo(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)->post(route('air.exencion.guardar', $propuesta), [
            'fracciones'    => [1],
            'justificacion' => 'La modificación es de forma y no genera costos de cumplimiento.',
        ]);

        $this->actingAs($this->usuario(User::ROL_REVISORA))
            ->post(route('air.exencion.resolver', $propuesta), ['resolucion' => 'rechazada']);

        $propuesta->refresh();

        $this->assertSame('rechazada', $propuesta->exencion->estatus);
        $this->assertSame(PropuestaRegulatoria::AIR_REQUIERE_AIR, $propuesta->determinacion_air);
        $this->assertSame(PropuestaRegulatoria::ESTATUS_CONSULTA, $propuesta->estatus);
    }

    public function test_aprobar_la_exencion_la_deja_aprobada(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)->post(route('air.exencion.guardar', $propuesta), [
            'fracciones'    => [1],
            'justificacion' => 'La modificación es de forma y no genera costos de cumplimiento.',
        ]);

        $this->actingAs($this->usuario(User::ROL_REVISORA))
            ->post(route('air.exencion.resolver', $propuesta), ['resolucion' => 'aprobada']);

        $this->assertSame('aprobada', $propuesta->fresh()->exencion->estatus);
    }

    public function test_un_enlace_no_resuelve_su_propia_exencion(): void
    {
        $enlace    = $this->usuario(User::ROL_ENLACE);
        $propuesta = $this->propuesta(autor: $enlace);

        $this->actingAs($enlace)->post(route('air.exencion.guardar', $propuesta), [
            'fracciones'    => [1],
            'justificacion' => 'La modificación es de forma y no genera costos de cumplimiento.',
        ]);

        $this->actingAs($enlace)
            ->post(route('air.exencion.resolver', $propuesta), ['resolucion' => 'aprobada'])
            ->assertForbidden();

        $this->assertNotSame('aprobada', $propuesta->fresh()->exencion->estatus);
    }
}
