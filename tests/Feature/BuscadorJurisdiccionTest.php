<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba la jurisdicción en el buscador: el filtro (Fase 2a) y el motor del
 * "ver todo" (Fase 2b) — el parámetro que salta el filtro y el marcador
 * fuera_de_jurisdiccion que rotula lo que entra.
 *
 * (Reemplaza la versión de 2a: mismas pruebas del filtro, más las del motor.)
 *
 * La instalación de pruebas está configurada como La Paz, BCS (config
 * 'punta.jurisdiccion'). Se busca con forzarCompleto para que corran las cinco
 * fuentes y no el atajo "enfocado".
 */
class BuscadorJurisdiccionTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    private function ley(string $nombre, ?string $ambito, ?string $estado = null, ?string $municipio = null): Regulacion
    {
        return Regulacion::factory()->create([
            'nombre'    => $nombre,
            'ambito'    => $ambito,
            'estado'    => $estado,
            'municipio' => $municipio,
        ]);
    }

    private function articuloDePredial(Regulacion $ley): void
    {
        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '1',
            'texto'         => 'El impuesto predial se calcula sobre el valor catastral del inmueble.',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    /** La colección de resultados de una búsqueda (con o sin "ver todo"). */
    private function resultados(string $consulta, bool $verTodo = false)
    {
        return $this->buscador
            ->buscar($consulta, forzarCompleto: true, incluirOtrasJurisdicciones: $verTodo)['resultados'];
    }

    /** IDs de regulación presentes en los resultados (sin nulos). */
    private function regulacionesEn($resultados): array
    {
        return $resultados->pluck('meta.regulacion_id')->filter()->unique()->all();
    }

    // ── El filtro (Fase 2a): apagado el "ver todo", solo entra lo de aquí ──────

    public function test_no_trae_leyes_de_otra_jurisdiccion(): void
    {
        $lapaz = $this->ley('Ley de Hacienda para el Municipio de La Paz', 'municipal', 'BCS', 'La Paz');
        $sonora = $this->ley('Ley de Hacienda del Estado de Sonora', 'estatal', 'Sonora');

        $this->articuloDePredial($lapaz);
        $this->articuloDePredial($sonora);

        $ids = $this->regulacionesEn($this->resultados('predial'));

        $this->assertContains($lapaz->id, $ids, 'La ley de La Paz, que sí aplica, debe aparecer.');
        $this->assertNotContains($sonora->id, $ids, 'La ley de Sonora es de otro estado: no debe llegar al ciudadano.');
    }

    public function test_una_ley_sin_clasificar_queda_excluida_y_aparece_marcada_con_ver_todo(): void
    {
        // Fase 3: NULL ya no se incluye. Una ley sin ámbito no está confirmada como de
        // esta jurisdicción, así que el filtro la deja fuera. Con el default del modelo
        // ninguna ley nace NULL, así que aquí se fuerza el null (update directo) para
        // simular una ley mal cargada.
        $sinClasificar = $this->ley('Reglamento de Predial sin clasificar', 'municipal', 'BCS', 'La Paz');
        DB::table('regulaciones')->where('id', $sinClasificar->id)->update([
            'ambito' => null, 'estado' => null, 'municipio' => null,
        ]);
        $this->articuloDePredial($sinClasificar);

        // Por defecto: excluida.
        $idsNormal = $this->regulacionesEn($this->resultados('predial'));
        $this->assertNotContains($sinClasificar->id, $idsNormal, 'Una ley sin ámbito debe quedar excluida por defecto.');

        // Con "ver todo": aparece, pero marcada como fuera de jurisdicción (por verificar).
        $conTodo = $this->resultados('predial', verTodo: true);
        $res = $conTodo->firstWhere('meta.regulacion_id', $sinClasificar->id);
        $this->assertNotNull($res, 'Con "ver todo" debe aparecer.');
        $this->assertTrue($res['fuera_de_jurisdiccion'], 'Sin ámbito confirmado, debe venir marcada.');
    }

    public function test_incluye_leyes_federales(): void
    {
        $federal = $this->ley('Ley Federal del Predial Hipotético', 'federal');
        $this->articuloDePredial($federal);

        $ids = $this->regulacionesEn($this->resultados('predial'));

        $this->assertContains($federal->id, $ids, 'Una ley federal debe incluirse siempre.');
    }

    public function test_no_trae_la_ficha_de_una_ley_de_otro_estado(): void
    {
        $lapaz = $this->ley('Reglamento de Comercio del Municipio de La Paz', 'municipal', 'BCS', 'La Paz');
        $sonora = $this->ley('Reglamento de Comercio del Estado de Sonora', 'estatal', 'Sonora');

        $ids = $this->regulacionesEn($this->resultados('comercio'));

        $this->assertContains($lapaz->id, $ids, 'La ficha de la ley de La Paz debe aparecer.');
        $this->assertNotContains($sonora->id, $ids, 'La ficha de la ley de Sonora no debe llegar al ciudadano.');
    }

    // ── El motor del "ver todo" (Fase 2b) ─────────────────────────────────────

    public function test_ver_todo_trae_leyes_de_otro_estado_pero_marcadas(): void
    {
        $lapaz = $this->ley('Ley de Hacienda para el Municipio de La Paz', 'municipal', 'BCS', 'La Paz');
        $sonora = $this->ley('Ley de Hacienda del Estado de Sonora', 'estatal', 'Sonora');

        $this->articuloDePredial($lapaz);
        $this->articuloDePredial($sonora);

        $resultados = $this->resultados('predial', verTodo: true);

        // Con "ver todo", la de Sonora SÍ aparece —esa es la diferencia con el filtro—
        // pero viene marcada como fuera de jurisdicción. La red de seguridad no es
        // esconderla: es mostrarla diciendo de dónde es.
        $sonoraRes = $resultados->firstWhere('meta.regulacion_id', $sonora->id);
        $this->assertNotNull($sonoraRes, 'Con "ver todo", la ley de Sonora debe aparecer.');
        $this->assertTrue(
            $sonoraRes['fuera_de_jurisdiccion'],
            'La ley de Sonora debe venir marcada como fuera de jurisdicción.'
        );

        // Y la local NO se marca: es de aquí.
        $lapazRes = $resultados->firstWhere('meta.regulacion_id', $lapaz->id);
        $this->assertNotNull($lapazRes, 'La ley de La Paz debe seguir apareciendo.');
        $this->assertFalse(
            $lapazRes['fuera_de_jurisdiccion'],
            'La ley de La Paz no debe marcarse: es de esta jurisdicción.'
        );
    }

    public function test_sin_ver_todo_lo_que_entra_nunca_viene_marcado(): void
    {
        // Con el filtro puesto (modo normal), lo único que entra es de aquí, así que
        // ningún resultado debe llevar la marca. Es coherencia: si algo está marcado
        // fuera de jurisdicción, es que el filtro lo dejó pasar, y eso no debe ocurrir
        // sin "ver todo".
        $lapaz = $this->ley('Ley de Hacienda para el Municipio de La Paz', 'municipal', 'BCS', 'La Paz');
        $sonora = $this->ley('Ley de Hacienda del Estado de Sonora', 'estatal', 'Sonora');

        $this->articuloDePredial($lapaz);
        $this->articuloDePredial($sonora);

        $resultados = $this->resultados('predial'); // sin ver todo

        $marcados = $resultados->where('fuera_de_jurisdiccion', true);
        $this->assertTrue($marcados->isEmpty(), 'En modo normal, ningún resultado debe venir marcado fuera de jurisdicción.');
    }
}
