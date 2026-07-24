<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionPaginadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas del PAGINADOR de regulaciones.
 *
 * ── Qué hace y por qué es delicado ──
 *
 * Guarda, para cada artículo, en qué página del PDF original aparece. Con ese dato
 * el buscador puede abrir el documento oficial justo donde está el texto que
 * respondió la búsqueda, en vez de en la primera hoja.
 *
 * Ubicar un artículo dentro de un PDF parece trivial y no lo es. Costó varias
 * vueltas encontrar una regla que aguantara los documentos reales:
 *
 *   - Buscar por el TEXTO del artículo falla: las leyes de Hacienda repiten
 *     frases-plantilla ("causarán el equivalente a una vez el valor de la UMA"),
 *     así que el cuerpo de un artículo aparece dentro de otro.
 *   - Buscar "Artículo N" en cualquier posición también falla: aparece en las
 *     CITAS que otros artículos hacen ("...conforme al artículo 5...").
 *
 * Lo que sí funciona es exigir que "Artículo N" ABRA UN RENGLÓN, que es lo que
 * ocurre donde el artículo realmente empieza. Estas pruebas fijan esa regla con un
 * PDF de verdad: se genera al vuelo, se pasa por el paginador y se comprueba
 * página por página.
 */
class RegulacionPaginadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // pdftotext vive en la imagen, pero si faltara la prueba no tendría sentido.
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('pdftotext no está disponible en este entorno.');
        }

        Storage::fake('local');
    }

    /**
     * Crea una regulación con un PDF real de varias páginas.
     *
     * @param  array<int, string>  $paginas  HTML de cada página, en orden
     */
    private function regulacionConPdf(array $paginas): Regulacion
    {
        $cuerpo = collect($paginas)
            ->map(fn ($html) => '<div style="page-break-after: always">' . $html . '</div>')
            ->implode('');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<html><body style="font-family: DejaVu Sans, sans-serif; font-size: 12px">'
            . $cuerpo . '</body></html>'
        )->output();

        $ruta = 'regulaciones/originales/prueba.pdf';
        Storage::disk('local')->put($ruta, $pdf);

        return Regulacion::factory()->create([
            'archivo_original'   => $ruta,
            'extension_original' => 'pdf',
        ]);
    }

    private function articulo(Regulacion $r, string $numero, string $texto, int $orden): RegulacionNodo
    {
        return RegulacionNodo::create([
            'regulacion_id' => $r->id,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => $numero,
            'texto'         => $texto,
            'orden'         => $orden,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Caso base
    // ─────────────────────────────────────────────────────────────

    public function test_ubica_cada_articulo_en_su_pagina(): void
    {
        $r = $this->regulacionConPdf([
            '<p>Artículo 1.- Disposiciones generales del ordenamiento.</p>',
            '<p>Artículo 2.- De los sujetos obligados al pago.</p>',
            '<p>Artículo 3.- De las exenciones aplicables.</p>',
        ]);

        $a1 = $this->articulo($r, '1', 'Disposiciones generales del ordenamiento.', 1);
        $a2 = $this->articulo($r, '2', 'De los sujetos obligados al pago.', 2);
        $a3 = $this->articulo($r, '3', 'De las exenciones aplicables.', 3);

        $exactas = app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(3, $exactas);
        $this->assertSame(1, $a1->fresh()->pagina);
        $this->assertSame(2, $a2->fresh()->pagina);
        $this->assertSame(3, $a3->fresh()->pagina);
    }

    /**
     * Varios artículos cortos caben en una misma hoja: los tres deben apuntar a
     * ella, no repartirse por páginas inventadas.
     *
     * Los textos van con el largo de un artículo real. No es adorno: el paginador
     * solo usa el cuerpo como respaldo si tiene al menos doce caracteres útiles,
     * para no emparejar por casualidad con cualquier fragmento suelto.
     */
    public function test_varios_articulos_en_la_misma_pagina_apuntan_a_esa_pagina(): void
    {
        $r = $this->regulacionConPdf([
            '<p>Artículo 1.- La presente ley regula los ingresos que percibirá la hacienda '
            . 'pública municipal durante el ejercicio fiscal correspondiente.</p>'
            . '<p>Artículo 2.- Los sujetos obligados deberán cubrir las contribuciones en las '
            . 'cajas recaudadoras autorizadas por la tesorería municipal.</p>'
            . '<p>Artículo 3.- Las exenciones previstas en este ordenamiento se otorgarán '
            . 'previa solicitud fundada del interesado ante la autoridad competente.</p>',

            '<p>Artículo 4.- El incumplimiento de las obligaciones señaladas dará lugar a las '
            . 'sanciones administrativas que determine el reglamento respectivo.</p>',
        ]);

        $a2 = $this->articulo(
            $r,
            '2',
            'Los sujetos obligados deberán cubrir las contribuciones en las cajas recaudadoras autorizadas por la tesorería municipal.',
            2
        );
        $a4 = $this->articulo(
            $r,
            '4',
            'El incumplimiento de las obligaciones señaladas dará lugar a las sanciones administrativas que determine el reglamento respectivo.',
            4
        );

        app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(1, $a2->fresh()->pagina);
        $this->assertSame(2, $a4->fresh()->pagina);
    }

    // ─────────────────────────────────────────────────────────────
    //  Los dos casos que rompían el paginador
    // ─────────────────────────────────────────────────────────────

    /**
     * REGRESIÓN: una CITA a un artículo no debe atraerlo. Aquí el artículo 9 se
     * menciona en la página 1, dentro de otro artículo, pero empieza en la 2.
     */
    public function test_una_cita_no_se_confunde_con_el_articulo(): void
    {
        $r = $this->regulacionConPdf([
            '<p>Artículo 1.- El infractor pagará conforme al artículo 9 de esta ley.</p>',
            '<p>Artículo 9.- De las sanciones y su cobro.</p>',
        ]);

        $a9 = $this->articulo($r, '9', 'De las sanciones y su cobro.', 9);

        app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(
            2,
            $a9->fresh()->pagina,
            'La mención dentro del artículo 1 no debería atraer al artículo 9.'
        );
    }

    /**
     * REGRESIÓN: texto-plantilla repetido. El cuerpo del artículo 7 aparece casi
     * igual en el 3; si se buscara por texto, el 7 caería en la página del 3.
     */
    public function test_el_texto_repetido_entre_articulos_no_desvia_la_pagina(): void
    {
        $frase = 'Causarán el equivalente a una vez el valor de la Unidad de Medida.';

        $r = $this->regulacionConPdf([
            '<p>Artículo 3.- ' . $frase . '</p>',
            '<p>Artículo 7.- ' . $frase . '</p>',
        ]);

        $a7 = $this->articulo($r, '7', $frase, 7);

        app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(2, $a7->fresh()->pagina);
    }

    // ─────────────────────────────────────────────────────────────
    //  Herencia y casos sin PDF
    // ─────────────────────────────────────────────────────────────

    /**
     * Un resultado del buscador suele ser una fracción, no el artículo entero. La
     * fracción hereda la página de su artículo; si no, abriría el PDF en la hoja 1.
     */
    public function test_las_fracciones_heredan_la_pagina_de_su_articulo(): void
    {
        $r = $this->regulacionConPdf([
            '<p>Artículo 1.- Primero.</p>',
            '<p>Artículo 2.- De los requisitos siguientes:</p>',
        ]);

        $art = $this->articulo($r, '2', 'De los requisitos siguientes:', 2);
        $fraccion = RegulacionNodo::create([
            'regulacion_id' => $r->id,
            'parent_id'     => $art->id,
            'tipo'          => RegulacionNodo::TIPO_FRACCION,
            'numero'        => 'I',
            'texto'         => 'Presentar identificación oficial.',
            'orden'         => 1,
        ]);

        app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(2, $art->fresh()->pagina);
        $this->assertSame(2, $fraccion->fresh()->pagina, 'La fracción debería heredar la página de su artículo.');
    }

    public function test_una_regulacion_sin_pdf_no_revienta(): void
    {
        $r = Regulacion::factory()->create([
            'archivo_original'   => null,
            'extension_original' => null,
        ]);
        $this->articulo($r, '1', 'Sin PDF que consultar.', 1);

        $exactas = app(RegulacionPaginadorService::class)->detectarPaginas($r);

        $this->assertSame(0, $exactas, 'Sin PDF no hay páginas que detectar, pero tampoco error.');
    }

    public function test_una_regulacion_de_word_se_omite(): void
    {
        $r = Regulacion::factory()->create([
            'archivo_original'   => 'regulaciones/originales/algo.docx',
            'extension_original' => 'docx',
        ]);

        $this->assertSame(0, app(RegulacionPaginadorService::class)->detectarPaginas($r));
    }
}
