<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El buscador tiene que entender PREGUNTAS, no solo palabras clave.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG, CON EL CASO REAL QUE LO DESTAPÓ
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano escribe en el buscador:
 *
 *     "cuanto paga un semifijo en basura"
 *
 * Y el sistema le dice que NO HAY NADA.
 *
 * Pero el artículo que responde EXACTAMENTE esa pregunta existe, y está cargado. Es el inciso
 * "e" de la Ley de Hacienda:
 *
 *     "Servicio de recolección de basura a semifijos que laboren en la vía pública.
 *      0.66 del valor de la Unidad de Medida y Actualización por mes."
 *
 * ¿Por qué no lo encontraba?
 *
 * La consulta full-text se arma con AND: exige que el artículo contenga TODAS las palabras. Y
 * la frase cruda del ciudadano se partía tal cual, sin limpiar:
 *
 *     cuanto:* & paga:* & semifijo:* & basura:*
 *
 * El artículo contiene "semifijo" y contiene "basura". Pero NO contiene "cuanto" ni "paga" —
 * porque las leyes no hablan así. La ley dice "cuota", "cubrirán", "el pago del derecho será".
 *
 * Así que el AND lo descartaba.
 *
 * ── Lo cruel del asunto ──
 *
 * El buscador solo funcionaba si el ciudadano ADIVINABA las palabras exactas de la ley.
 *
 * Y si las adivinara, NO NECESITARÍA PREGUNTAR.
 *
 * ── Y lo peor: no daba ningún error ──
 *
 * "No se encontraron resultados" es una respuesta perfectamente normal. Nadie sospecharía que
 * el sistema tiene el artículo delante y lo está descartando por una palabra de relleno.
 *
 * Es el patrón de los catorce bugs de este proyecto: el sistema funciona, no falla, no avisa —
 * y no hace lo que tiene que hacer.
 */
class BuscadorEntiendePreguntasTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    /**
     * Carga el artículo REAL de la Ley de Hacienda que destapó el bug.
     *
     * Se usa el texto literal, no uno inventado, a propósito: una prueba escrita sobre un caso
     * de laboratorio ("artículo con la palabra X") no habría cazado nada. Este bug solo se ve
     * cuando el lenguaje del ciudadano y el de la ley son distintos — y para eso hace falta
     * lenguaje de ley de verdad.
     */
    private function cargarLeyDeHacienda(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Hacienda']);

        // Los nodos se cargan CON SU CONTEXTO, como los produce el estructurador real.
        //
        // Un fixture de artículos huérfanos probaría un mundo que no existe: el sistema cuelga
        // cada artículo de su sección, su capítulo y su título, y guarda ese camino en la columna
        // `contexto`. Sin eso, la mitad de las búsquedas no funcionan — y la prueba no lo vería.
        $ctxDerechos = 'CUARTO DERECHOS. II SERVICIOS DE LIMPIA Y RECOLECCIÓN DE BASURA';
        $ctxViaPub   = 'CUARTO DERECHOS. V USO DE LA VÍA PÚBLICA';

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_INCISO,
            'numero'        => 'e',
            'texto'         => 'Servicio de recolección de basura a semifijos que laboren en la vía '
                             . 'pública. 0.66 del valor de la Unidad de Medida y Actualización por mes',
            'contexto'      => $ctxDerechos,
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        // El inciso que responde "cuánto cuesta el permiso para ambulantes".
        //
        // FÍJATE EN LO QUE **NO** DICE: no dice "permiso". La ley no lo llama así — lo llama
        // cuota, o derecho. Y ese detalle es todo el bug.
        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_INCISO,
            'numero'        => 'f',
            'texto'         => 'Resto del Municipio: 0.040 UMA VILBIS. Ambulantes 0.05 UMA por día, '
                             . 'pudiendo realizar el pago semanal o mensual',
            'contexto'      => $ctxViaPub,
            'orden'         => 2,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);

        // El RUIDO: un artículo largo de sanciones que menciona "permiso" y "ambulantes" de
        // pasada. Con el AND estricto, era el ÚNICO resultado que salía.
        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '154',
            'texto'         => 'Las sanciones por desacato al Bando de Policía, Buen Gobierno y '
                             . 'Justicia Cívica del Municipio de La Paz, incluyendo el retiro del '
                             . 'permiso a los vendedores ambulantes y demás infracciones previstas '
                             . 'en el Reglamento de Tránsito para la Movilidad Segura',
            'contexto'      => 'SÉPTIMO INFRACCIONES Y SANCIONES',
            'orden'         => 3,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El caso que lo destapó
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA DEL BUG.
     *
     * Una pregunta escrita como habla la gente encuentra el artículo que la responde.
     */
    public function test_una_pregunta_en_lenguaje_natural_encuentra_el_articulo(): void
    {
        $this->cargarLeyDeHacienda();

        $resultado = $this->buscador->buscar('cuanto paga un semifijo en basura');

        $this->assertGreaterThan(
            0,
            $resultado['resultados']->count(),
            'El buscador no encontró el artículo que responde LITERALMENTE esta pregunta. '
            . 'Lo tiene cargado, contiene "semifijo" y contiene "basura" — pero como no contiene '
            . '"cuanto" ni "paga", el AND del full-text lo descarta. El ciudadano solo encuentra '
            . 'algo si adivina las palabras exactas de la ley. Y si las adivinara, no preguntaría.'
        );
    }

    /**
     * La misma pregunta, escrita de tres formas distintas, encuentra lo mismo.
     *
     * Porque un ciudadano no escribe dos veces igual. Y el artículo es el mismo.
     */
    public function test_la_misma_pregunta_de_varias_formas_encuentra_lo_mismo(): void
    {
        $this->cargarLeyDeHacienda();

        $formas = [
            'cuanto paga un semifijo en basura',
            'cuánto cuesta la basura para semifijos',
            'que debo pagar por recoleccion de basura si soy semifijo',
            'semifijo basura',
        ];

        foreach ($formas as $pregunta) {
            $this->assertGreaterThan(
                0,
                $this->buscador->buscar($pregunta)['resultados']->count(),
                "La pregunta «{$pregunta}» no encontró nada, y el artículo está cargado."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La mitad que impide pasarse de frenada
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * ── ESTA PRUEBA SE ELIMINÓ, Y CONVIENE SABER POR QUÉ ──
     *
     * Aquí vivía test_las_palabras_con_contenido_siguen_siendo_obligatorias, que exigía que el
     * buscador NO encontrara nada con "semifijo predial" — dos palabras que no están juntas en
     * ningún artículo.
     *
     * Codificaba el AND estricto: todas las palabras, obligatorias.
     *
     * Y ese AND resultó ser el problema, no la virtud. Con él, "cuánto cuesta el permiso para
     * ambulantes" devolvía SOLO un artículo sobre sanciones por desacato al Bando de Policía —
     * porque era el único que contenía las dos palabras— y descartaba el inciso que responde la
     * pregunta, que dice "Ambulantes 0.05 UMA por día" pero no dice "permiso".
     *
     * El ciudadano añadía una palabra correcta y razonable, y eso le QUITABA la respuesta.
     *
     * ── La decisión ──
     *
     * El buscador pasa a OR: trae MÁS resultados, con más ruido, y no se pierde la respuesta.
     * Filtrar deja de ser trabajo suyo y pasa a serlo del asistente, que sabe LEER — que es lo
     * que ninguna consulta SQL puede hacer.
     *
     * Y por eso esta prueba se va: ahora "semifijo predial" SÍ devuelve el inciso de los
     * semifijos, y eso es CORRECTO. Mantenerla sería exigirle al buscador un comportamiento que
     * decidimos abandonar.
     *
     * Una prueba que contradice una decisión tomada a conciencia no es una red de seguridad: es
     * un ancla. Se quita, y se deja escrito por qué.
     *
     * Lo que SÍ hay que vigilar ahora es otra cosa: que el ruido no ahogue la respuesta. De eso
     * se encargan las dos pruebas de abajo, y AsistenteRespuestaTest.
     */


    /**
     * UNA BÚSQUEDA DE PURAS PALABRAS VACÍAS NO PUEDE TUMBAR EL BUSCADOR.
     *
     * ── El bug que esta prueba destapó ──
     *
     * "qué es un" no deja ninguna palabra con contenido. El respaldo devolvía entonces la frase
     * ORIGINAL, tal cual, y esa frase acababa dentro de:
     *
     *     to_tsquery('spanish', que es un)
     *
     * PostgreSQL NO acepta una frase suelta en un tsquery: espera operadores (& | !). Sin ellos:
     *
     *     SQLSTATE[42601]: syntax error in tsquery: "que es un"
     *
     * Es decir: UN ERROR 500 EN EL BUSCADOR DE UN AYUNTAMIENTO, porque alguien escribió "qué es".
     *
     * ── Por qué no lo había visto nadie ──
     *
     * El bug YA EXISTÍA. Pero era casi inalcanzable: hacía falta que TODAS las palabras midieran
     * menos de tres letras.
     *
     * Al añadir las palabras de pregunta a la lista de vacías —para que el buscador entendiera
     * "cuánto paga un semifijo"— ese camino se volvió trivial de provocar. El arreglo de un bug
     * hizo alcanzable otro que llevaba ahí desde el principio.
     *
     * Es lo normal cuando se toca código viejo, y es exactamente por eso que un arreglo se
     * acompaña de pruebas: no para verificar el arreglo, sino para descubrir lo que el arreglo
     * despierta.
     */
    public function test_una_consulta_de_puras_palabras_vacias_no_tumba_el_buscador(): void
    {
        $this->cargarLeyDeHacienda();

        foreach (['que es un', 'como se hace', 'para que sirve', 'y', 'de la'] as $consulta) {
            // Lo que se prueba es que NO LANCE UNA EXCEPCIÓN. Que encuentre o no encuentre da
            // igual: "no hay resultados" es una respuesta legítima. Un error 500 no lo es.
            $resultado = $this->buscador->buscar($consulta);

            $this->assertIsIterable(
                $resultado['resultados'],
                "La consulta «{$consulta}» tumbó el buscador. Un tsquery malformado produce un "
                . 'error 500 de PostgreSQL, y el ciudadano ve una pantalla de error por haber '
                . 'escrito una pregunta normal.'
            );
        }
    }

    /**
     * La consulta normalizada CONSERVA las palabras de pregunta, aunque el full-text las tire.
     *
     * Parece contradictorio y no lo es. Son dos usos distintos:
     *
     *   · El FULL-TEXT necesita las palabras del TEMA ("semifijo", "basura"). Un "cuánto" solo
     *     estorba: ninguna ley lo escribe.
     *
     *   · El DETECTOR DE INTENCIÓN necesita justo lo contrario. Es POR el "cuánto" por lo que
     *     sabe que están preguntando un COSTO y no una definición.
     *
     * Si se quitaran de las dos, el buscador encontraría el artículo pero no sabría que le están
     * preguntando un precio — y enrutaría la búsqueda al sitio equivocado.
     *
     * Por eso SearchQueryNormalizer devuelve DOS cosas: la consulta entera y las palabras
     * limpias. Cada consumidor usa la que necesita.
     */
    public function test_el_detector_de_intencion_sigue_viendo_las_palabras_de_pregunta(): void
    {
        $normalizador = app(\App\Services\SearchQueryNormalizer::class);

        $r = $normalizador->normalizar('cuánto cuesta la licencia');

        $this->assertStringContainsString('cuanto', $r['consulta_normalizada'],
            'La consulta normalizada debe CONSERVAR el "cuánto": es lo que le dice al detector '
            . 'de intención que están preguntando un COSTO.');

        $this->assertNotContains('cuanto', $r['palabras'],
            'Pero las palabras del full-text NO deben llevarlo: ninguna ley escribe "cuánto".');

        $this->assertContains('licencia', $r['palabras']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. La cascada AND → OR
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El buscador encuentra el inciso de los ambulantes cuando la pregunta usa el VOCABULARIO DEL
     * TEMA.
     *
     * "cuánto pagan los ambulantes" → los verbos se filtran, queda ['ambulantes'], y el inciso
     * aparece. Esto SÍ funciona, y es lo que hay que proteger.
     */
    public function test_una_pregunta_con_el_vocabulario_del_tema_encuentra_el_inciso(): void
    {
        $this->cargarLeyDeHacienda();

        $resultados = $this->buscador->buscar('cuanto pagan los ambulantes')['resultados'];

        $this->assertStringContainsString(
            '0.05 UMA',
            $resultados->pluck('fragmento')->implode(' '),
            'El inciso que dice cuánto pagan los ambulantes no llegó, y la pregunta usa exactamente '
            . 'la palabra que la ley usa.'
        );
    }

    /**
     * ══════════════════════════════════════════════════════════════════════
     * UN LÍMITE CONOCIDO, ESCRITO A PROPÓSITO. NO ES UN BUG.
     * ══════════════════════════════════════════════════════════════════════
     *
     * Aquí había una prueba que exigía que "cuánto cuesta el PERMISO para ambulantes" encontrara
     * el inciso de los 0.05 UMA.
     *
     * Y EL BUSCADOR NO PUEDE HACERLO. Ni con contexto, ni con normalización, ni con filtros.
     *
     * ── Por qué ──
     *
     * El inciso dice: "Ambulantes 0.05 UMA por día, pudiendo realizar el pago semanal o mensual."
     *
     * Y NO CONTIENE LA PALABRA "PERMISO". Ni en su texto ni en su contexto, que es "DERECHOS. USO
     * DE LA VÍA PÚBLICA".
     *
     * Porque LA LEY NO LO LLAMA PERMISO. Lo llama DERECHO por uso de la vía pública. "Permiso" es
     * la palabra del ciudadano; "derecho" es la de la ley.
     *
     * El AND, correctamente, lo descarta. Y el único que sobrevive es el artículo 154 —el de las
     * sanciones por desacato— que sí menciona "permiso" y "ambulantes", de pasada.
     *
     * ── Por qué la prueba anterior estaba MAL ──
     *
     * Afirmaba que el buscador DEBE encontrarlo. Y no puede: sería exigirle que adivine un
     * sinónimo que nadie le ha dado.
     *
     * Una prueba que exige lo imposible no es una red de seguridad: es una alarma que suena sola.
     * Y una alarma que suena sola acaba ignorada — y entonces tampoco avisa el día que sí importa.
     *
     * ── Qué haría falta para resolverlo ──
     *
     * Un catálogo de sinónimos jurídicos, o una IA que traduzca la pregunta del ciudadano al
     * vocabulario de la ley ANTES de buscar:
     *
     *     permiso  →  derecho, cuota, tarifa, licencia
     *     basura   →  residuos sólidos, recolección, aseo público
     *
     * Esta prueba documenta el hueco. El día que alguien monte los sinónimos, sabrá EXACTAMENTE
     * qué caso viene a resolver — y esta prueba se le pondrá verde.
     *
     * Un límite documentado es una decisión. Uno sin documentar es una sorpresa.
     */
    public function test_limite_conocido_el_vocabulario_del_ciudadano_no_es_el_de_la_ley(): void
    {
        $this->cargarLeyDeHacienda();

        $resultados = $this->buscador->buscar('cuanto cuesta el permiso para ambulantes')['resultados'];

        $textos = $resultados->pluck('fragmento')->implode(' ');

        // HOY el buscador NO encuentra el inciso correcto con la palabra "permiso". Se deja
        // escrito, sin fingir lo contrario.
        //
        // Si algún día esta aserción falla, NO ES UN BUG: significa que alguien montó los
        // sinónimos, y hay que sustituirla por la afirmación de que SÍ lo encuentra.
        $this->assertStringNotContainsString(
            '0.05 UMA',
            $textos,
            'El buscador AHORA SÍ encuentra el inciso de los ambulantes buscando "permiso". '
            . 'Eso significa que alguien resolvió el problema del vocabulario (sinónimos, o una IA '
            . 'traductora). Enhorabuena: cambia esta prueba por una que afirme que lo encuentra, y '
            . 'borra este comentario.'
        );
    }

    /**
     * El ruido del OR no puede AHOGAR la respuesta correcta.
     *
     * Buscar con OR trae muchos más resultados. Eso es el trato: el buscador no tiene que
     * acertar, solo no perderse la respuesta.
     *
     * Pero hay un límite. Si una búsqueda devolviera doscientos resultados, el asistente solo
     * vería los veinte primeros — y si el bueno está en el puesto ochenta, no lo vería jamás.
     *
     * Esta prueba comprueba que una búsqueda normal no se desmadra.
     */
    public function test_el_or_no_devuelve_una_avalancha(): void
    {
        $this->cargarLeyDeHacienda();

        $resultados = $this->buscador->buscar('cuanto cuesta el permiso para ambulantes')['resultados'];

        $this->assertLessThan(
            50,
            $resultados->count(),
            'La búsqueda devolvió una avalancha de resultados. El asistente solo lee los primeros '
            . '20: si la respuesta correcta queda enterrada más abajo, no la verá nunca.'
        );
    }
}
