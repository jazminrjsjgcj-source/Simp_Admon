<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Tramite;
use App\Models\User;
use App\Services\ConsultaDatosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pruebas del módulo de PREGUNTAS A LOS DATOS ("¿cuántos trámites en borrador?").
 *
 * ── Qué se está protegiendo ──
 *
 * En este módulo una IA traduce la pregunta del usuario a una "receta" y este
 * servicio la ejecuta. La IA se puede equivocar, y la pregunta viene de fuera, así
 * que la seguridad NO puede depender de que el modelo se porte bien: depende de
 * que este servicio rechace todo lo que no esté declarado en
 * config/consulta_datos.php.
 *
 * Por eso la mitad de estas pruebas comprueban RECHAZOS. Si alguna dejara de
 * fallar, significaría que se abrió una puerta: consultar una tabla no declarada,
 * filtrar por una columna arbitraria o colar un valor inesperado.
 *
 * La otra mitad comprueba que los números que salen son los de la base, porque una
 * respuesta con formato bonito y cifra equivocada es peor que no responder.
 */
class ConsultaDatosTest extends TestCase
{
    use RefreshDatabase;

    private ConsultaDatosService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(ConsultaDatosService::class);
    }

    // ─────────────────────────────────────────────────────────────
    //  Los números salen de la base
    // ─────────────────────────────────────────────────────────────

    public function test_cuenta_los_tramites_que_cumplen_el_filtro(): void
    {
        Tramite::factory()->count(3)->create(['estatus' => 'borrador']);
        Tramite::factory()->count(2)->create(['estatus' => 'completado']);

        $r = $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'conteo',
            'filtros' => ['estatus' => 'borrador'],
        ]);

        $this->assertSame('conteo', $r['tipo']);
        $this->assertSame(3, $r['total']);
    }

    public function test_sin_filtros_cuenta_todo(): void
    {
        Tramite::factory()->count(4)->create();

        $r = $this->servicio->ejecutar(['entidad' => 'tramites', 'metrica' => 'conteo']);

        $this->assertSame(4, $r['total']);
    }

    public function test_la_lista_devuelve_filas_y_respeta_el_tope(): void
    {
        Tramite::factory()->count(3)->create(['estatus' => 'borrador']);

        $r = $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'lista',
            'filtros' => ['estatus' => 'borrador'],
        ]);

        $this->assertSame('lista', $r['tipo']);
        $this->assertSame(3, $r['total']);
        $this->assertCount(3, $r['filas']);
        $this->assertArrayHasKey('nombre', $r['filas'][0]);
        $this->assertLessThanOrEqual(config('consulta_datos.limite_lista'), count($r['filas']));
    }

    public function test_agrupa_por_una_columna(): void
    {
        Tramite::factory()->count(2)->create(['estatus' => 'borrador']);
        Tramite::factory()->count(1)->create(['estatus' => 'completado']);

        $r = $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'agrupar',
            'agrupar' => 'estatus',
        ]);

        $this->assertSame('agrupar', $r['tipo']);

        $porGrupo = collect($r['grupos'])->pluck('total', 'grupo');
        $this->assertSame(2, $porGrupo['borrador']);
        $this->assertSame(1, $porGrupo['completado']);
    }

    /**
     * Agrupar por dependencia obliga a resolver un JOIN. Se hace leyendo las llaves
     * de la relación de Eloquent, no escribiéndolas a mano, para que siga
     * funcionando si mañana cambia una FK.
     */
    public function test_agrupa_por_dependencia_resolviendo_la_relacion(): void
    {
        $tesoreria = Dependencia::factory()->create(['nombre' => 'Tesorería Municipal']);
        $obras     = Dependencia::factory()->create(['nombre' => 'Obras Públicas']);

        Tramite::factory()->count(2)->create(['dependencia_id' => $tesoreria->id]);
        Tramite::factory()->count(1)->create(['dependencia_id' => $obras->id]);

        $r = $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'agrupar',
            'agrupar' => 'dependencia',
        ]);

        $porGrupo = collect($r['grupos'])->pluck('total', 'grupo');
        $this->assertSame(2, $porGrupo['Tesorería Municipal']);
        $this->assertSame(1, $porGrupo['Obras Públicas']);
    }

    public function test_agrupa_por_mes(): void
    {
        Tramite::factory()->count(2)->create(['created_at' => '2026-03-10 10:00:00']);
        Tramite::factory()->count(1)->create(['created_at' => '2026-04-02 10:00:00']);

        $r = $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'agrupar',
            'agrupar' => 'mes',
        ]);

        $porGrupo = collect($r['grupos'])->pluck('total', 'grupo');
        $this->assertSame(2, $porGrupo['2026-03']);
        $this->assertSame(1, $porGrupo['2026-04']);
    }

    // ─────────────────────────────────────────────────────────────
    //  La lista blanca rechaza todo lo que no esté declarado
    // ─────────────────────────────────────────────────────────────

    public function test_rechaza_una_entidad_no_declarada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // 'users' NO está en el catálogo, y no debe poder consultarse por aquí.
        $this->servicio->ejecutar(['entidad' => 'users', 'metrica' => 'conteo']);
    }

    public function test_rechaza_un_filtro_no_declarado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'conteo',
            'filtros' => ['password' => 'x'],
        ]);
    }

    public function test_rechaza_un_valor_fuera_del_catalogo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'conteo',
            'filtros' => ['estatus' => 'inventado'],
        ]);
    }

    public function test_rechaza_agrupar_por_una_dimension_no_declarada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->ejecutar([
            'entidad' => 'tramites',
            'metrica' => 'agrupar',
            'agrupar' => 'password',
        ]);
    }

    public function test_rechaza_una_metrica_desconocida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->ejecutar(['entidad' => 'tramites', 'metrica' => 'borrar']);
    }

    /**
     * El catálogo declara un permiso por entidad. Que exista es parte del contrato:
     * sin él, el orquestador no podría filtrar por rol y cualquiera vería los
     * conteos de cualquier módulo.
     */
    public function test_cada_entidad_declara_su_permiso(): void
    {
        foreach (config('consulta_datos.entidades') as $clave => $entidad) {
            $this->assertNotEmpty(
                $entidad['permiso'] ?? null,
                "La entidad '{$clave}' debería declarar un permiso."
            );
        }
    }
}
