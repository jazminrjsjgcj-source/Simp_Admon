<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\AsistenteRespuestaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prueba el aviso de jurisdicción en el asistente (Fase 2b): que si la respuesta
 * se apoya en una fuente de otra jurisdicción, salga rotulada.
 *
 * ── Las dos capas ────────────────────────────────────────────────────
 *
 * · La red DURA (armarRespuesta): por código, antepone el aviso al texto. No
 *   depende de que el modelo obedezca. Es la garantía, y es lo que más se prueba
 *   aquí.
 * · El prompt (pregunta): marca la fuente con "[FUERA DE JURISDICCIÓN]" para que
 *   el modelo lo sepa. Es la capa blanda.
 *
 * El asistente NO decide qué es de otra jurisdicción: lee la marca
 * `fuera_de_jurisdiccion` que el buscador ya puso en el resultado. Por eso aquí
 * se fabrica el resultado con esa marca puesta, sin pasar por el buscador.
 */
class AsistenteAvisoJurisdiccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush(); // la respuesta se cachea; que un test no herede la de otro.

        config([
            'punta.asistente.activo'  => true,
            'punta.asistente.api_key' => 'test-key',
            'punta.asistente.url'     => 'https://fake.test/v1/chat',
        ]);
    }

    /** El modelo responde citando las fuentes dadas. */
    private function fingirModelo(array $fuentesCitadas): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'suficiente' => true,
                        'respuesta'  => 'Se pagan 3 UMA.',
                        'fuentes'    => $fuentesCitadas,
                    ])],
                ]],
            ], 200),
        ]);
    }

    /** Un resultado del buscador (un artículo), con o sin la marca de otra jurisdicción. */
    private function resultado(bool $fueraDeJurisdiccion): array
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Prueba']);
        $nodo = RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '10',
            'texto'         => 'La tarifa aplicable es de 3 UMA.',
            'orden'         => 10,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        return [
            'tipo'      => 'articulo',
            'titulo'    => 'Artículo 10',
            'subtitulo' => $ley->nombre,
            'fragmento' => $nodo->texto,
            'score'     => 1.0,
            'url'       => '/x',
            'fuera_de_jurisdiccion' => $fueraDeJurisdiccion,
            'meta'      => [
                'regulacion_id' => $ley->id,
                'nodo_id'       => $nodo->id,
                'tipo_nodo'     => 'articulo',
            ],
        ];
    }

    public function test_la_red_dura_antepone_el_aviso_al_usar_una_fuente_de_otra_jurisdiccion(): void
    {
        $this->fingirModelo(fuentesCitadas: [1]);

        $resultados = collect([$this->resultado(fueraDeJurisdiccion: true)]);

        $respuesta = app(AsistenteRespuestaService::class)->construir('cuánto pago', $resultados);

        $this->assertNotNull($respuesta, 'El asistente debió producir una respuesta.');
        $this->assertTrue($respuesta['fuera_de_jurisdiccion'], 'La respuesta debe quedar marcada.');
        $this->assertStringStartsWith('Aviso:', $respuesta['definicion'], 'El aviso debe ir por delante del texto.');
        $this->assertStringContainsString('otra jurisdicción', $respuesta['definicion']);
    }

    public function test_no_hay_aviso_si_la_fuente_usada_es_local(): void
    {
        $this->fingirModelo(fuentesCitadas: [1]);

        $resultados = collect([$this->resultado(fueraDeJurisdiccion: false)]);

        $respuesta = app(AsistenteRespuestaService::class)->construir('cuánto pago', $resultados);

        $this->assertNotNull($respuesta);
        $this->assertFalse($respuesta['fuera_de_jurisdiccion'], 'Una fuente local no debe marcar la respuesta.');
        $this->assertStringNotContainsString('otra jurisdicción', $respuesta['definicion']);
    }

    public function test_el_prompt_marca_las_fuentes_de_otra_jurisdiccion(): void
    {
        // La capa blanda: el modelo debe VER la marca en la fuente, en el mensaje del usuario.
        $this->fingirModelo(fuentesCitadas: [1]);

        $resultados = collect([$this->resultado(fueraDeJurisdiccion: true)]);

        app(AsistenteRespuestaService::class)->construir('cuánto pago', $resultados);

        Http::assertSent(function ($request) {
            $mensajes = $request->data()['messages'] ?? [];
            $userMsg = collect($mensajes)->firstWhere('role', 'user')['content'] ?? '';

            return str_contains($userMsg, '[FUERA DE JURISDICCIÓN]');
        });
    }
}
