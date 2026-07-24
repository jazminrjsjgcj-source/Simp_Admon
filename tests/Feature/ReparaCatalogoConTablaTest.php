<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\DetectorCatalogosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba la reparación del nodo-catálogo con la tabla limpia
 * (DetectorCatalogosService::repararCatalogoConTabla) — el cierre del cruce (T2).
 *
 * El nodo del catálogo (Bando art. 105) tiene en la base el texto que dejó
 * pdftotext: la tabla aplastada e ilegible. El script Python recupera los pares
 * limpios; este método los vuelca, legibles, en ese nodo, para que la inyección
 * —que ya lo arrastra— entregue al asistente "Artículo 65 → Clase D".
 *
 * La extracción real (Python) es plomería y se prueba en el contenedor; aquí se
 * prueba la LÓGICA: que la tabla se escriba en el nodo correcto y solo en ese.
 */
class ReparaCatalogoConTablaTest extends TestCase
{
    use RefreshDatabase;

    private DetectorCatalogosService $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = app(DetectorCatalogosService::class);
    }

    private function nodo(Regulacion $ley, string $numero, string $texto, ?string $tipoReferencia): RegulacionNodo
    {
        $nodo = RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => $numero,
            'texto'         => $texto,
            'orden'         => (int) $numero,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        if ($tipoReferencia !== null) {
            $nodo->tipo_referencia = $tipoReferencia;
            $nodo->save();
        }

        return $nodo;
    }

    /** Los pares que el script Python habría recuperado. */
    private function pares(): array
    {
        return [
            ['articulo' => '65', 'clase' => 'D'],
            ['articulo' => '66', 'clase' => 'C'],
        ];
    }

    public function test_vuelca_la_tabla_limpia_en_el_nodo_catalogo(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);

        // El catálogo, con el texto aplastado por pdftotext.
        $catalogo = $this->nodo($ley, '105', 'Para efectos del artículo anterior 65 D 66 C',
            'catalogo_clasificacion');

        $reparados = $this->detector->repararCatalogoConTabla($ley, $this->pares());

        $this->assertSame(1, $reparados);

        $texto = $catalogo->fresh()->texto;
        $this->assertStringContainsString('Artículo 65 → Clase D', $texto, 'El cruce insignia debe quedar legible en el nodo.');
        $this->assertStringContainsString('Artículo 66 → Clase C', $texto);
        // Sin perder el texto original.
        $this->assertStringContainsString('Para efectos del artículo anterior', $texto);
    }

    public function test_no_toca_un_nodo_que_no_es_el_catalogo(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);

        // La escala (art. 104) y un artículo normal NO son el catálogo de clasificación.
        $escala = $this->nodo($ley, '104', 'Clase A: 1 a 10 UMA...', 'escala_sancion');
        $normal = $this->nodo($ley, '10', 'Un artículo cualquiera.', null);

        $this->detector->repararCatalogoConTabla($ley, $this->pares());

        $this->assertStringNotContainsString('Clase D', $escala->fresh()->texto);
        $this->assertSame('Un artículo cualquiera.', $normal->fresh()->texto);
    }

    public function test_re_ejecutar_actualiza_la_tabla_sin_duplicar(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $catalogo = $this->nodo($ley, '105', 'Texto crudo del catálogo.', 'catalogo_clasificacion');

        // Primera pasada con una tabla.
        $this->detector->repararCatalogoConTabla($ley, [['articulo' => '65', 'clase' => 'D']]);

        // Segunda pasada con la tabla CORREGIDA (p. ej. tras pulir el extractor): reemplaza.
        $reparados = $this->detector->repararCatalogoConTabla($ley, [
            ['articulo' => '65', 'clase' => 'D'],
            ['articulo' => '66', 'clase' => 'C'],
        ]);

        $this->assertSame(1, $reparados, 'Re-ejecutar debe volver a reparar (actualizar).');

        $texto = $catalogo->fresh()->texto;
        $this->assertSame(1, substr_count($texto, 'Clasificación recuperada de la tabla:'), 'No debe duplicar el bloque.');
        $this->assertSame(1, substr_count($texto, 'Artículo 65 → Clase D'), 'No debe duplicar la línea.');
        $this->assertStringContainsString('Artículo 66 → Clase C', $texto, 'La tabla nueva refleja los pares corregidos.');
        $this->assertStringContainsString('Texto crudo del catálogo.', $texto, 'El texto original se conserva.');
    }

    public function test_no_hace_nada_sin_pares_ni_sin_nodo_catalogo(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $this->nodo($ley, '105', 'Texto.', 'catalogo_clasificacion');

        $this->assertSame(0, $this->detector->repararCatalogoConTabla($ley, []), 'Sin pares, nada que hacer.');

        $otraLey = Regulacion::factory()->create(['nombre' => 'Ley sin catálogo']);
        $this->assertSame(0, $this->detector->repararCatalogoConTabla($otraLey, $this->pares()), 'Sin nodo-catálogo, nada que reparar.');
    }
}
