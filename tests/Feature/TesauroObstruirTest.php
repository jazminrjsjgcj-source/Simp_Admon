<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba el eslabón de vocabulario del caso insignia: que "obstruir" alcance el
 * artículo que dice "obstáculos", gracias a la entrada del tesauro.
 *
 * El artículo 65 del Bando dice "Poner OBSTÁCULOS en las calles, banquetas…". El
 * ciudadano dice el verbo, "obstruir". El buscador busca por prefijo (obstruir:*),
 * que nunca casa "obstáculos" —raíces distintas, ningún stemmer las une—. La
 * entrada obstruir → obstaculo del tesauro es la que salva ese salto.
 *
 * ("banqueta" no necesita tesauro: el art. 65 dice "banquetas" y banqueta:* casa.)
 *
 * La entrada la siembra la migración add_obstruir_al_tesauro, que RefreshDatabase
 * corre; por eso los tests ya la encuentran.
 */
class TesauroObstruirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // el tesauro se cachea una hora; que un test no herede caché de otro.
    }

    /** Crea el artículo 65 con su texto real (dice "obstáculos", no "obstruir"). */
    private function articulo65(): Regulacion
    {
        $ley = Regulacion::factory()->create([
            'nombre'    => 'Bando de Prueba',
            'ambito'    => 'municipal',
            'estado'    => 'BCS',
            'municipio' => 'La Paz',
        ]);

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '65',
            'texto'         => 'Poner obstáculos en las calles, banquetas, caminos o vías de comunicación.',
            'orden'         => 65,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        return $ley;
    }

    private function regulacionesEncontradas(string $consulta): array
    {
        $resultado = app(BuscadorService::class)->buscar($consulta, forzarCompleto: true);

        return $resultado['resultados']->pluck('meta.regulacion_id')->filter()->unique()->all();
    }

    public function test_obstruir_alcanza_el_articulo_que_dice_obstaculos(): void
    {
        $ley = $this->articulo65();

        $ids = $this->regulacionesEncontradas('obstruir');

        $this->assertContains(
            $ley->id,
            $ids,
            'Con la entrada obstruir→obstaculo, buscar "obstruir" debe alcanzar el artículo que dice "obstáculos".'
        );
    }

    public function test_sin_la_entrada_del_tesauro_obstruir_no_alcanza_obstaculos(): void
    {
        // Control: se quita la entrada y se comprueba que, sin ella, "obstruir" NO llega.
        // Esto prueba que la entrada es exactamente lo que cierra el caso —no otra cosa—.
        $ley = $this->articulo65();

        DB::table('busqueda_tesauro')->where('termino_ciudadano', 'obstruir')->delete();
        Cache::forget('tesauro:completo');

        $ids = $this->regulacionesEncontradas('obstruir');

        $this->assertNotContains(
            $ley->id,
            $ids,
            'Sin la entrada, "obstruir" (raíz obstru-) no casa "obstáculos" (raíz obstacul-).'
        );
    }
}
