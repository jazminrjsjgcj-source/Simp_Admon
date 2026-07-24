<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\AsistenteRespuestaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Red de regresión para la inyección de catálogos (inyectarContextoPermanente).
 *
 * ── Por qué existe esta prueba ───────────────────────────────────────
 *
 * Esta inyección estuvo MUERTA (un `return` mal puesto la dejaba como código
 * inalcanzable) y ninguna de las 345 pruebas lo notó, porque ninguna la
 * ejercitaba. Esta prueba cierra ese hueco: si alguien vuelve a romper la
 * inyección, aquí se pone en rojo.
 *
 * ── Qué comprueba, con el caso insignia ──────────────────────────────
 *
 * El buscador encuentra la CONDUCTA (art. 65: "obstáculos en banquetas") pero no
 * el CATÁLOGO (art. 105: la tabla de clases), porque el catálogo no menciona
 * "banqueta". Sin el catálogo, el asistente no puede calcular la multa y rellena
 * el hueco con una cita falsa. inyectarContextoPermanente añade el catálogo
 * aunque el buscador no lo trajera.
 *
 * Se verifica en el punto exacto que importa: el prompt REALMENTE enviado al
 * modelo. Y se mira SOLO el mensaje del usuario (la lista de fuentes), no las
 * instrucciones del sistema —que traen ejemplos de formato como "[1], [2]" y
 * darían falsos positivos al contar fuentes.
 */
class AsistenteInyectaCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El asistente solo actúa si está encendido y con API key. Se configura para
        // la prueba; la llamada real se intercepta con Http::fake, no sale a internet.
        config([
            'punta.asistente.activo'  => true,
            'punta.asistente.api_key' => 'test-key',
            'punta.asistente.url'     => 'https://fake.test/v1/chat',
        ]);
    }

    /** Respuesta fija del modelo, para que preguntarAlModelo no falle. */
    private function fingirModelo(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'suficiente' => true,
                        'respuesta'  => 'Respuesta de prueba.',
                        'fuentes'    => [1],
                    ])],
                ]],
            ], 200),
        ]);
    }

    /** El contenido del mensaje 'user' que se envió al modelo (la lista de fuentes). */
    private function promptUsuario($request): string
    {
        $mensajes = $request->data()['messages'] ?? [];

        return collect($mensajes)->firstWhere('role', 'user')['content'] ?? '';
    }

    /** Crea un artículo de una ley. Si $tipoReferencia no es null, es un artículo-catálogo. */
    private function articulo(Regulacion $ley, string $numero, string $texto, ?string $tipoReferencia = null): RegulacionNodo
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
            // Asignación directa + save: no depende de que tipo_referencia esté en fillable.
            $nodo->tipo_referencia = $tipoReferencia;
            $nodo->save();
        }

        return $nodo;
    }

    /** El resultado que el buscador le pasa al asistente para un artículo dado. */
    private function resultadoDe(Regulacion $ley, RegulacionNodo $nodo, string $titulo): array
    {
        return [
            'tipo'      => 'articulo',
            'icono'     => 'ti-book-2',
            'titulo'    => $titulo,
            'subtitulo' => $ley->nombre,
            'fragmento' => $nodo->texto,
            'score'     => 1.0,
            'url'       => '/x',
            'meta'      => [
                'regulacion_id' => $ley->id,
                'nodo_id'       => $nodo->id,
                'tipo_nodo'     => 'articulo',
            ],
        ];
    }

    public function test_el_catalogo_de_una_regulacion_llega_al_modelo(): void
    {
        $this->fingirModelo();

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);

        // La conducta: lo que el buscador SÍ encuentra. Sin tipo_referencia.
        $conducta = $this->articulo($ley, '65', 'Se prohíbe poner obstáculos en las banquetas sin permiso.');

        // El catálogo: lo que el buscador NO encuentra, pero debe inyectarse. Lleva un
        // marcador distintivo para reconocerlo en el prompt.
        $this->articulo($ley, '105', 'MARCADOR_CATALOGO: el artículo 65 corresponde a la Clase D.', 'catalogo');

        // Al asistente se le pasa SOLO la conducta.
        $resultados = collect([$this->resultadoDe($ley, $conducta, 'Artículo 65')]);

        app(AsistenteRespuestaService::class)->construir('multa por obstruir la banqueta', $resultados);

        // El catálogo (art. 105) no venía en los resultados; debe aparecer en el mensaje
        // del usuario que realmente se envió al modelo. Si no está, la inyección murió.
        Http::assertSent(fn ($request) => str_contains($this->promptUsuario($request), 'MARCADOR_CATALOGO'));
    }

    public function test_sin_catalogo_no_se_inyecta_nada_de_mas(): void
    {
        // Contraparte: una ley sin artículos-catálogo no debe hacer que se inyecte nada.
        $this->fingirModelo();

        $ley = Regulacion::factory()->create(['nombre' => 'Reglamento sin catálogo']);
        $unico = $this->articulo($ley, '1', 'Un artículo normal, sin tabla de clases.');

        $resultados = collect([$this->resultadoDe($ley, $unico, 'Artículo 1')]);

        app(AsistenteRespuestaService::class)->construir('cualquier pregunta', $resultados);

        // El modelo recibe exactamente UNA fuente en el mensaje del usuario: la que trajo
        // el buscador, sin añadidos. Hay cabecera "[1]" y ninguna "[2]".
        Http::assertSent(function ($request) {
            $prompt = $this->promptUsuario($request);

            return str_contains($prompt, '[1]') && ! str_contains($prompt, '[2]');
        });
    }

    public function test_la_escala_inyectada_lleva_sus_cuantias_de_los_hijos(): void
    {
        // El caso de la banqueta: la escala (art. 104) tiene su encabezado en el texto
        // propio, pero las CUANTÍAS ("Clase D: 31 a 100 UMA") están en los incisos hijos.
        // La inyección debe llevar los hijos, o el asistente ve la clase sin el monto.
        $this->fingirModelo();

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);

        // La conducta que trae el buscador.
        $conducta = $this->articulo($ley, '65', 'Poner obstáculos en las banquetas sin permiso.');

        // La escala, marcada, con su cuantía en un inciso hijo.
        $escala = $this->articulo($ley, '104', 'Las infracciones se clasifican de la siguiente manera:', 'escala_sancion');
        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => $escala->id,
            'tipo'          => RegulacionNodo::TIPO_INCISO,
            'numero'        => 'd',
            'texto'         => 'Infracciones Clase D: Multa de 31 a 100 UMA o arresto de 30 a 36 horas.',
            'orden'         => 4,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        // Al asistente se le pasa SOLO la conducta; la escala la añade la inyección.
        $resultados = collect([$this->resultadoDe($ley, $conducta, 'Artículo 65')]);

        app(AsistenteRespuestaService::class)->construir('multa por obstruir la banqueta', $resultados);

        // La cuantía, que vive en el inciso hijo del 104, debe llegar al prompt del modelo.
        Http::assertSent(fn ($request) => str_contains($this->promptUsuario($request), '31 a 100 UMA'));
    }
}
