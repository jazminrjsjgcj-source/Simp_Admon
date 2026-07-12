<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Regulacion;
use App\Models\Tramite;
use App\Services\BuscadorService;
use Database\Seeders\DiccionarioJuridicoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del buscador (BuscadorService) y sus filtros.
 *
 * ── Qué se prueba aquí, y qué NO ─────────────────────────────────────
 *
 * El buscador hace dos cosas muy distintas, y solo una de ellas se puede probar
 * de forma útil:
 *
 *   1. DECIDE. Antes de tocar la base, decide en qué modo va a buscar
 *      ('enfocado', 'completo' o 'filtrado') y en qué fuentes. Esa decisión es
 *      lógica pura, es la parte que se rompe al refactorizar, y es lo que el
 *      usuario percibe como "los filtros". → SE PRUEBA AQUÍ.
 *
 *   2. PUNTÚA. Ordena los resultados por relevancia usando el full-text de
 *      PostgreSQL. Eso depende del motor, de los índices y del idioma
 *      configurado. Probar que "licencia" salga antes que "permiso" sería probar
 *      PostgreSQL, no PUNTA. → NO SE PRUEBA AQUÍ.
 *
 * ── Los tres modos, explicados desde cero ────────────────────────────
 *
 * 'enfocado'  El diccionario jurídico reconoció un concepto de tipo "dato" en la
 *             consulta. Por ejemplo, "costo" apunta a la tabla `requisitos`. En
 *             vez de buscar en las 5 fuentes, se busca SOLO ahí: es más rápido y
 *             evita que resultados genéricos le ganen al dato real en el ranking.
 *
 * 'completo'  No se reconoció ningún concepto enfocable (o la búsqueda enfocada
 *             no encontró nada, o el usuario pidió "ver todo"). Se buscan las 5
 *             fuentes de siempre.
 *
 * 'filtrado'  El usuario eligió una regulación concreta. Se busca en las 6
 *             fuentes (entra también la agenda), pero restringido a esa ley.
 *
 * ── Aviso honesto ────────────────────────────────────────────────────
 *
 * Estas son las pruebas con más probabilidad de dar sorpresas al correrlas por
 * primera vez, porque el buscador consulta full-text real contra PostgreSQL. Si
 * alguna falla, el mensaje de error es información, no un problema: dime qué sale
 * y lo ajustamos.
 */
class BuscadorFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    /**
     * Un trámite con un nombre que se pueda buscar. El buscador consulta la tabla
     * `tramites` entre sus 5 fuentes.
     */
    private function tramiteLlamado(string $nombre): Tramite
    {
        return Tramite::factory()->create([
            'nombre_oficial' => $nombre,
            'objetivo'       => 'Permitir el ejercicio de actividades comerciales.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. La puerta de entrada
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Una consulta de menos de 2 caracteres no dispara ninguna búsqueda. Devuelve
     * la estructura vacía sin tocar la base.
     *
     * Importa porque el buscador se dispara mientras el usuario escribe: sin esta
     * guarda, cada letra tecleada lanzaría 5 consultas full-text.
     */
    public function test_una_consulta_demasiado_corta_no_busca_nada(): void
    {
        $resultado = $this->buscador->buscar('a');

        $this->assertTrue($resultado['resultados']->isEmpty());
        $this->assertNull($resultado['respuesta_destacada']);
        $this->assertSame('completo', $resultado['modo']);
    }

    public function test_la_respuesta_siempre_trae_las_tres_claves(): void
    {
        // El controlador y la vista dependen de esta forma. Si alguien renombra una
        // clave al refactorizar, la vista se rompe sin que nadie lo vea venir.
        $resultado = $this->buscador->buscar('licencia');

        $this->assertArrayHasKey('resultados', $resultado);
        $this->assertArrayHasKey('respuesta_destacada', $resultado);
        $this->assertArrayHasKey('modo', $resultado);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Filtro por TIPO de fuente
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cuando el usuario marca "solo trámites", el buscador debe devolver SOLO
     * resultados de tipo trámite. Nada de artículos, ni requisitos, ni fundamentos.
     *
     * Esta es la prueba del filtro más usado de la interfaz.
     */
    public function test_el_filtro_por_tipo_solo_devuelve_esa_fuente(): void
    {
        $this->tramiteLlamado('Licencia de funcionamiento comercial');

        $resultado = $this->buscador->buscar('licencia', tipos: ['tramite']);

        $tipos = $resultado['resultados']->pluck('tipo')->unique();

        $this->assertTrue(
            $tipos->isEmpty() || $tipos->every(fn ($t) => $t === 'tramite'),
            'Con el filtro en "tramite" se colaron resultados de otra fuente: ' . $tipos->implode(', ')
        );
    }

    /**
     * El filtro acepta varias fuentes a la vez: el usuario puede marcar "trámites" y
     * "requisitos" y descartar el resto.
     */
    public function test_el_filtro_por_tipo_acepta_varias_fuentes(): void
    {
        $this->tramiteLlamado('Licencia de funcionamiento comercial');

        $resultado = $this->buscador->buscar('licencia', tipos: ['tramite', 'requisito']);

        $tipos = $resultado['resultados']->pluck('tipo')->unique();

        $this->assertTrue(
            $tipos->every(fn ($t) => in_array($t, ['tramite', 'requisito'], true)),
            'Se colaron fuentes fuera del filtro: ' . $tipos->implode(', ')
        );
    }

    /**
     * REGLA QUE SE ROMPE FÁCIL AL REFACTORIZAR.
     *
     * Si el usuario activó un filtro de tipos, el enrutamiento automático por
     * concepto se DESACTIVA. Tiene sentido: el usuario ya dijo dónde quiere buscar,
     * así que el diccionario jurídico no debe llevárselo a otra fuente por su
     * cuenta.
     *
     * Sin esta regla, un usuario que filtra "solo artículos" y escribe "costo"
     * acabaría viendo requisitos, porque el diccionario mandaría la búsqueda ahí.
     */
    public function test_con_filtro_de_tipos_el_modo_nunca_es_enfocado(): void
    {
        $this->seed(DiccionarioJuridicoSeeder::class);

        // "costo" es justo la palabra que el diccionario enrutaría a `requisitos`.
        $resultado = $this->buscador->buscar('costo', tipos: ['articulo']);

        $this->assertNotSame(
            'enfocado',
            $resultado['modo'],
            'El filtro del usuario debe ganarle al enrutamiento automático del diccionario.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Filtro por REGULACIÓN
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Cuando el usuario elige una ley concreta ("busca solo dentro del Reglamento de
     * Comercio"), el modo pasa a 'filtrado'.
     *
     * En ese modo cambian DOS cosas respecto a la búsqueda normal, y las dos hay que
     * congelarlas:
     *   1. Se desactiva el enrutamiento por concepto (el universo ya está acotado).
     *   2. Entra una sexta fuente, las acciones de agenda, que en las búsquedas
     *      generales se deja fuera para no saturar los resultados.
     */
    public function test_filtrar_por_regulacion_cambia_el_modo_a_filtrado(): void
    {
        $regulacion = Regulacion::factory()->create(['nombre' => 'Reglamento de Comercio']);

        $resultado = $this->buscador->buscar('licencia', regulacionIds: [$regulacion->id]);

        $this->assertSame('filtrado', $resultado['modo']);
    }

    public function test_con_filtro_de_regulacion_el_modo_nunca_es_enfocado(): void
    {
        $this->seed(DiccionarioJuridicoSeeder::class);

        $regulacion = Regulacion::factory()->create(['nombre' => 'Reglamento de Comercio']);

        $resultado = $this->buscador->buscar('costo', regulacionIds: [$regulacion->id]);

        $this->assertSame(
            'filtrado',
            $resultado['modo'],
            'Haber elegido una ley debe ganarle al enrutamiento por concepto.'
        );
    }

    /**
     * Los dos filtros se pueden combinar: "busca 'licencia' solo en los artículos
     * del Reglamento de Comercio".
     */
    public function test_los_dos_filtros_se_pueden_combinar(): void
    {
        $regulacion = Regulacion::factory()->create(['nombre' => 'Reglamento de Comercio']);

        $resultado = $this->buscador->buscar(
            'licencia',
            regulacionIds: [$regulacion->id],
            tipos: ['articulo'],
        );

        $this->assertSame('filtrado', $resultado['modo']);

        $tipos = $resultado['resultados']->pluck('tipo')->unique();
        $this->assertTrue(
            $tipos->isEmpty() || $tipos->every(fn ($t) => $t === 'articulo'),
            'El filtro de tipos debe seguir aplicándose dentro del modo filtrado.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. Modo explorar (forzarCompleto)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * La vista ofrece un enlace "Ver todos los resultados relacionados". Ese enlace
     * pasa forzarCompleto = true, que ignora el enrutamiento por concepto y busca en
     * las 5 fuentes pase lo que pase.
     *
     * Es la válvula de escape del usuario cuando el buscador se pasa de listo y le
     * enfoca la búsqueda donde no quería.
     */
    public function test_forzar_completo_ignora_el_enrutamiento_por_concepto(): void
    {
        $this->seed(DiccionarioJuridicoSeeder::class);

        $resultado = $this->buscador->buscar('costo', forzarCompleto: true);

        $this->assertSame(
            'completo',
            $resultado['modo'],
            'Con "ver todos los resultados", el buscador nunca debe enfocarse.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. El respaldo automático (la regla más sutil del servicio)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA REGLA MÁS FÁCIL DE PERDER EN UN REFACTOR.
     *
     * Si el diccionario decide enfocar la búsqueda en una sola fuente y esa fuente
     * no devuelve NADA, el buscador NO se rinde: amplía automáticamente a las 5
     * fuentes, sin que el usuario tenga que pedir nada.
     *
     * El razonamiento está escrito en el código: "una búsqueda enfocada que no
     * encontró nada no le sirve a nadie".
     *
     * Es una rama de código que solo se ejecuta cuando la búsqueda enfocada falla,
     * así que es justo la que alguien podría borrar por parecer código muerto.
     * Aquí queda fijada.
     */
    public function test_si_la_busqueda_enfocada_no_encuentra_nada_se_amplia_sola(): void
    {
        $this->seed(DiccionarioJuridicoSeeder::class);

        // Base vacía de requisitos: la búsqueda enfocada en `requisitos` no puede
        // devolver nada. El buscador debe caer al modo completo por su cuenta.
        $resultado = $this->buscador->buscar('costo');

        $this->assertSame(
            'completo',
            $resultado['modo'],
            'La búsqueda enfocada no encontró nada y el buscador se quedó ahí, '
            . 'en vez de ampliar a las 5 fuentes.'
        );
    }
}
