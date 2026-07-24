<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\DetectorCatalogosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Red de regresión del detector de catálogos (DetectorCatalogosService).
 *
 * El detector le pregunta a la IA, una vez al cargar una ley, qué artículos son
 * "de referencia" (escalas, catálogos, definiciones) y los marca con
 * `tipo_referencia`. Ese marcado es lo que luego permite que la inyección de
 * catálogos acompañe cada respuesta. Si el detector se rompe, el caso insignia
 * de la banqueta vuelve a fallar aguas abajo, sin ruido.
 *
 * Estas pruebas fingen la IA con Http::fake (no salen a internet) y cubren:
 *   · que la respuesta de la IA se persiste en tipo_referencia,
 *   · que el texto que se le manda a la IA incluye los HIJOS del artículo (la
 *     corrección clave: un catálogo esconde su sustancia en los incisos),
 *   · que un artículo normal no se marca,
 *   · y el candado: un tipo fuera de la lista cerrada se descarta.
 */
class DetectorCatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'punta.asistente.activo'  => true,
            'punta.asistente.api_key' => 'test-key',
            'punta.asistente.url'     => 'https://fake.test/v1/chat',
        ]);
    }

    /** La IA responde con un tipo dado (o basura), en el formato que el detector espera. */
    private function fingirIA(string $tipo): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['tipo' => $tipo])],
                ]],
            ], 200),
        ]);
    }

    private function articulo(Regulacion $ley, string $numero, string $texto): RegulacionNodo
    {
        return RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'parent_id'     => null,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => $numero,
            'texto'         => $texto,
            'orden'         => (int) $numero,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    private function inciso(RegulacionNodo $padre, string $texto, int $orden): void
    {
        RegulacionNodo::create([
            'regulacion_id' => $padre->regulacion_id,
            'parent_id'     => $padre->id,
            'tipo'          => RegulacionNodo::TIPO_INCISO,
            'numero'        => (string) $orden,
            'texto'         => $texto,
            'orden'         => $orden,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    public function test_marca_un_articulo_catalogo_y_le_manda_los_hijos_a_la_ia(): void
    {
        $this->fingirIA('escala_sancion');

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);

        // Un catálogo: encabezado corto + las clases como incisos hijos. El detector debe
        // clasificarlo mirando el texto CON los hijos.
        $art = $this->articulo($ley, '104',
            'Las infracciones a este Bando se clasifican en las siguientes clases, según su '
            . 'gravedad, para efectos de determinar la multa que corresponde en cada caso:');
        $this->inciso($art, 'Clase A: de 1 a 10 UMA.', 1);
        $this->inciso($art, 'MARCA_HIJO Clase D: de 31 a 100 UMA, la más grave.', 2);

        $marcados = app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame(1, $marcados, 'Debió marcar exactamente un artículo.');
        $this->assertSame('escala_sancion', $art->fresh()->tipo_referencia);

        // La corrección clave: el texto enviado a la IA incluye el de los hijos.
        Http::assertSent(function ($request) {
            $mensajes = $request->data()['messages'] ?? [];
            $userMsg = collect($mensajes)->firstWhere('role', 'user')['content'] ?? '';

            return str_contains($userMsg, 'MARCA_HIJO');
        });
    }

    public function test_no_marca_un_articulo_corto_ni_llama_a_la_ia(): void
    {
        // Un artículo de una sola frase (<200 caracteres, ya con hijos) ni siquiera se manda
        // a la IA: se ahorra la llamada y queda sin marcar.
        $this->fingirIA('escala_sancion'); // aunque la IA diría "sí", no debe llamarse.

        $ley = Regulacion::factory()->create(['nombre' => 'Reglamento de Prueba']);
        $art = $this->articulo($ley, '1', 'Artículo breve y sin sustancia de catálogo.');

        $marcados = app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame(0, $marcados);
        $this->assertNull($art->fresh()->tipo_referencia);
        Http::assertNothingSent();
    }

    public function test_el_candado_descarta_un_tipo_fuera_de_la_lista(): void
    {
        // Si la IA devuelve un tipo que no está en la lista cerrada, se descarta: preferimos no
        // marcar a marcar mal. El artículo queda sin marca.
        $this->fingirIA('categoria_inventada_que_no_existe');

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $art = $this->articulo($ley, '50',
            'Este artículo es suficientemente largo como para superar el umbral de doscientos '
            . 'caracteres y así llegar hasta la llamada a la inteligencia artificial, que en esta '
            . 'prueba devuelve un tipo inválido a propósito para verificar el candado del detector.');

        $marcados = app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame(0, $marcados, 'Un tipo inválido no debe marcar nada.');
        $this->assertNull($art->fresh()->tipo_referencia);
    }

    public function test_no_vuelve_a_preguntar_a_la_ia_por_el_mismo_texto(): void
    {
        // La caché por contenido: clasificar el mismo texto dos veces (como al
        // re-estructurar) debe llamar a la IA UNA sola vez. Es lo que hace la
        // re-detección rápida y determinista.
        $this->fingirIA('escala_sancion');

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $texto = 'Este artículo es un catálogo suficientemente largo como para superar el umbral '
               . 'de doscientos caracteres y llegar a la IA; contiene la tabla de clases que asigna '
               . 'a cada conducta su gravedad, y por tanto debe clasificarse como escala de sanción.';
        $art = $this->articulo($ley, '104', $texto);

        // Dos detecciones seguidas sobre el mismo texto.
        app(DetectorCatalogosService::class)->detectarYMarcar($ley);
        app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame('escala_sancion', $art->fresh()->tipo_referencia);
        Http::assertSentCount(1); // la segunda vez salió de la caché, sin llamar a la IA
    }

    public function test_un_fallo_de_la_ia_no_deja_nada_en_la_cache(): void
    {
        // Si la IA se cae, el artículo NO debe quedar registrado en la caché —ni como
        // catálogo ni como "no catálogo"—, para que la próxima corrida lo reintente en
        // vez de quedarse con un negativo falso.
        //
        // Se comprueba directo sobre la tabla-caché, en UNA sola corrida: así el test no
        // depende de encadenar fallo+éxito (donde el fake de excepción deja estado que
        // ensucia la segunda petición). Lo que importa es que el fallo no cachea.
        Http::fake(fn () => throw new ConnectionException('IA no disponible'));

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $texto = 'Este artículo es un catálogo suficientemente largo como para superar el umbral '
               . 'de doscientos caracteres y llegar a la IA; contiene la tabla de clases que asigna '
               . 'a cada conducta su gravedad, y por tanto debe clasificarse como escala de sanción.';
        $art = $this->articulo($ley, '104', $texto);

        app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertNull($art->fresh()->tipo_referencia, 'Un fallo no debe marcar el artículo.');
        $this->assertSame(
            0,
            DB::table('clasificaciones_ia')->count(),
            'Un fallo no debe dejar entradas en la caché: la próxima corrida debe reintentar.'
        );
    }

    public function test_un_negativo_confirmado_por_la_ia_si_se_cachea(): void
    {
        // La contraparte: cuando la IA SÍ responde (2xx con contenido) que un artículo no
        // es catálogo, eso sí se cachea —como fila con tipo NULL—, para no volver a
        // preguntarlo. Es lo que hace rápida la re-detección de artículos normales.
        $this->fingirIA('ninguna'); // 'ninguna' no está en TIPOS_VALIDOS → negativo

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $texto = 'Este artículo es suficientemente largo como para superar el umbral de doscientos '
               . 'caracteres y llegar a la IA, pero es un artículo normal que la IA no reconoce como '
               . 'un catálogo, de modo que su clasificación negativa debe quedar cacheada para el futuro.';
        $this->articulo($ley, '10', $texto);

        app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame(1, DB::table('clasificaciones_ia')->count(), 'Un negativo confirmado se cachea.');
        $this->assertNull(DB::table('clasificaciones_ia')->value('tipo'), 'Se cachea como tipo NULL (no es catálogo).');
    }

    public function test_reintenta_un_fallo_transitorio_y_acaba_clasificando(): void
    {
        // Un parpadeo transitorio de la IA (un timeout) no debe abandonar el artículo:
        // el reintento con backoff lo recupera dentro de la misma corrida. Sin esto, la
        // detección de una ley grande se degradaba cada vez que la IA tenía un mal momento.
        //
        // Se simula con un contador: la PRIMERA petición lanza una excepción de conexión;
        // la SEGUNDA (el reintento) responde bien. (La prueba del corte confirma que este
        // entorno sí propaga ConnectionException lanzada desde el closure del fake.)
        $intentos = 0;
        Http::fake(function () use (&$intentos) {
            $intentos++;
            if ($intentos === 1) {
                throw new ConnectionException('timeout transitorio');
            }

            return Http::response([
                'choices' => [['message' => ['content' => json_encode(['tipo' => 'escala_sancion'])]]],
            ], 200);
        });

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        $texto = 'Este artículo es un catálogo suficientemente largo como para superar el umbral '
               . 'de doscientos caracteres y llegar a la IA; contiene la tabla de clases que asigna '
               . 'a cada conducta su gravedad, y por tanto debe clasificarse como escala de sanción.';
        $art = $this->articulo($ley, '104', $texto);

        app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame('escala_sancion', $art->fresh()->tipo_referencia, 'El reintento debe recuperar el parpadeo y acabar marcando.');
        $this->assertGreaterThanOrEqual(2, $intentos, 'Debió reintentar tras el primer fallo.');
    }

    public function test_corta_la_deteccion_tras_muchos_fallos_seguidos(): void
    {
        // Si la IA está CAÍDA, el detector no debe recorrer los cientos de artículos
        // fallando uno por uno (fue lo que pasó: una corrida de casi 2 horas que marcó
        // casi nada). Tras una racha de fallos seguidos, corta y avisa. Lo marcado antes
        // del corte queda, y re-ejecutar reanuda desde la caché.
        Http::fake(fn () => throw new ConnectionException('IA caída'));
        Log::spy();

        $ley = Regulacion::factory()->create(['nombre' => 'Bando de Prueba']);
        for ($i = 1; $i <= 8; $i++) {
            $this->articulo($ley, (string) $i,
                'Artículo número ' . $i . ' con texto suficientemente largo como para superar el '
                . 'umbral de doscientos caracteres y llegar hasta la llamada a la IA, que en esta '
                . 'prueba está caída, de modo que todos los intentos fallan uno tras otro sin cesar.');
        }

        $marcados = app(DetectorCatalogosService::class)->detectarYMarcar($ley);

        $this->assertSame(0, $marcados, 'Con la IA caída no se marca nada.');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($mensaje) => is_string($mensaje) && str_contains($mensaje, 'se corta la detección'))
            ->once();
    }
}
