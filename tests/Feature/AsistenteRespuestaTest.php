<?php

namespace Tests\Feature;

use App\Services\AsistenteRespuestaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El asistente del buscador NO PUEDE INVENTAR.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ SE ESTÁ PROTEGIENDO
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta "¿cuánto cuesta mi licencia de funcionamiento?". Si el asistente
 * responde "$1,240 pesos" y esa cifra no está en ninguna regulación —se la inventó porque
 * suena plausible—, esa persona se presenta en ventanilla con el dinero equivocado.
 *
 * Y nadie lo detecta. Porque $1,240 es una cifra perfectamente razonable.
 *
 * Es exactamente el patrón de los trece bugs que este proyecto ya tenía: un número plausible
 * que nadie puede identificar como falso, que no produce ningún error, y que se descubre
 * demasiado tarde.
 *
 * La diferencia es que esos trece los heredamos. Este lo estaríamos AÑADIENDO nosotros.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LAS DOS COSAS QUE SE PRUEBAN
 * ══════════════════════════════════════════════════════════════════════
 *
 * 1. QUE NO INVENTE. Aunque el modelo se empeñe: si no cita fuentes, o cita fuentes que no
 *    existen, o admite que no le basta con lo que tiene → no hay respuesta.
 *
 * 2. QUE SU FALLO NO ROMPA NADA. Si DeepSeek se cae, tarda, o devuelve basura, el ciudadano
 *    tiene que ver su lista de resultados exactamente como la vería hoy. Un buscador
 *    municipal no puede depender de que una API externa esté de buen humor.
 *
 * Se usa Http::fake(): NUNCA se llama a la API de verdad en una prueba. Sería lento, costaría
 * dinero, y —lo peor— la prueba fallaría o pasaría según lo que al modelo le diera por
 * responder ese día. Una prueba que depende de un tercero no es una prueba: es una apuesta.
 */
class AsistenteRespuestaTest extends TestCase
{
    use RefreshDatabase;

    private AsistenteRespuestaService $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        // El asistente se enciende SOLO en las pruebas que lo necesitan.
        config([
            'punta.asistente.activo'  => true,
            'punta.asistente.api_key' => 'clave-de-prueba',
            'punta.asistente.url'     => 'https://api.deepseek.com/chat/completions',
        ]);

        // Sin caché entre pruebas: si no, la segunda leería la respuesta de la primera y no
        // probaría nada.
        Cache::flush();

        $this->asistente = app(AsistenteRespuestaService::class);
    }

    /** Los resultados que el buscador ya encontró, tal como se los pasa al asistente. */
    private function fuentes(): \Illuminate\Support\Collection
    {
        // ══════════════════════════════════════════════════════════════════════
        // ESTAS SON LAS CLAVES **REALES** QUE DEVUELVE BuscadorService
        // ══════════════════════════════════════════════════════════════════════
        //
        //     ['tipo', 'icono', 'titulo', 'subtitulo', 'fragmento', 'score', 'url', 'meta']
        //
        // La primera versión de este fixture usaba 'texto' y 'fuente' — claves que NO EXISTEN.
        // Me las inventé, y la prueba pasaba igual porque el servicio también las leía mal.
        //
        // Dos errores que se cancelaban. Cuando arreglé el servicio para leer las claves de
        // verdad, la prueba se puso roja... y parecía que el servicio estaba roto. No lo estaba:
        // era la prueba, que describía un contrato inventado.
        //
        // ── La lección ──
        //
        // Un fixture que se inventa la forma de los datos NO PRUEBA NADA. Prueba que el servicio
        // funciona con datos que nunca va a recibir.
        //
        // Si esta prueba hubiera usado desde el principio las claves reales, habría cazado el bug
        // el primer día: el asistente recibía fuentes SIN TEXTO ("Artículo 1" y nada más) y el
        // modelo, muy correctamente, decía que no le bastaban. En producción, en silencio.
        return collect([
            [
                'tipo'      => 'articulo',
                'titulo'    => 'Artículo 15',
                'subtitulo' => 'Reglamento de Comercio',
                'fragmento' => 'El costo de la licencia de funcionamiento es de 500 pesos.',
                'score'     => 0.9,
                'url'       => '/regulaciones/7',
                'meta'      => ['regulacion_id' => 7, 'nodo_id' => null, 'tipo_nodo' => 'articulo'],
            ],
            [
                'tipo'      => 'articulo',
                'titulo'    => 'Artículo 16',
                'subtitulo' => 'Reglamento de Comercio',
                'fragmento' => 'La licencia tendrá vigencia de un año calendario.',
                'score'     => 0.7,
                'url'       => '/regulaciones/7',
                'meta'      => ['regulacion_id' => 7, 'nodo_id' => null, 'tipo_nodo' => 'articulo'],
            ],
        ]);
    }

    /** Simula lo que devuelve DeepSeek. */
    private function elModeloResponde(array $json): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($json)]],
                ],
            ], 200),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El camino bueno
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Con fuentes reales y un modelo que cita bien, sale una respuesta — CON SU CITA.
     *
     * Fíjate en lo que se comprueba: no solo que haya texto, sino que la respuesta lleve
     * `fuente`, `articulo` y `regulacion_id`. Una respuesta sin cita no sirve: el ciudadano
     * no puede comprobarla, y el Ayuntamiento no puede defenderla.
     */
    public function test_una_respuesta_bien_citada_se_construye(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia de funcionamiento cuesta 500 pesos.',
            'fuentes'    => [1],
        ]);

        $r = $this->asistente->construir('cuánto cuesta la licencia', $this->fuentes(), 'costo');

        $this->assertNotNull($r);
        $this->assertStringContainsString('500 pesos', $r['definicion']);

        // La cita se arma con el subtitulo (la regulación) y el titulo (la etiqueta del nodo).
        $this->assertSame('Reglamento de Comercio', $r['fuente'], 'La respuesta debe decir DE DÓNDE sale.');
        $this->assertSame('Artículo 15', $r['articulo']);
        $this->assertSame(7, $r['regulacion_id']);
    }

    /**
     * La confianza es 'generada', y eso NO es un detalle cosmético.
     *
     * Es lo que permite a la pantalla marcar la respuesta como REDACTADA POR UNA MÁQUINA,
     * distinta de una definición legal curada por una persona (confianza 'alta') o extraída
     * del articulado (confianza 'media').
     *
     * Si la vista no las distingue, este servicio entero es un riesgo en vez de una ayuda: un
     * ciudadano tiene derecho a saber si lo que lee lo escribió el Ayuntamiento o lo redactó
     * un modelo.
     */
    public function test_la_respuesta_se_marca_como_generada(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia cuesta 500 pesos.',
            'fuentes'    => [1],
        ]);

        $r = $this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo');

        $this->assertSame('generada', $r['confianza']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Que NO invente (los tres candados)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * EL MODELO DICE QUE NO LE BASTA → no hay respuesta.
     *
     * Y esto NO es un fallo: es el comportamiento correcto.
     *
     * Que un modelo sepa decir "no lo sé" es la diferencia entre un asistente y un generador
     * de plausibilidades. La prueba existe para que nadie "arregle" ese caso rellenándolo con
     * algo.
     */
    public function test_si_el_modelo_dice_que_no_le_basta_no_hay_respuesta(): void
    {
        $this->elModeloResponde([
            'suficiente' => false,
            'respuesta'  => '',
            'fuentes'    => [],
        ]);

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));
    }

    /**
     * UNA RESPUESTA SIN CITAS SE DESCARTA. Aunque el texto sea perfecto.
     *
     * Si el modelo no puede decir de DÓNDE sacó lo que dice, es que no lo sacó de ningún
     * sitio: lo produjo. Y eso es justo lo que no queremos.
     */
    public function test_una_respuesta_sin_citar_fuentes_se_descarta(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia cuesta 500 pesos y se tramita en tres días hábiles.',
            'fuentes'    => [], // ← no cita nada
        ]);

        $this->assertNull(
            $this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'),
            'El modelo dio una respuesta sin decir de dónde la sacó. Eso es inventar, aunque '
            . 'acierte por casualidad.'
        );
    }

    /**
     * LA PRUEBA MÁS IMPORTANTE DEL ARCHIVO.
     *
     * El modelo cita la fuente [9]. Solo se le dieron 2. Se la inventó.
     *
     * Y no se descarta solo esa cita: SE DESCARTA LA RESPUESTA ENTERA.
     *
     * Puede parecer excesivo, y no lo es. Un modelo que inventa una cita también inventa el
     * contenido que le atribuye: no son dos fallos independientes, son el mismo fallo asomando
     * por dos sitios.
     *
     * Quedarse con "la parte buena" de una respuesta que ya demostró estar inventando es
     * exactamente el error que este servicio existe para evitar. Es lo mismo que decidimos con
     * el formulario manipulado del requisito ajeno: si alguien falseó un campo, no hay razón
     * para confiar en el resto.
     */
    public function test_si_el_modelo_cita_una_fuente_inventada_se_descarta_todo(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia cuesta 500 pesos, según el artículo 42.',
            'fuentes'    => [1, 9], // ← la 9 no existe: solo se le dieron 2 fuentes
        ]);

        $this->assertNull(
            $this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'),
            'El modelo citó una fuente inexistente. Un modelo que inventa una cita también '
            . 'inventa el contenido: se descarta la respuesta entera, no solo la cita.'
        );
    }

    /**
     * SIN FUENTES, NI SIQUIERA SE LLAMA A LA API.
     *
     * Si el buscador no encontró nada, el asistente no puede saber nada. Pedirle que responda
     * de todas formas es pedirle explícitamente que se lo invente.
     *
     * Se comprueba que NO se hizo ninguna petición HTTP: no es solo que devuelva null, es que
     * ni siquiera lo intenta. Cada llamada cuesta dinero y tiempo, y esta no tendría ningún
     * sentido.
     */
    public function test_sin_resultados_no_se_llama_siquiera_a_la_api(): void
    {
        Http::fake();

        $this->assertNull($this->asistente->construir('xyzabc', collect(), null));

        Http::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Que su fallo NO rompa el buscador
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA API SE CAE → el buscador sigue.
     *
     * Un error 500 de DeepSeek no puede convertirse en un error 500 de PUNTA. El ciudadano ve
     * su lista de resultados, exactamente como la vería hoy sin asistente.
     */
    public function test_si_la_api_falla_no_revienta_nada(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response('Error interno', 500)]);

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));
    }

    /** La API tarda demasiado → el buscador sigue. */
    public function test_si_la_api_no_responde_no_revienta_nada(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'));

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));
    }

    /** La API devuelve algo que no es JSON → el buscador sigue. */
    public function test_si_la_api_devuelve_basura_no_revienta_nada(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'esto no es JSON, es texto suelto']]],
            ], 200),
        ]);

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. El interruptor
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * APAGADO significa APAGADO: ni una llamada.
     *
     * Es el interruptor de emergencia. Si algo va mal en producción —la API se vuelve loca,
     * la factura se dispara, alguien encuentra una respuesta mala—, se pone
     * ASISTENTE_ACTIVO=false y el buscador vuelve a ser exactamente el de antes. Sin
     * despliegue, sin migración, sin tocar código.
     *
     * Un interruptor que hay que probar para saber si funciona no es un interruptor.
     */
    public function test_apagado_no_hace_ninguna_llamada(): void
    {
        config(['punta.asistente.activo' => false]);
        Http::fake();

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));

        Http::assertNothingSent();
    }

    /**
     * Encendido PERO SIN API KEY = apagado.
     *
     * Un `activo = true` sin clave no es "medio encendido": es un servicio que va a fallar en
     * cada búsqueda, y va a tardar 8 segundos en fallar. Eso es PEOR que estar apagado —
     * ralentiza todas las búsquedas del municipio sin dar ningún error visible.
     */
    public function test_sin_api_key_es_como_estar_apagado(): void
    {
        config(['punta.asistente.api_key' => null]);
        Http::fake();

        $this->assertNull($this->asistente->construir('cuánto cuesta', $this->fuentes(), 'costo'));

        Http::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. Privacidad
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * NO SE ENVÍA NINGÚN DATO PERSONAL A LA API EXTERNA.
     *
     * El asistente solo puede leer lo que BuscadorService le pasa, y BuscadorService solo
     * devuelve contenido público: artículos de reglamentos publicados, nombres de trámites,
     * requisitos y costos. Todo eso ya está en el portal ciudadano.
     *
     * Esta prueba lo verifica de la única forma seria: INSPECCIONANDO LO QUE DE VERDAD SE
     * MANDÓ por la red. No se fía de que el diseño sea correcto — comprueba el cuerpo de la
     * petición.
     *
     * Porque el día que alguien añada un campo `firmante` o `usuario` a los resultados del
     * buscador "para que salga en la lista", ese dato empezaría a viajar a un servidor externo
     * sin que nadie se diera cuenta. Esta prueba se pondría roja.
     */
    public function test_no_se_envia_ningun_dato_personal(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia cuesta 500 pesos.',
            'fuentes'    => [1],
        ]);

        // Se cuela un dato personal entre los resultados, como haría un refactor descuidado.
        $conDatoPersonal = $this->fuentes()->push([
            'tipo'      => 'tramite',
            'titulo'    => 'Licencia',
            'subtitulo' => 'Trámite · Comercio',
            'fragmento' => 'Trámite de licencia de funcionamiento',
            'score'     => 0.5,
            'meta'      => ['regulacion_id' => null, 'nodo_id' => null],

            // Estos dos NO deberían viajar a una API externa. Se cuelan aquí como los colaría un
            // refactor descuidado que añadiera campos "para que salgan en la lista".
            'firmante'  => 'María Pérez López',
            'email'     => 'maria.perez@lapaz.gob.mx',
        ]);

        $this->asistente->construir('cuánto cuesta', $conDatoPersonal, 'costo');

        Http::assertSent(function ($request) {
            $cuerpo = json_encode($request->data());

            $this->assertStringNotContainsString('María Pérez', $cuerpo,
                'Se envió el nombre de una persona a una API externa.');

            $this->assertStringNotContainsString('@lapaz.gob.mx', $cuerpo,
                'Se envió un correo electrónico a una API externa.');

            return true;
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. La caché
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * La misma pregunta con las mismas fuentes se pregunta UNA sola vez.
     *
     * Cien ciudadanos preguntando "cuánto cuesta la licencia" no pueden producir cien llamadas
     * a una API que se paga por uso. Y la respuesta sería idéntica: la temperatura es 0.
     *
     * La caché se invalida sola, porque la clave incluye las fuentes: si se reestructura una
     * regulación y el buscador devuelve otros artículos, la clave cambia y se vuelve a
     * preguntar. Nadie tiene que acordarse de limpiarla.
     */
    public function test_la_misma_pregunta_no_se_paga_dos_veces(): void
    {
        $this->elModeloResponde([
            'suficiente' => true,
            'respuesta'  => 'La licencia cuesta 500 pesos.',
            'fuentes'    => [1],
        ]);

        $this->asistente->construir('cuánto cuesta la licencia', $this->fuentes(), 'costo');
        $this->asistente->construir('cuánto cuesta la licencia', $this->fuentes(), 'costo');

        Http::assertSentCount(1);
    }
}
