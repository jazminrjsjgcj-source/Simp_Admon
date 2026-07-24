<?php

namespace Tests\Feature;

use App\Services\ReformuladorConsultaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La IA propone otras palabras para buscar. NO busca, NO responde, y NO PUEDE CAMBIAR DE TEMA.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ HACE Y QUÉ NO
 * ══════════════════════════════════════════════════════════════════════
 *
 *     LA IA NO BUSCA. PROPONE PALABRAS. EL BUSCADOR BUSCA.
 *
 * Este servicio recibe "¿qué tasa de impuesto predial corresponde a una casa habitación?" y
 * devuelve algo como ["predial casa habitación", "millar valor catastral casa habitación"].
 *
 * Luego BuscadorService busca con esas palabras, contra la base, como siempre. Si el modelo
 * propone términos que no existen en ninguna regulación, no se encuentra nada — y no pasa nada.
 *
 * La IA sigue sin poder inventar un dato. Solo puede sugerir por dónde mirar.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL RIESGO REAL, Y ES SERIO
 * ══════════════════════════════════════════════════════════════════════
 *
 * Que la IA traduzca a un concepto QUE SÍ EXISTE PERO ES OTRO:
 *
 *     "cuánto pago por la basura"  →  la IA propone "impuesto predial"
 *                                  →  el buscador encuentra artículos del predial
 *                                  →  el asistente redacta una respuesta PERFECTAMENTE CITADA
 *                                     y COMPLETAMENTE FALSA sobre lo que hay que pagar
 *
 * Y los tres candados del asistente NO LO DETECTARÍAN: la cita existe, el dato está en el texto,
 * el artículo es real. Todo cuadra. Y es sobre otra cosa.
 *
 * Un ciudadano se presentaría en ventanilla con la cifra del predial creyendo que es la de la
 * basura.
 *
 * Es el bug más peligroso que este proyecto podría tener, porque sería IMPOSIBLE de detectar
 * desde el código: todo estaría formalmente correcto.
 *
 * Por eso la mitad de estas pruebas comprueban que NO cambia de tema.
 */
class ReformuladorConsultaTest extends TestCase
{
    use RefreshDatabase;

    private ReformuladorConsultaService $reformulador;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'punta.asistente.activo'  => true,
            'punta.asistente.api_key' => 'clave-de-prueba',
            'punta.asistente.url'     => 'https://api.deepseek.com/chat/completions',
        ]);

        Cache::flush();

        $this->reformulador = app(ReformuladorConsultaService::class);
    }

    private function elModeloPropone(array $consultas): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['consultas' => $consultas])]],
                ],
            ], 200),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El camino bueno
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * EL CASO REAL QUE MOTIVÓ ESTE SERVICIO.
     *
     * "¿Qué tasa de impuesto predial corresponde a una casa habitación?" devolvía CERO
     * resultados, y la respuesta existe: el artículo 31 fracción I dice "2 al millar anual sobre
     * el valor catastral de los predios destinados... para su propia casa habitación".
     *
     * Esa fracción NO dice "tasa", NO dice "impuesto", NO dice "predial" (dice "predios"). Solo
     * dice "casa habitación": dos palabras de cinco. Y el AND las exige todas.
     */
    public function test_devuelve_consultas_con_el_vocabulario_de_la_ley(): void
    {
        $this->elModeloPropone([
            'predial casa habitación',
            'millar valor catastral casa habitación',
        ]);

        $alternativas = $this->reformulador->reformular(
            'Qué tasa de impuesto predial corresponde a una casa habitación'
        );

        $this->assertCount(2, $alternativas);
        $this->assertContains('predial casa habitación', $alternativas);
    }

    /**
     * Máximo TRES alternativas.
     *
     * No es tacañería: cada una es una ronda completa de consultas contra las cinco fuentes.
     * Diez alternativas serían cincuenta consultas, y el ciudadano esperando.
     */
    public function test_como_mucho_devuelve_tres(): void
    {
        $this->elModeloPropone([
            'predial casa habitación',
            'millar valor catastral',
            'predios destinados habitación',
            'impuesto predial urbano',
            'valor catastral tasas',
        ]);

        $this->assertCount(
            3,
            $this->reformulador->reformular('qué tasa de predial paga una casa habitación')
        );
    }

    /**
     * Se descartan las que son FRASES, no términos de búsqueda.
     *
     * Si el modelo devuelve "cuál es la tasa que aplica a las casas habitación en el municipio",
     * eso no es un término: es la pregunta reescrita. Y buscarla con AND daría cero, igual que la
     * original.
     */
    public function test_descarta_las_frases_largas(): void
    {
        $this->elModeloPropone([
            'predial casa habitación',                                          // 3 palabras: sirve
            'cuál es la tasa que aplica a las casas habitación del municipio',   // 11: es una frase
        ]);

        $alternativas = $this->reformulador->reformular('qué tasa de predial paga una casa');

        $this->assertCount(1, $alternativas);
        $this->assertSame('predial casa habitación', $alternativas[0]);
    }

    /**
     * UNA REFORMULACIÓN CON TILDES NO ES "UNA FRASE".
     *
     * ══════════════════════════════════════════════════════════════════════
     * EL BUG QUE ESTA PRUEBA CAZA, Y ES MÍO
     * ══════════════════════════════════════════════════════════════════════
     *
     * El filtro de longitud usaba str_word_count(). Y esa función NO ENTIENDE ACENTOS: cuenta
     * solo caracteres ASCII.
     *
     *     str_word_count('millar valor catastral casa habitación')  →  6
     *
     * ¡SEIS! Porque parte "habitación" en DOS: "habitaci" + "n".
     *
     * Y con el límite en 4, esa reformulación —cinco términos jurídicos perfectamente útiles— se
     * descartaba con el mensaje "es una frase, no un término".
     *
     * El log de producción lo demostró:
     *
     *     "aceptadas": ["predial casa habitación"]
     *     "descartadas": ["millar valor catastral casa habitación (es una frase, no un término)",
     *                     "predios destinados casa habitación (es una frase, no un término)"]
     *
     * Dos de tres propuestas, tiradas. Y las dos eran BUENAS.
     *
     * ── La ironía ──
     *
     * Es el MISMO bug de los acentos que acabábamos de arreglar en el buscador. str_word_count es
     * a preg_split lo que substr es a mb_substr: una función que ignora que el español tiene
     * tildes.
     *
     * Lo cometí YO, en el servicio que escribí para ayudar a resolver ese mismo problema. Y no
     * daba ningún error: simplemente descartaba las mejores propuestas, en silencio.
     *
     * Las palabras jurídicas españolas van LLENAS de tildes: habitación, sanción, instalación,
     * valoración, construcción, regulación. Ese filtro estaba sesgado contra exactamente el
     * vocabulario que necesitamos.
     */
    public function test_una_reformulacion_con_tildes_no_es_una_frase(): void
    {
        $this->elModeloPropone([
            'millar valor catastral casa habitación',   // 5 palabras reales, todas útiles
            'predios destinados casa habitación',       // 4 palabras reales
        ]);

        $alternativas = $this->reformulador->reformular(
            'qué tasa de impuesto predial corresponde a una casa habitación'
        );

        $this->assertCount(
            2,
            $alternativas,
            'Se descartó una reformulación válida por "demasiado larga". Probablemente el filtro '
            . 'usa str_word_count(), que NO entiende acentos: parte "habitación" en dos '
            . '("habitaci" + "n") e infla el conteo.'
            . "\n\n"
            . 'Y las palabras jurídicas españolas van llenas de tildes —habitación, sanción, '
            . 'instalación, construcción—, así que ese filtro está sesgado contra EXACTAMENTE el '
            . 'vocabulario que necesitamos.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. QUE NO CAMBIE DE TEMA (el candado que de verdad importa)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA MÁS IMPORTANTE DE ESTE ARCHIVO.
     *
     * El modelo propone "impuesto predial" para una pregunta sobre BASURA. Se descarta.
     *
     * ── Por qué esto es lo más peligroso que podría pasar ──
     *
     * "Basura" e "impuesto predial" son los dos cobros municipales. Una traducción así es
     * PLAUSIBLE — un modelo podría hacerla sin ninguna mala intención.
     *
     * Y produciría una respuesta perfectamente citada, con un artículo real, con una cifra que
     * está literalmente en el texto... sobre el impuesto EQUIVOCADO.
     *
     * Los tres candados del asistente no la detectarían. Todo estaría formalmente correcto.
     *
     * Un ciudadano se presentaría en ventanilla con la cifra del predial creyendo que es la de la
     * basura.
     *
     * ── El candado ──
     *
     * Cada reformulación debe conservar al menos una palabra significativa de la pregunta
     * original. "Basura" puede convertirse en "residuos sólidos"... pero entonces algo de
     * "basura" o de su tema tiene que sobrevivir.
     *
     * No es infalible. Pero convierte un fallo catastrófico e indetectable en uno improbable.
     */
    public function test_no_puede_cambiar_de_tema(): void
    {
        $this->elModeloPropone([
            'impuesto predial valor catastral',   // ← CAMBIÓ DE TEMA. Fuera.
            'recolección basura semifijos',       // ← conserva "basura". Vale.
        ]);

        $alternativas = $this->reformulador->reformular('cuánto pago por la basura de mi negocio');

        $this->assertNotContains(
            'impuesto predial valor catastral',
            $alternativas,
            'La IA cambió el tema de BASURA a PREDIAL, y se aceptó. El buscador encontrará '
            . 'artículos del predial, el asistente redactará una respuesta perfectamente citada '
            . 'con una cifra real... sobre el impuesto EQUIVOCADO. Y ningún candado lo detectaría: '
            . 'todo estaría formalmente correcto.'
        );

        $this->assertContains('recolección basura semifijos', $alternativas);
    }

    /** Y si TODAS cambian de tema, no se devuelve ninguna. Más vale no buscar que buscar mal. */
    public function test_si_todas_cambian_de_tema_no_se_devuelve_ninguna(): void
    {
        $this->elModeloPropone([
            'impuesto predial urbano',
            'derechos de construcción',
            'licencia de funcionamiento',
        ]);

        $this->assertSame(
            [],
            $this->reformulador->reformular('cuánto pago por la basura de mi negocio'),
            'Ninguna alternativa conserva el tema (basura). Devolver una búsqueda sobre otro tema '
            . 'es peor que no devolver nada: el ciudadano recibiría una respuesta impecable sobre '
            . 'algo que no preguntó.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Que su fallo no rompa nada
    // ═══════════════════════════════════════════════════════════════════════

    /** Apagado significa apagado: ni una llamada. */
    public function test_apagado_no_hace_ninguna_llamada(): void
    {
        config(['punta.asistente.activo' => false]);
        Http::fake();

        $this->assertSame([], $this->reformulador->reformular('cuánto cuesta el permiso'));

        Http::assertNothingSent();
    }

    /** La API se cae → array vacío → el buscador sigue igual que ayer. */
    public function test_si_la_api_falla_devuelve_vacio(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response('error', 500)]);

        $this->assertSame([], $this->reformulador->reformular('cuánto cuesta el permiso'));
    }

    /** La API tarda demasiado → array vacío. */
    public function test_si_la_api_no_responde_devuelve_vacio(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'));

        $this->assertSame([], $this->reformulador->reformular('cuánto cuesta el permiso'));
    }

    /**
     * Una consulta de una o dos palabras NO se reformula, y ni siquiera se llama a la API.
     *
     * "predial" ya ES un término de búsqueda. Pedirle a un modelo que lo traduzca es tirar tiempo
     * y dinero: no hay nada que traducir.
     */
    public function test_una_consulta_corta_no_se_reformula(): void
    {
        Http::fake();

        $this->assertSame([], $this->reformulador->reformular('impuesto predial'));

        Http::assertNothingSent();
    }

    /** La misma pregunta no se paga dos veces. */
    public function test_la_misma_pregunta_se_cachea(): void
    {
        $this->elModeloPropone(['predial casa habitación']);

        $this->reformulador->reformular('qué tasa de predial paga una casa habitación');
        $this->reformulador->reformular('qué tasa de predial paga una casa habitación');

        Http::assertSentCount(1);
    }
}
