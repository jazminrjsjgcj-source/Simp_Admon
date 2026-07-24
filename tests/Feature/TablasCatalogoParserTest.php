<?php

namespace Tests\Feature;

use App\Services\RegulacionConversorService;
use Tests\TestCase;

/**
 * Prueba el parser de las tablas-catálogo (RegulacionConversorService::
 * parsearTablasCatalogo). Es la parte con lógica de la extracción: convierte el
 * JSON que imprime el script Python en una lista limpia de pares artículo→clase.
 *
 * La ejecución del script en sí (exec de python3) es plomería y se prueba con la
 * corrida real en el contenedor, igual que el exec de pdftotext.
 *
 * El contrato del script: SIEMPRE imprime JSON, incluso al fallar
 * ({"ok": false, "tablas": []}). El parser tiene que sobrevivir a todos esos
 * casos y, ante cualquier duda, devolver una lista vacía —la extracción es una
 * mejora, nunca un requisito—.
 */
class TablasCatalogoParserTest extends TestCase
{
    private RegulacionConversorService $conversor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversor = app(RegulacionConversorService::class);
    }

    public function test_aplana_los_pares_de_todas_las_tablas(): void
    {
        $json = json_encode([
            'ok'     => true,
            'tablas' => [
                ['filas' => [['65', 'D'], ['66', 'C']]],
                ['filas' => [['89', 'A']]],
            ],
        ]);

        $pares = $this->conversor->parsearTablasCatalogo($json);

        $this->assertSame([
            ['articulo' => '65', 'clase' => 'D'],
            ['articulo' => '66', 'clase' => 'C'],
            ['articulo' => '89', 'clase' => 'A'],
        ], $pares);
    }

    public function test_el_par_insignia_65_a_clase_d_se_conserva(): void
    {
        $json = json_encode(['ok' => true, 'tablas' => [['filas' => [['65', 'D']]]]]);

        $pares = $this->conversor->parsearTablasCatalogo($json);

        $this->assertContains(['articulo' => '65', 'clase' => 'D'], $pares);
    }

    public function test_descarta_filas_cuyo_articulo_no_es_numero(): void
    {
        // Ruido residual conocido del script: un nombre de sección arrastrado como
        // "['LA BUENA', 'A']". El artículo no es un número → se descarta.
        $json = json_encode([
            'ok'     => true,
            'tablas' => [['filas' => [['65', 'D'], ['LA BUENA', 'A'], ['', 'B']]]],
        ]);

        $pares = $this->conversor->parsearTablasCatalogo($json);

        $this->assertSame([['articulo' => '65', 'clase' => 'D']], $pares);
    }

    public function test_devuelve_vacio_si_el_script_reporto_error(): void
    {
        // El script no pudo (pdfplumber ausente, PDF corrupto...). Informa ok:false.
        $json = json_encode(['ok' => false, 'error' => 'pdfplumber no está instalado', 'tablas' => []]);

        $this->assertSame([], $this->conversor->parsearTablasCatalogo($json));
    }

    public function test_devuelve_vacio_ante_json_ilegible(): void
    {
        // Si por lo que sea llega algo que no es JSON, no revienta: lista vacía.
        $this->assertSame([], $this->conversor->parsearTablasCatalogo('esto no es json'));
        $this->assertSame([], $this->conversor->parsearTablasCatalogo(''));
    }

    public function test_devuelve_vacio_si_no_hay_tablas(): void
    {
        $json = json_encode(['ok' => true, 'tablas' => []]);

        $this->assertSame([], $this->conversor->parsearTablasCatalogo($json));
    }
}
