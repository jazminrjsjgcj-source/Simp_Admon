<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buscador global de PUNTA — buscador híbrido jurídico-administrativo.
 *
 * Sigue sin depender de IA: todo el ranking y el enrutamiento se deciden
 * con reglas explícitas y el scoring nativo de MySQL FULLTEXT.
 *
 * Fuentes de datos consultables:
 *   1. Articulado de regulaciones (regulacion_nodos.texto)
 *   2. Regulaciones (nombre, resumen, objetivo, palabras_clave, materia)
 *   3. Trámites y servicios (nombre_oficial, objetivo, poblacion_objetivo)
 *   4. Requisitos (nombre)
 *   5. Fundamentos jurídicos (normativa_nombre, articulo_fraccion, resumen)
 *   6. Acciones de agenda (descripcion, meta) — solo cuando hay filtro activo
 *
 * Cuando $regulacionIds está presente (filtro por regulación):
 *   - Fuente 1: solo nodos de esa regulación.
 *   - Fuente 2: solo esa regulación (en su metadata).
 *   - Fuentes 3, 4 y 5: solo trámites/requisitos/fundamentos que tengan
 *     fundamento jurídico apuntando a esa regulación.
 *   - Fuente 6: acciones de agenda cuyo trámite tenga fundamento en esa
 *     regulación (fuente que solo aparece con filtro activo para no
 *     saturar los resultados generales).
 *   - La respuesta destacada siempre se muestra (decisión de diseño).
 *   - El enrutamiento por concepto del diccionario jurídico se desactiva
 *     cuando hay filtro: ya el usuario restringió el universo, no tiene
 *     sentido enfocarlo aún más en una sola fuente.
 *
 * Clean Code §3: cada método hace una sola cosa.
 * Clean Code §11: el servicio no sabe nada de HTTP ni de vistas.
 */
class BuscadorService
{
    public function __construct(
        private SearchQueryNormalizer $normalizador,
        private LegalDictionaryService $diccionario,
        private FeaturedAnswerService $respuestaDestacada,
        private AsistenteRespuestaService $asistente,
        private SearchIntentDetector $detector,
    ) {}

    /**
     * Número máximo de resultados por fuente.
     * Se limita para que una fuente con muchos matches no desplace a las demás.
     */
    /**
     * Cuántos resultados devuelve cada fuente.
     *
     * Se separa el ARTICULADO del resto, y no es un capricho.
     *
     * ── Por qué el articulado necesita más ──
     *
     * Una sola ley puede tener DECENAS de artículos sobre el mismo tema. La Ley de Hacienda de
     * La Paz tiene DIECISIETE artículos que mencionan "espectáculos": el que define el objeto, el
     * que dice quién es sujeto, el que fija el plazo de pago, el que exige la garantía, el que
     * regula el boletaje...
     *
     * Y solo UNO dice cuánto se paga:
     *
     *     "Artículo 65.- Los sujetos pagarán por concepto de este impuesto, el 8% del monto
     *      total de los ingresos obtenidos."
     *
     * Ese artículo es CORTO. Los otros son largos y repiten la palabra "espectáculos" cuatro o
     * cinco veces. Y ts_rank premia la repetición: los largos puntúan más alto.
     *
     * Con un límite de 10, el artículo 65 QUEDABA FUERA DEL CORTE. El asistente recibía el marco
     * legal y el objeto del impuesto, pero no el porcentaje. Y respondía, muy honestamente, que
     * no encontraba cómo se calcula.
     *
     * El modelo hacía bien su trabajo. Le estábamos dando la basura y escondiéndole el oro.
     *
     * ── Por qué 30 y no 100 ──
     *
     * Porque el asistente solo lee las 20 mejores (config punta.asistente.max_fuentes), y porque
     * cada consulta cuesta tiempo. Treinta da margen suficiente para que un artículo corto y
     * denso entre, sin inundar la pantalla.
     *
     * El resto de fuentes (trámites, requisitos, regulaciones) siguen en 10: ahí no existe el
     * problema de los "veinte artículos sobre lo mismo".
     */
    /**
     * Por encima de este porcentaje del articulado, una palabra deja de distinguir nada.
     *
     * Frecuencias reales en la Ley de Hacienda de La Paz (964 nodos), contadas SOLO SOBRE EL
     * TEXTO (ver el comentario largo de frecuenciaEnArticulado, que explica por qué no se cuenta
     * el contexto):
     *
     *     publicos      → 113 nodos = 11,7%  ← RUIDO. Vía pública, orden público, servicios
     *                                          públicos, notarios públicos... Exigirla no acota
     *                                          nada.
     *     cobros        →  31 nodos =  3,2%
     *     espectaculos  →  28 nodos =  2,9%  ← señala un tema
     *     patrimonio    →   2 nodos =  0,2%  ← rarísima en el texto, pero está en el TÍTULO de un
     *                                          capítulo entero: lo más informativo que hay
     *
     * El 5% deja fuera "publicos" y conserva todo lo demás. Es un número redondo, elegido con
     * criterio y no medido: si algún día se afina, que sea mirando qué palabras caen a cada lado
     * en las regulaciones reales del municipio, no a ojo.
     */
    private const UMBRAL_PALABRA_COMUN = 0.05;

    /**
     * Por debajo de este número de apariciones, una palabra NUNCA se descarta por común.
     *
     * Es el suelo del umbral porcentual, y existe porque un porcentaje sobre un corpus pequeño no
     * significa nada: en una regulación de 10 artículos, el 5% es cero, y el filtro tiraría
     * cualquier palabra que apareciera dos veces.
     *
     * Con pocos artículos, ninguna palabra es "demasiado común". No hay suficiente corpus para
     * que ninguna pierda su capacidad de distinguir.
     */
    private const MINIMO_PARA_DESCARTAR = 20;

    private const LIMITE_POR_FUENTE    = 10;
    private const LIMITE_ARTICULADO    = 30;

    /**
     * Longitud máxima del fragmento de texto que se muestra en cada resultado.
     */
    private const LARGO_FRAGMENTO = 250;

    /**
     * Mapa de `tabla_preferente` (columna de busqueda_diccionario_juridico)
     * a los métodos de búsqueda que ya existen en esta clase. Cuando el
     * diccionario jurídico dice "para 'costo', busca primero en
     * requisitos", este mapa le dice a buscar() cuál método llamar.
     *
     * Nota: 'definiciones_legales' y 'acciones_agenda' aparecen como
     * tabla_preferente en el diccionario pero NO están en este mapa
     * todavía. 'definiciones_legales' se resuelve aparte, a través de
     * FeaturedAnswerService (no es una búsqueda de lista, es una respuesta
     * destacada). 'acciones_agenda' todavía no tiene un método de búsqueda
     * propio en esta clase — si un concepto apunta ahí, el buscador cae de
     * vuelta a la búsqueda completa de las 5 fuentes en vez de fallar.
     */
    private const TABLA_A_METODO = [
        'requisitos'          => 'buscarEnRequisitos',
        'tramites'             => 'buscarEnTramites',
        'fundamento_juridico'  => 'buscarEnFundamentos',
    ];

    /**
     * Busca en las fuentes de datos y devuelve resultados ordenados por
     * relevancia, junto con la respuesta destacada (si se pudo construir
     * una) y el modo de búsqueda que se usó.
     *
     * ── Cómo decide entre buscar en una sola fuente o en las fuentes ────
     *
     * 1. Si $regulacionIds está presente, se busca en las 6 fuentes pero
     *    filtrando por esa regulación. El enrutamiento por concepto se
     *    desactiva (el usuario ya restringió el universo).
     * 2. Si el diccionario jurídico reconoce un concepto tipo "dato" en la
     *    consulta (por ejemplo, "costo" apunta a la tabla `requisitos`),
     *    se busca PRIMERO solo ahí. Es más rápido y evita que resultados
     *    genéricos de otras fuentes le ganen en el ranking al dato real.
     * 3. Si esa búsqueda enfocada no encuentra nada, se amplía
     *    AUTOMÁTICAMENTE a las 5 fuentes completas — una búsqueda enfocada
     *    que no encontró nada no le sirve a nadie.
     * 4. Si no se reconoce ningún concepto tipo "dato", se buscan las 5
     *    fuentes de siempre, sin cambios respecto al comportamiento anterior.
     *
     * El parámetro $forzarCompleto permite que la vista ofrezca un enlace
     * "Ver todos los resultados relacionados" que ignora el enrutamiento y
     * siempre busca en las 5 fuentes, sin importar lo que el diccionario
     * hubiera sugerido — el "Modo explorar" de la especificación del
     * buscador robusto.
     *
     * @param  string   $consulta        Texto libre que el usuario escribió.
     * @param  bool     $forzarCompleto  Si es true, ignora el enrutamiento
     *                   por concepto y siempre busca en todas las fuentes.
     * @param  array|null $regulacionIds  Cuando viene, restringe todos los
     *                   resultados a esas regulaciones y sus trámites/fundamentos
     *                   vinculados.
     * @param  array|null $tipos          Cuando viene, solo busca en esas
     *                   fuentes (articulo, regulacion, tramite, requisito,
     *                   fundamento, agenda). Si es null se busca en todas.
     * @return array{resultados: Collection, respuesta_destacada: array|null, modo: string}
     */
    public function buscar(
        string $consulta,
        bool $forzarCompleto = false,
        ?array $regulacionIds = null,
        ?array $tipos = null
    ): array {
        $consulta = trim($consulta);

        if (mb_strlen($consulta) < 2) {
            return $this->resultadoVacio();
        }

        $normalizado         = $this->normalizador->normalizar($consulta);
        $consultaNormalizada = $normalizado['consulta_normalizada'];
        $palabras            = $normalizado['palabras'];

        // La respuesta destacada se intenta siempre, sin importar el modo
        // de búsqueda — es independiente de si la lista de resultados es
        // enfocada, completa o filtrada por regulación.
        $respuestaDestacada = $this->respuestaDestacada->construir($consultaNormalizada, $palabras);

        // Se le pasan las PALABRAS YA LIMPIAS, no la frase cruda.
        //
        // Antes esto era prepararConsultaFulltext($consulta) — la frase entera, tal como la
        // escribió el ciudadano. Y el trabajo del normalizador (que ya había quitado las palabras
        // vacías) SE TIRABA A LA BASURA.
        //
        // El propio SearchQueryNormalizer lo dejaba dicho en un comentario:
        //
        //     'palabras' => $palabrasRelevantes,   // SIN palabras vacías, para diccionario y FULLTEXT
        //
        // Estaba preparado para esto. Nadie lo conectó.
        // AND sobre los sustantivos que DE VERDAD discriminan. Ver el comentario largo de abajo.
        $consultaFt = $this->prepararConsultaFulltext(
            $this->descartarPalabrasQueNoDistinguen($palabras),
            $consulta
        );

        // Helper: ¿debo incluir esta fuente según el filtro de tipos?
        // Si $tipos es null (sin filtro), se incluyen todas.
        $incluir = fn (string $tipo) => $tipos === null || in_array($tipo, $tipos);

        // ── Búsqueda con filtro por regulación ───────────────────────────
        // Cuando el usuario eligió una ley, se desactiva el enrutamiento por
        // concepto (el universo ya está restringido) y se incluye la fuente 6
        // (acciones de agenda), que no aparece en búsquedas generales para no
        // saturar los resultados.
        if (!empty($regulacionIds)) {
            $resultados = collect();
            if ($incluir('articulo'))   $resultados = $resultados->merge($this->buscarEnArticulado($consultaFt, $consulta, $regulacionIds));
            if ($incluir('regulacion')) $resultados = $resultados->merge($this->buscarEnRegulaciones($consultaFt, $consulta, $regulacionIds));
            if ($incluir('tramite'))    $resultados = $resultados->merge($this->buscarEnTramites($consultaFt, $consulta, $regulacionIds));
            if ($incluir('requisito'))  $resultados = $resultados->merge($this->buscarEnRequisitos($consultaFt, $consulta, $regulacionIds));
            if ($incluir('fundamento')) $resultados = $resultados->merge($this->buscarEnFundamentos($consultaFt, $consulta, $regulacionIds));
            if ($incluir('agenda'))     $resultados = $resultados->merge($this->buscarEnAgendaSeguro($consultaFt, $consulta, $regulacionIds));

            return $this->responder($resultados, $respuestaDestacada, 'filtrado', $consulta, $consultaNormalizada);
        }

        // ── Búsqueda sin filtro de regulación ────────────────────────────
        // Si hay filtro de tipos activo, se desactiva el enrutamiento por
        // concepto — el usuario ya eligió en qué fuentes quiere buscar.
        if (!$forzarCompleto && $tipos === null) {
            $metodoEnfocado = $this->determinarMetodoEnfocado($palabras);

            if ($metodoEnfocado !== null) {
                $resultadosEnfocados = collect($this->$metodoEnfocado($consultaFt, $consulta));

                if ($resultadosEnfocados->isNotEmpty()) {
                    return $this->responder($resultadosEnfocados, $respuestaDestacada, 'enfocado', $consulta, $consultaNormalizada);
                }
                // La búsqueda enfocada no encontró nada: se sigue de largo
                // hacia la búsqueda completa de abajo, sin necesidad de que
                // el usuario pida nada — este es el respaldo automático.
            }
        }

        // Búsqueda completa: las fuentes según el filtro de tipos (o las 5
        // de siempre si no hay filtro). Se ejecuta cuando no se reconoció un
        // concepto enfocable, cuando la búsqueda enfocada no encontró nada,
        // o cuando el usuario pidió explícitamente "ver todos los resultados".
        // ══════════════════════════════════════════════════════════════════════
        // SE BUSCA CON **OR**, NO CON AND. Y es una decisión, no un descuido.
        // ══════════════════════════════════════════════════════════════════════
        //
        // ── Por qué el AND no servía ──
        //
        // Un ciudadano escribe: "cuánto cuesta el permiso para ambulantes".
        //
        // Con AND, el tsquery exige las DOS palabras:  permiso:* & ambulantes:*
        //
        // Y el inciso que RESPONDE la pregunta —"Ambulantes 0.05 UMA por día"— contiene
        // "ambulantes" pero NO contiene "permiso". Porque la ley no lo llama permiso: lo llama
        // cuota, o derecho.
        //
        // Así que el AND lo descartaba. Y el ÚNICO superviviente era el artículo 154, sobre
        // sanciones por desacato al Bando de Policía, que menciona las dos palabras de pasada.
        //
        // El ciudadano añadía una palabra correcta y razonable, y eso le QUITABA la respuesta.
        //
        // ── Por qué OR y no sinónimos ──
        //
        // Se probó la cascada (AND primero, OR si no encuentra nada) y NO FUNCIONÓ: el AND SÍ
        // encontraba algo —el artículo equivocado—, así que el OR nunca se disparaba.
        //
        // Y traducir la pregunta al vocabulario legal tampoco basta: el inciso f no contiene
        // "cuota" ni "derecho" tampoco. Solo dice "Ambulantes 0.05 UMA". Da igual qué palabras se
        // elijan si el buscador sigue exigiéndolas TODAS.
        //
        // El problema nunca fue el vocabulario. Era la exigencia.
        //
        // ── El precio, y quién lo paga ──
        //
        // El OR trae MÁS RUIDO. Buscar "licencia de funcionamiento" devuelve ahora todo lo que
        // mencione "funcionamiento", de cualquier reglamento.
        //
        // Eso se paga con dos cosas:
        //
        //   1. ts_rank ordena por relevancia (lo más pertinente arriba).
        //   2. El ASISTENTE lee los resultados y ELIGE cuáles responden de verdad la pregunta.
        //      Ahí es donde una IA aporta algo que ninguna consulta SQL puede: entiende el
        //      CONTENIDO, no adivina palabras.
        //
        // El buscador ya no tiene que acertar. Solo tiene que NO PERDERSE LA RESPUESTA. Filtrar
        // es trabajo de quien sabe leer.
        // ══════════════════════════════════════════════════════════════════════
        // AND SOBRE LOS SUSTANTIVOS. Ni OR, ni cascadas, ni rarezas.
        // ══════════════════════════════════════════════════════════════════════
        //
        // Este bloque ha pasado por CUATRO diseños, y los tres primeros estaban mal. Merece la
        // pena dejarlos escritos, porque cada uno enseña algo.
        //
        // ── 1. AND sobre la frase cruda (el original) ──
        //
        //     cuanto:* & paga:* & semifijo:* & basura:*   →  0 resultados
        //
        // Exigía palabras que ninguna ley escribe. El ciudadano preguntaba y no encontraba nada.
        //
        // ── 2. OR (el segundo intento) ──
        //
        //     calculan:* | cobros:* | espectaculos:* | publicos:*   →  144 nodos
        //
        // "publicos" es veneno en una Ley de Hacienda: vía pública, servicios públicos, orden
        // público, alumbrado público... Con OR entraba cualquier nodo que dijera "público". Le
        // llegaban al asistente guías de traslado de animales al rastro.
        //
        // ── 3. Soltar palabras por rareza (el tercer intento, y el más elegante... y erróneo) ──
        //
        // La idea era ordenar por frecuencia y soltar primero las más comunes. "Cuanto más rara,
        // más informativa" — el IDF de toda la vida.
        //
        // Y se rompió. Frecuencias reales en la Ley de Hacienda:
        //
        //     calculan      →   7 nodos    ← LA MÁS RARA
        //     espectaculos  →  28 nodos
        //     cobros        →  31 nodos
        //     publicos      → 113 nodos
        //
        // "calculan" es MÁS RARA que "espectaculos". Así que se conservaba... y se soltaba el
        // tema. El buscador devolvía siete artículos sobre recargos y notarios.
        //
        // En una LEY, una palabra puede ser rara por dos motivos opuestos: porque es específica
        // del tema, o porque es del habla del ciudadano y la ley no la usa. La frecuencia NO LOS
        // DISTINGUE.
        //
        // ── 4. Lo que funciona: quitar los verbos y hacer AND sobre lo que queda ──
        //
        // Lo que distingue una palabra útil de una trampa no es su frecuencia. Es su categoría
        // gramatical:
        //
        //     "espectáculos" es un SUSTANTIVO  → la ley y el ciudadano lo escriben IGUAL.
        //     "calculan" es un VERBO           → la ley dice "pagarán".
        //
        // SearchQueryNormalizer se lleva los verbos de acción (calculan, cobros, dura, tramitar...)
        // junto con las palabras vacías. Lo que llega aquí son los SUSTANTIVOS DEL TEMA.
        //
        //     "cuáles y cómo se calculan los cobros sobre espectáculos públicos"
        //          ↓
        //     ['espectaculos', 'publicos']
        //          ↓
        //     espectaculos:* & publicos:*   →  21 nodos, TODOS del tema
        //
        // Y el artículo 65 —"los sujetos pagarán el 8%"— entra entre 21 candidatos en vez de
        // competir contra 144.
        //
        // El AND vuelve a ser lo correcto. Nunca fue el problema: el problema era exigirlo sobre
        // palabras que la ley no escribe.
        $resultados = $this->buscarEnTodasLasFuentes($consultaFt, $consulta, $incluir);


        return $this->responder($resultados, $respuestaDestacada, 'completo', $consulta, $consultaNormalizada);
    }

    /**
     * Decide si la consulta se puede resolver con una sola fuente, usando
     * el diccionario jurídico. Solo enruta cuando el concepto reconocido es
     * tipo "dato" (costo, requisitos, fundamento) — los conceptos tipo
     * "concepto" (servicio, trámite) se resuelven por FeaturedAnswerService
     * como respuesta destacada, no como una lista enfocada de resultados.
     *
     * @return string|null  Nombre del método de búsqueda a llamar, o null
     *                       si no se debe enrutar (se busca en las 5 fuentes).
     */
    private function determinarMetodoEnfocado(array $palabras): ?string
    {
        $concepto = $this->diccionario->encontrarConceptoEnPalabras($palabras);

        if ($concepto === null || $concepto->tipo_concepto !== 'dato') {
            return null;
        }

        return self::TABLA_A_METODO[$concepto->tabla_preferente] ?? null;
    }

    /**
     * Estructura vacía para cuando la consulta es demasiado corta (menos
     * de 2 caracteres) para intentar cualquier tipo de búsqueda.
     */
    /**
     * Descarta las palabras que aparecen en TANTOS artículos que no distinguen nada.
     *
     * ══════════════════════════════════════════════════════════════════════
     * LA IRONÍA QUE CIERRA TODO ESTO
     * ══════════════════════════════════════════════════════════════════════
     *
     * Después de cinco rediseños del buscador, el último obstáculo resultó ser este:
     *
     *     EL ARTÍCULO QUE RESPONDE LA PREGUNTA NO DICE "ESPECTÁCULOS PÚBLICOS".
     *     DICE "ESPECTÁCULO".
     *
     * El artículo 65 de la Ley de Hacienda:
     *
     *     "Los sujetos pagarán por concepto de este impuesto, el 8% del monto total de los
     *      ingresos obtenidos, que genere el ESPECTÁCULO que corresponda..."
     *
     * Singular. Sin "públicos".
     *
     * ¿Por qué? Porque NO LE HACE FALTA. Ya está dentro del capítulo titulado "IMPUESTO SOBRE
     * ESPECTÁCULOS PÚBLICOS". El contexto lo da la sección, no la frase.
     *
     * Un abogado lo entiende. Un AND de PostgreSQL, no.
     *
     * ── Y esto pasa en TODA ley bien redactada ──
     *
     * Los artículos NO REPITEN el título de su capítulo. Sería redundante. Así que exigir dos
     * palabras del tema descarta sistemáticamente los artículos MÁS PRECISOS — precisamente
     * porque son los que van al grano y no repiten contexto.
     *
     * Cuanto mejor escrito está un artículo, menos palabras del tema repite. Y más fácil es que
     * un AND lo descarte.
     *
     * ══════════════════════════════════════════════════════════════════════
     * EL CRITERIO: ¿ESTA PALABRA DISTINGUE ALGO?
     * ══════════════════════════════════════════════════════════════════════
     *
     * Frecuencias reales en la Ley de Hacienda de La Paz (unos 2.000 nodos):
     *
     *     espectaculos  →   28 nodos  (1,4% del corpus)   ← DISTINGUE. Señala un tema.
     *     publicos      →  113 nodos  (5,7% del corpus)   ← NO DISTINGUE. Ruido de fondo.
     *
     * "Públicos" sale por todas partes: vía pública, servicios públicos, orden público, alumbrado
     * público, contratación pública, bienes de dominio público, notarios públicos... Exigirla no
     * acota nada. Solo estorba.
     *
     * ── OJO: esto NO es "cuanto más rara, mejor" ──
     *
     * Ese criterio ya se probó, y falló estrepitosamente. "Calculan" aparece en 7 nodos —es MÁS
     * rara que "espectaculos"— y es completamente inútil: sale en artículos sobre recargos y
     * notarios, porque el stemmer la iguala a "cálculo".
     *
     * Aquí el criterio es el opuesto y mucho más simple:
     *
     *     Se descartan las palabras DEMASIADO COMUNES.
     *     Las raras se conservan TODAS.
     *
     * Y solo se descartan si queda AL MENOS UNA palabra. Si todas son comunes, se buscan todas:
     * más vale un AND con ruido que una consulta vacía.
     *
     * ── El umbral ──
     *
     * 5% del corpus. Es un número redondo elegido con criterio, no medido:
     *
     *   · Por debajo, una palabra señala un tema (espectáculos: 1,4%).
     *   · Por encima, es vocabulario administrativo genérico (públicos: 5,7%).
     *
     * Si algún día se afina, que sea con datos: mirando qué palabras caen a cada lado en las
     * regulaciones reales del municipio.
     */
    private function descartarPalabrasQueNoDistinguen(array $palabras): array
    {
        // Con una sola palabra no hay nada que descartar: es lo único que hay.
        if (count($palabras) <= 1) {
            return $palabras;
        }

        $totalNodos = \Illuminate\Support\Facades\Cache::remember(
            'buscador:total_nodos',
            now()->addHour(),
            fn () => (int) \Illuminate\Support\Facades\DB::table('regulacion_nodos')
                ->whereNull('deleted_at')
                ->count()
        );

        if ($totalNodos === 0) {
            return $palabras;
        }

        // ══════════════════════════════════════════════════════════════════════
        // EL SUELO MÍNIMO: sin él, un corpus pequeño se queda sin palabras
        // ══════════════════════════════════════════════════════════════════════
        //
        // El umbral es un PORCENTAJE (5%). Y un porcentaje sobre un corpus diminuto no significa
        // nada.
        //
        // En producción hay 964 nodos → el 5% son 48. Razonable.
        //
        // Pero en una prueba con 3 nodos, el 5% es CERO. Y entonces cualquier palabra que
        // aparezca en dos nodos se considera "demasiado común" y se descarta.
        //
        // Eso rompió las pruebas de los ambulantes: "ambulantes" salía en 2 de 3 nodos (67%), así
        // que el filtro la tiraba... y dejaba "permiso", que solo está en el artículo de las
        // sanciones. El buscador devolvía justo el resultado equivocado que veníamos a evitar.
        //
        // ── La regla ──
        //
        // Con pocos artículos, NINGUNA palabra es "demasiado común". No hay suficiente corpus para
        // que ninguna pierda su capacidad de distinguir.
        //
        // El suelo mínimo (20 nodos) hace que el filtro solo actúe cuando hay bastante material
        // como para que el porcentaje diga algo. Por debajo, se conservan todas las palabras.
        //
        // Es el mismo error que llevamos toda la sesión: un número mágico que funciona en un
        // contexto y se rompe en otro, sin dar ningún aviso.
        $umbral = max(
            self::MINIMO_PARA_DESCARTAR,
            (int) ceil($totalNodos * self::UMBRAL_PALABRA_COMUN)
        );

        $utiles = [];

        foreach ($palabras as $palabra) {
            $frecuencia = $this->frecuenciaEnArticulado($palabra);

            // Se conserva la palabra solo si cumple LAS DOS condiciones:
            //
            //   frecuencia > 0        → existe en el corpus. Una palabra que no aparece en ningún
            //                           artículo garantiza cero resultados si se exige en un AND.
            //                           "Calculan" parecía cumplir esto (sale en 7 nodos), pero
            //                           solo por el stemmer: PostgreSQL la iguala a "cálculo", y
            //                           esos 7 nodos hablan de recargos y notarios. Por eso los
            //                           verbos se filtran ANTES, en SearchQueryNormalizer.
            //
            //   frecuencia <= umbral  → no es tan común que deje de distinguir. "Públicos" sale
            //                           en el 5,7% del articulado: exigirla no acota nada.
            if ($frecuencia > 0 && $frecuencia <= $umbral) {
                $utiles[] = $palabra;
            }
        }

        // Si el filtro se lo llevó TODO, se devuelven las originales. Más vale un AND con ruido
        // que una consulta vacía: al menos el asistente tendrá algo que leer.
        return $utiles !== [] ? $utiles : $palabras;
    }

    /** En cuántos nodos del articulado aparece esta palabra. Se cachea: el corpus no cambia cada minuto. */
    private function frecuenciaEnArticulado(string $palabra): int
    {
        $limpia = preg_replace('/[&|!():*\'"]/', '', $palabra);

        if (mb_strlen($limpia) < 3) {
            return 0;
        }

        // ══════════════════════════════════════════════════════════════════════
        // LA FRECUENCIA SE CUENTA SOLO SOBRE EL **TEXTO**. NO SOBRE EL CONTEXTO.
        // ══════════════════════════════════════════════════════════════════════
        //
        // Parece incoherente —la búsqueda sí mira texto + contexto— y no lo es. Miden cosas
        // distintas a propósito, y este comentario existe porque la versión anterior las medía
        // igual y rompió el buscador.
        //
        // ── Qué pasó ──
        //
        // Al añadir el contexto heredado, TODOS los artículos de un capítulo pasaron a "decir" el
        // título de ese capítulo. Y las frecuencias se dispararon:
        //
        //     patrimonio, contando el contexto  →  134 nodos de 964  =  13,9%
        //     patrimonio, solo en el texto      →    2 nodos         =   0,2%
        //
        // Con el umbral del 5%, el filtro descartó "patrimonio" por DEMASIADO COMÚN. Y la
        // búsqueda se quedó con "impuestos" y "aplicables", que son ruido puro.
        //
        // El filtro tiró la palabra clave. Le devolvía al ciudadano sanciones, multas,
        // fideicomisos y registro de ganado.
        //
        // ── Por qué la frecuencia sobre el contexto NO sirve para decidir ──
        //
        // Compara:
        //
        //     publicos    → 113 nodos, DISPERSOS por toda la ley (vía pública, orden público,
        //                   servicios públicos, notarios públicos...). Es ruido de fondo: no acota
        //                   nada.
        //
        //     patrimonio  → 134 nodos, CONCENTRADOS en un capítulo. Y ese capítulo es EXACTAMENTE
        //                   lo que responde la pregunta.
        //
        // Las dos superan el umbral. Una es basura y la otra es la respuesta. LA FRECUENCIA NO LAS
        // SEPARA — igual que no separaba "calculan" de "espectaculos" cuando lo intentamos por
        // rareza.
        //
        // ── Lo que sí las separa ──
        //
        //     Una palabra que aparece en el CONTEXTO señala un capítulo entero.
        //     Es lo MÁS informativo que hay.
        //
        //     Una que aparece dispersa por el TEXTO, en cien sitios sin relación, es ruido.
        //
        // Por eso el filtro cuenta solo el texto: ahí "patrimonio" sale en 2 nodos —rarísima, se
        // conserva— y "publicos" en 113 —común, se descarta—. Justo lo que queremos.
        //
        // Y la búsqueda sigue usando texto + contexto, así que "patrimonio" encuentra los 134.
        //
        //     EL FILTRO DECIDE CON EL TEXTO. LA BÚSQUEDA USA EL CONTEXTO.
        //
        // Cada uno mide lo que debe.
        return \Illuminate\Support\Facades\Cache::remember(
            'buscador:frecuencia:' . md5($limpia),
            now()->addHour(),
            fn () => (int) \Illuminate\Support\Facades\DB::table('regulacion_nodos')
                ->whereNull('deleted_at')
                ->whereRaw(
                    "to_tsvector('spanish', coalesce(texto, '')) @@ to_tsquery('spanish', ?)",
                    [$limpia . ':*']
                )
                ->count()
        );
    }

    /**
     * Lanza la búsqueda contra las cinco fuentes, con el tsquery que se le dé.
     *
     * Existe para poder ejecutar la MISMA ronda dos veces —una con AND y otra con OR— sin
     * duplicar las cinco líneas. Y esa duplicación no es un problema estético: si estuvieran
     * escritas dos veces, alguien añadiría una sexta fuente en una de las dos y no en la otra, y
     * la búsqueda de respaldo devolvería menos resultados que la principal sin que nadie
     * entendiera por qué.
     *
     * Es lo mismo que ya nos pasó con las cinco listas del dashboard, cuatro con límite y una
     * sin él.
     */
    private function buscarEnTodasLasFuentes(string $consultaFt, string $consulta, callable $incluir): Collection
    {
        $resultados = collect();

        if ($incluir('articulo'))   $resultados = $resultados->merge($this->buscarEnArticulado($consultaFt, $consulta));
        if ($incluir('regulacion')) $resultados = $resultados->merge($this->buscarEnRegulaciones($consultaFt, $consulta));
        if ($incluir('tramite'))    $resultados = $resultados->merge($this->buscarEnTramites($consultaFt, $consulta));
        if ($incluir('requisito'))  $resultados = $resultados->merge($this->buscarEnRequisitos($consultaFt, $consulta));
        if ($incluir('fundamento')) $resultados = $resultados->merge($this->buscarEnFundamentos($consultaFt, $consulta));

        return $resultados;
    }

    /**
     * Arma la respuesta final de buscar(), y de paso le da al asistente su única oportunidad.
     *
     * ── POR QUÉ EXISTE ESTE HELPER ──
     *
     * buscar() tiene TRES puntos de retorno: filtrado, enfocado y completo. Añadir la llamada al
     * asistente en cada uno sería copiar la misma lógica tres veces... y olvidarla en uno.
     *
     * Ya sabemos cómo acaba eso. El dashboard tenía cinco listas de pendientes: cuatro con
     * ->take(5) y una sin ningún límite. El 5 estaba escrito a mano cinco veces, y alguien lo
     * olvidó en la quinta. Un valor repetido tres veces es un valor que alguien va a olvidar en
     * uno de los tres.
     *
     * ── EL ORDEN IMPORTA, Y MUCHO ──
     *
     * El asistente SOLO se llama si se cumplen las dos condiciones:
     *
     *   1. NO HAY YA UNA RESPUESTA DESTACADA. El diccionario curado (confianza ALTA) y las
     *      definiciones extraídas del articulado (MEDIA) tienen prioridad absoluta sobre una
     *      redacción automática. Si una persona curó ese concepto, su definición vale más que
     *      cualquier cosa que redacte un modelo.
     *
     *   2. HAY RESULTADOS QUE RESUMIR. Sin fuentes, el asistente no puede saber nada — y pedirle
     *      que responda igualmente es pedirle explícitamente que se lo invente.
     *
     * O sea: la IA es el ÚLTIMO recurso, nunca el primero. Solo entra donde hoy no hay nada.
     */
    private function responder(
        Collection $resultados,
        ?array $destacada,
        string $modo,
        string $consulta,
        string $consultaNormalizada,
    ): array {
        $resultados = $resultados->sortByDesc('score')->values();

        if ($destacada === null && $resultados->isNotEmpty()) {
            $destacada = $this->asistente->construir(
                $consulta,
                $resultados,
                $this->detector->detectar($consultaNormalizada),
            );
        }

        return [
            'resultados'          => $resultados,
            'respuesta_destacada' => $destacada,
            'modo'                => $modo,
        ];
    }

    /**
     * Resultado vacío: la consulta era demasiado corta y no se buscó nada.
     *
     * OJO: aquí NO se llama al asistente, y es deliberado. No hay resultados, así que no hay
     * fuentes que redactar. Llamarlo sería pedirle que responda sin material — es decir,
     * pedirle literalmente que se lo invente.
     */
    private function resultadoVacio(): array
    {
        return [
            'resultados'          => collect(),
            'respuesta_destacada' => null,
            'modo'                => 'completo',
        ];
    }

    /**
     * Convierte la consulta del usuario a formato FULLTEXT BOOLEAN.
     *
     * Cada palabra se convierte en un prefijo obligatorio: "licencia comercio"
     * se convierte en "+licencia* +comercio*". Esto significa que el resultado
     * debe contener AMBAS palabras (o palabras que empiecen con ellas).
     *
     * Se filtran palabras de menos de 3 caracteres porque MySQL FULLTEXT
     * las ignora por defecto (ft_min_word_len = 3 en InnoDB).
     */
    /**
     * Arma la consulta tsquery de PostgreSQL a partir de las palabras RELEVANTES.
     *
     * ══════════════════════════════════════════════════════════════════════
     * QUÉ ESTABA MAL, Y POR QUÉ NADIE LO VEÍA
     * ══════════════════════════════════════════════════════════════════════
     *
     * Este método recibía la frase CRUDA que escribió el ciudadano y la partía por espacios. El
     * trabajo del SearchQueryNormalizer —que ya había quitado las palabras vacías— se tiraba a
     * la basura.
     *
     * Y como el tsquery se arma con ' & ' (AND), el buscador exigía que el artículo contuviera
     * TODAS las palabras. Incluidas las que ninguna ley usa jamás.
     *
     *     "cuanto paga un semifijo en basura"
     *          ↓
     *     cuanto:* & paga:* & semifijo:* & basura:*
     *          ↓
     *     CERO RESULTADOS
     *
     * ¿Por qué cero? Porque ningún artículo de la Ley de Hacienda contiene la palabra "cuanto",
     * ni la palabra "paga". La ley dice "cuota", "cubrirán", "el pago del derecho será".
     *
     * Y aquí está lo cruel: el artículo QUE RESPONDE ESA PREGUNTA EXISTE. Dice:
     *
     *     "Servicio de recolección de basura a semifijos que laboren en la vía pública.
     *      0.66 del valor de la Unidad de Medida y Actualización por mes."
     *
     * Está ahí. Contiene "semifijo" y contiene "basura". Pero como no contiene "cuanto" ni
     * "paga", el buscador lo descartaba.
     *
     * El ciudadano preguntaba exactamente lo que la ley responde, y el sistema le decía que no
     * había nada. Solo funcionaba si adivinaba las palabras exactas de la ley — y si las
     * adivinara, no necesitaría preguntar.
     *
     * ══════════════════════════════════════════════════════════════════════
     * QUÉ HACE AHORA
     * ══════════════════════════════════════════════════════════════════════
     *
     *     "cuanto paga un semifijo en basura"
     *          ↓  el normalizador quita las palabras vacías y las de pregunta
     *     ['semifijo', 'basura']
     *          ↓
     *     semifijo:* & basura:*
     *          ↓
     *     El inciso e de la Ley de Hacienda
     *
     * Se mantiene el AND, y es deliberado: con OR, buscar "licencia de funcionamiento" devolvería
     * cientos de artículos que solo mencionan "funcionamiento". El AND sigue siendo lo correcto —
     * lo que estaba mal era EXIGIRLO SOBRE PALABRAS QUE NO SON DEL TEMA.
     *
     * @param array<string> $palabras Palabras relevantes (sin vacías), del SearchQueryNormalizer.
     * @param string $consultaOriginal Se usa solo como respaldo si no quedó ninguna palabra útil.
     */
    private function prepararConsultaFulltext(array $palabras, string $consultaOriginal, string $operador = ' & '): string
    {
        $terminos = [];

        foreach ($palabras as $palabra) {
            // Se limpian los operadores de tsquery (& | ! : * paréntesis, comillas) para que una
            // palabra escrita por el usuario no rompa la consulta.
            $limpia = preg_replace('/[&|!():*\'"]/', '', $palabra);

            if (mb_strlen($limpia) >= 3) {
                // ':*' busca por prefijo: "semifijo:*" también encuentra "semifijos".
                $terminos[] = $limpia . ':*';
            }
        }

        if (! empty($terminos)) {
            return implode($operador, $terminos);
        }

        // ══════════════════════════════════════════════════════════════════════
        // EL RESPALDO — y aquí había un bug que reventaba el buscador
        // ══════════════════════════════════════════════════════════════════════
        //
        // Se llega aquí cuando no quedó NINGUNA palabra utilizable. Pasa con:
        //
        //   · Consultas que son todo palabras vacías: "qué es", "cómo se hace".
        //   · Palabras de menos de tres letras: "IVA", "UMA".
        //
        // La versión anterior devolvía la frase ORIGINAL, tal cual:
        //
        //     return preg_replace('/[&|!():*\'"]/', '', $consultaOriginal);
        //
        // Y eso acababa dentro de to_tsquery('spanish', que es un).
        //
        // PostgreSQL NO acepta una frase suelta en un tsquery: espera operadores. Sin ellos,
        // lanza un error de sintaxis:
        //
        //     SQLSTATE[42601]: syntax error in tsquery: "que es un"
        //
        // Es decir: un error 500 EN EL BUSCADOR DE UN AYUNTAMIENTO, porque alguien escribió
        // "qué es".
        //
        // El bug ya existía antes de tocar nada. Pero era casi inalcanzable: hacía falta que
        // TODAS las palabras midieran menos de tres letras. Al añadir las palabras de pregunta a
        // la lista de vacías, se volvió trivial de provocar — y por eso salió a la luz.
        //
        // ── La solución ──
        //
        // Se unen las palabras con ' & ', igual que en el camino normal. Un tsquery bien formado
        // que probablemente no encuentre nada es infinitamente mejor que uno malformado que tumba
        // la página.
        $crudas = preg_split('/\s+/', preg_replace('/[&|!():*\'"]/', '', $consultaOriginal));

        $crudas = array_values(array_filter(
            array_map('trim', $crudas),
            fn ($p) => $p !== ''
        ));

        if (empty($crudas)) {
            // Ni siquiera hay una palabra. Un tsquery vacío no encuentra nada y no revienta:
            // 'zzzznoexiste' es una forma explícita de decir "no busques nada".
            return 'zzzznoexiste';
        }

        return implode($operador, array_map(fn ($p) => $p . ':*', $crudas));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 1: Articulado de regulaciones (regulacion_nodos)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en el articulado de las regulaciones (los nodos: artículos, fracciones, incisos).
     *
     * ══════════════════════════════════════════════════════════════════════
     * SE BUSCA SOBRE TEXTO + CONTEXTO. Y ES EL ARREGLO MÁS IMPORTANTE DE TODOS.
     * ══════════════════════════════════════════════════════════════════════
     *
     * ── El bug ──
     *
     * Un ciudadano pregunta "¿cuáles son los impuestos aplicables al patrimonio?" y el buscador
     * devuelve DOS resultados, ninguno correcto: uno sobre recuperaciones de capital y otro sobre
     * fideicomisos.
     *
     * Mientras tanto, la Ley de Hacienda tiene EXACTAMENTE esto:
     *
     *     CAPÍTULO II — IMPUESTOS SOBRE EL PATRIMONIO
     *          SECCIÓN I    — Impuesto Predial
     *          SECCIÓN II   — Impuesto sobre Adquisición de Bienes Inmuebles
     *          SECCIÓN III  — Impuesto sobre Urbanización
     *
     * La respuesta está ahí. Con el título del capítulo diciéndolo literalmente.
     *
     * ── Por qué no la encontraba ──
     *
     * El artículo 26 dice: "Son objeto del Impuesto Predial, la propiedad, usufructo, goce, uso y
     * posesión..."
     *
     * Y NUNCA dice "patrimonio". No le hace falta: ya está DENTRO del capítulo que lo dice.
     *
     * El buscador solo miraba el texto del nodo. Ignoraba el capítulo al que pertenece. Para él,
     * cada artículo era una isla sin contexto.
     *
     * Un abogado que lee el artículo 26 SABE que es un impuesto al patrimonio, porque ve el
     * encabezado tres líneas más arriba. El buscador, no.
     *
     * ── Y es el MISMO bug que descartaba el artículo 65 ──
     *
     * Aquel decía "que genere el ESPECTÁCULO que corresponda" —singular, sin "públicos"— porque
     * ya estaba dentro del capítulo "IMPUESTO SOBRE ESPECTÁCULOS PÚBLICOS".
     *
     * No eran dos bugs. Era el mismo, dos veces. Y explica media docena de rediseños fallidos de
     * este archivo.
     *
     * ── La causa raíz, que conviene tener presente ──
     *
     *     EN TODA LEY BIEN REDACTADA, UN ARTÍCULO NO REPITE EL TÍTULO DE SU CAPÍTULO.
     *
     * Sería redundante. El contexto lo da la estructura, no la frase.
     *
     * Y de ahí se sigue algo incómodo: cuanto MEJOR escrito está un artículo, MENOS palabras del
     * tema repite. Y más invisible se vuelve para un buscador que solo mira texto plano.
     *
     * Estábamos penalizando la buena redacción jurídica.
     *
     * ── La solución ──
     *
     * Cada nodo guarda el texto de sus ancestros en la columna `contexto` (ver la migración
     * add_contexto_a_regulacion_nodos). Y aquí se busca sobre los dos campos concatenados.
     *
     *     "patrimonio"             → encuentra el artículo 26, el 38 y toda la sección predial.
     *     "espectáculos públicos"  → encuentra el artículo 65, aunque diga "espectáculo" a secas.
     *
     * ══════════════════════════════════════════════════════════════════════
     * DOS DECISIONES MÁS QUE PARECEN DETALLES Y NO LO SON
     * ══════════════════════════════════════════════════════════════════════
     *
     * ── 1. EL LÍMITE ES 30, NO 10 (LIMITE_ARTICULADO) ──
     *
     * Una sola ley puede tener DECENAS de artículos sobre el mismo tema. La Ley de Hacienda de
     * La Paz tiene DIECISIETE que mencionan "espectáculos": el que define el objeto, el que dice
     * quién es sujeto, el que fija el plazo de pago, el que exige la garantía, el del boletaje...
     *
     * Y solo UNO dice cuánto se paga:
     *
     *     "Artículo 65.- Los sujetos pagarán por concepto de este impuesto, el 8% del monto
     *      total de los ingresos obtenidos."
     *
     * Con un límite de 10, ese artículo QUEDABA FUERA DEL CORTE. El asistente recibía el marco
     * legal y la definición del impuesto, pero no el porcentaje — y respondía, honestamente, que
     * no encontraba cómo se calcula.
     *
     * El modelo hacía bien su trabajo. Le estábamos dando la basura y escondiéndole el oro.
     *
     * ── 2. ts_rank LLEVA UN TERCER ARGUMENTO: EL 2 ──
     *
     * Ese 2 normaliza la puntuación DIVIDIÉNDOLA ENTRE LA LONGITUD del texto.
     *
     * Sin él, ts_rank premia la REPETICIÓN: cuantas más veces aparezca la palabra buscada, más
     * alto puntúa. Y eso favorece a los artículos LARGOS.
     *
     * El artículo 63 —que solo DEFINE qué es un espectáculo— enumera teatro, ballet, ópera,
     * conciertos, circo, lucha libre, box, fútbol, béisbol, carreras de burros... Repite la
     * palabra cuatro veces, tiene 150 palabras, y NO DICE CUÁNTO SE PAGA.
     *
     * El artículo 65 tiene 40 palabras y la mitad son la respuesta.
     *
     * Sin normalizar, GANABA EL 63.
     *
     * La normalización captura una intuición simple y cierta:
     *
     *     Un texto CORTO que menciona tu palabra HABLA de tu palabra.
     *     Un texto LARGO que la menciona de pasada, no.
     *
     * Es un cambio de comportamiento GLOBAL: reordena todas las búsquedas del articulado. Y en
     * general mejora — los artículos precisos suben, los divagantes bajan.
     *
     * ── Y una advertencia sobre los comentarios dentro del SQL ──
     *
     * Esta explicación vivía DENTRO del selectRaw(), como comentarios SQL. Y rompía el archivo:
     * el SQL va en una cadena PHP con comillas dobles, y el comentario contenía comillas dobles
     * ("cuáles y cómo se calculan..."). PHP cerraba la cadena ahí y petaba con un error de
     * sintaxis.
     *
     * Un comentario no puede romper el código. Por eso la explicación vive AQUÍ, y dentro del SQL
     * solo queda una línea que remite a este docblock.
     */
    private function buscarEnArticulado(
        string $consultaFt,
        string $consultaOriginal,
        ?array $regulacionIds = null
    ): array {
        $query = DB::table('regulacion_nodos as n')
            ->join('regulaciones as r', 'n.regulacion_id', '=', 'r.id')
            ->selectRaw("
                n.id,
                n.tipo,
                n.numero,
                n.texto,
                n.regulacion_id,
                r.nombre as regulacion_nombre,
                -- El 2 del tercer argumento normaliza por longitud del texto.
                -- La explicacion completa esta en el docblock del metodo, arriba.
                -- Se busca sobre TEXTO + CONTEXTO (el titulo del capitulo y la seccion).
                -- El porque esta en el docblock de arriba: un articulo NO REPITE el titulo de su
                -- capitulo, asi que sin el contexto es invisible para quien busque por el tema.
                -- El 2 del tercer argumento normaliza por longitud.
                ts_rank(
                    to_tsvector('spanish', coalesce(n.texto, '') || ' ' || coalesce(n.contexto, '')),
                    to_tsquery('spanish', ?),
                    2
                ) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish', coalesce(n.texto, '') || ' ' || coalesce(n.contexto, '')) @@ to_tsquery('spanish', ?)",
                [$consultaFt]
            )

            // ══════════════════════════════════════════════════════════════════
            // FUERA LOS ROTULOS DE SECCION. No son contenido: son letreros.
            // ══════════════════════════════════════════════════════════════════
            //
            // ── El caso real ──
            //
            // Alguien pregunta "cuales y como se calculan los cobros sobre espectaculos
            // publicos". Y de las 20 fuentes que llegaban al asistente, CUATRO eran esto:
            //
            //     "IMPUESTOS SOBRE ESPECTACULOS PUBLICOS"
            //     "IMPUESTO SOBRE DIVERSIONES PUBLICAS"
            //     "SERVICIOS DE SEGURIDAD PUBLICA Y TRANSITO"
            //     "BIENES DE USO COMUN Y OCUPACION DE LA VIA PUBLICA"
            //
            // Son los titulos de las secciones de la ley. Tres o cuatro palabras en mayusculas.
            // No dicen nada: solo anuncian de que va lo que viene despues.
            //
            // El estructurador no los reconocio como titulos —no llevan la palabra TITULO ni
            // CAPITULO delante, que es lo que busca— y los volco como nodos de tipo parrafo.
            //
            // ── Y la normalizacion por longitud los CATAPULTO ──
            //
            // Un rotulo de cuatro palabras que contiene "espectaculos" tiene densidad perfecta:
            // toda la palabra buscada, ninguna de relleno. Con ts_rank normalizado, gana a
            // CUALQUIER articulo real.
            //
            // El arreglo que subio los articulos CORTOS Y DENSOS tambien subio estos, que son
            // CORTOS Y VACIOS. La normalizacion no distingue "denso" de "vacio": solo mide
            // longitud.
            //
            // Le estabamos dando al asistente los rotulos de las secciones y preguntandole cuanto
            // se paga. Su respuesta —"las fuentes mencionan que EXISTE un impuesto, pero no
            // especifican las tarifas"— era exactamente la que mereciamos.
            //
            // ── Por que se arregla aqui y no en el parser ──
            //
            // Arreglar el estructurador seria lo ideal: que reconociera estos rotulos como titulos
            // de seccion. Pero obligaria a REESTRUCTURAR TODAS las regulaciones cargadas, y el
            // parser es la pieza mas delicada del sistema.
            //
            // Aqui el arreglo es acotado y reversible: los rotulos siguen en la base (por si el
            // dia de manana sirven para navegar el articulado), pero NO COMPITEN en la busqueda.
            //
            // ── El criterio ──
            //
            // Un parrafo que es TODO MAYUSCULAS y tiene menos de 60 caracteres es un rotulo, no
            // contenido. Ninguna ley escribe un articulo entero en mayusculas, y ninguno cabe en
            // 60 caracteres.
            //
            // Solo se aplica a los PARRAFOS. Un articulo, una fraccion o un inciso NUNCA se
            // descartan, por cortos que sean: el articulo 65 dice "el 8% del monto total" y tiene
            // que llegar siempre.
            ->where(function ($q) {
                $q->where('n.tipo', '!=', 'parrafo')
                  ->orWhereRaw('LENGTH(n.texto) >= 60')
                  ->orWhereRaw('n.texto <> UPPER(n.texto)');
            })
            ->whereNull('n.deleted_at');

        if (!empty($regulacionIds)) {
            $query->whereIn('n.regulacion_id', $regulacionIds);
        }

        $resultados = $query
            ->orderByDesc('score')
            ->limit(self::LIMITE_ARTICULADO)
            ->get();

        return $resultados->map(function ($r) {
            $etiqueta = $this->etiquetaNodo($r->tipo, $r->numero);
            return [
                'tipo'      => 'articulo',
                'icono'     => 'ti-book-2',
                'titulo'    => $etiqueta,
                'subtitulo' => $r->regulacion_nombre,
                'fragmento' => Str::limit($r->texto, self::LARGO_FRAGMENTO),
                'score'     => (float) $r->score,
                'url'       => route('regulaciones.show', $r->regulacion_id),
                'meta'      => [
                    'regulacion_id' => $r->regulacion_id,
                    'nodo_id'       => $r->id,
                    'tipo_nodo'     => $r->tipo,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 2: Regulaciones (metadata)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en la metadata de las regulaciones: nombre, resumen, objetivo,
     * palabras clave y materia. No busca en el texto completo del Markdown
     * (eso lo cubre buscarEnArticulado que busca nodo por nodo).
     *
     * Cuando $regulacionIds está presente, devuelve solo esa regulación
     * (independientemente del score FULLTEXT — se usa un score fijo de 1.0
     * para que siempre aparezca al tope del bloque de regulaciones).
     *
     * Responde preguntas como:
     *   "¿Existe un reglamento de comercio?"
     *   "¿Qué regulaciones aplican a construcción?"
     */
    private function buscarEnRegulaciones(
        string $consultaFt,
        string $consultaOriginal,
        ?array $regulacionIds = null
    ): array {
        $query = DB::table('regulaciones')
            ->selectRaw("
                id, nombre, tipo, resumen, objetivo, materia,
                ts_rank(to_tsvector('spanish', coalesce(nombre, '') || ' ' || coalesce(resumen, '') || ' ' || coalesce(objetivo, '') || ' ' || coalesce(palabras_clave, '') || ' ' || coalesce(materia, '')), to_tsquery('spanish', ?)) as score
            ", [$consultaFt])
            ->whereNull('deleted_at');

        if (!empty($regulacionIds)) {
            // Con filtro activo mostramos SIEMPRE las regulaciones elegidas,
            // sin importar el score — el usuario ya las eligió a propósito.
            $query->whereIn('id', $regulacionIds);
        } else {
            $query->whereRaw(
                "to_tsvector('spanish', coalesce(nombre, '') || ' ' || coalesce(resumen, '') || ' ' || coalesce(objetivo, '') || ' ' || coalesce(palabras_clave, '') || ' ' || coalesce(materia, '')) @@ to_tsquery('spanish', ?)",
                [$consultaFt]
            );
        }

        $resultados = $query
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
            ->get();

        return $resultados->map(function ($r) {
            $descripcion = $r->resumen ?: $r->objetivo ?: '';
            return [
                'tipo'      => 'regulacion',
                'icono'     => 'ti-scale',
                'titulo'    => $r->nombre,
                'subtitulo' => $r->tipo . ($r->materia ? ' · ' . $r->materia : ''),
                'fragmento' => Str::limit($descripcion, self::LARGO_FRAGMENTO),
                'score'     => (float) $r->score,
                'url'       => route('regulaciones.show', $r->id),
                'meta'      => [
                    'regulacion_id' => $r->id,
                    'tipo'          => $r->tipo,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 3: Trámites y servicios
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en los trámites y servicios por nombre, objetivo y población
     * objetivo. Muestra si el resultado es trámite o servicio, y su tipo.
     *
     * Cuando $regulacionIds está presente, solo devuelve trámites que tengan
     * al menos un fundamento jurídico apuntando a esa regulación (el JOIN
     * hace el filtro — si no hay fundamento vinculado, el trámite no aparece).
     *
     * Responde preguntas como:
     *   "¿Qué trámites hay para comercio?"
     *   "¿Qué servicios ofrece catastro?"
     *   "¿Cuánto tarda la licencia de funcionamiento?"
     */
    private function buscarEnTramites(
        string $consultaFt,
        string $consultaOriginal,
        ?array $regulacionIds = null
    ): array {
        $query = DB::table('tramites as t')
            ->leftJoin('tipos_tramite as tt', 't.tipo_tramite_id', '=', 'tt.id')
            ->leftJoin('dependencias as d', 't.dependencia_id', '=', 'd.id')
            ->selectRaw("
                t.id, t.nombre_oficial, t.objetivo, t.naturaleza, t.tipo_servicio,
                t.homoclave, t.estatus,
                tt.nombre as tipo_tramite_nombre,
                d.nombre as dependencia_nombre,
                t.plazo_resolucion_cantidad, t.plazo_resolucion_unidad,
                t.cbu_unitario,
                ts_rank(to_tsvector('spanish', coalesce(t.nombre_oficial, '') || ' ' || coalesce(t.objetivo, '') || ' ' || coalesce(t.poblacion_objetivo, '')), to_tsquery('spanish', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish', coalesce(t.nombre_oficial, '') || ' ' || coalesce(t.objetivo, '') || ' ' || coalesce(t.poblacion_objetivo, '')) @@ to_tsquery('spanish', ?)",
                [$consultaFt]
            )
            ->whereNull('t.deleted_at');

        if (!empty($regulacionIds)) {
            // INNER JOIN efectivo: solo pasan trámites que tienen un fundamento
            // jurídico vinculado a alguna de las regulaciones elegidas.
            $query->join(
                'fundamento_juridico as fj_filtro',
                fn ($join) => $join
                    ->on('fj_filtro.tramite_id', '=', 't.id')
                    ->whereIn('fj_filtro.regulacion_id', $regulacionIds)
            );
        }

        $resultados = $query
            ->distinct()
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
            ->get();

        return $resultados->map(function ($r) {
            $esServicio = $r->naturaleza === 'servicio';
            $tipo = $esServicio
                ? ($r->tipo_servicio ?? 'Servicio')
                : ($r->tipo_tramite_nombre ?? 'Trámite');
            $plazo = $r->plazo_resolucion_cantidad
                ? "{$r->plazo_resolucion_cantidad} {$r->plazo_resolucion_unidad}"
                : null;

            return [
                'tipo'      => 'tramite',
                'icono'     => $esServicio ? 'ti-clipboard-check' : 'ti-file-text',
                'titulo'    => $r->nombre_oficial,
                'subtitulo' => ($esServicio ? 'Servicio' : 'Trámite')
                    . ' · ' . $tipo
                    . ($r->dependencia_nombre ? ' · ' . $r->dependencia_nombre : ''),
                'fragmento' => Str::limit($r->objetivo ?? '', self::LARGO_FRAGMENTO),
                'score'     => (float) $r->score,
                'url'       => route('tramites.show', $r->id),
                'meta'      => [
                    'tramite_id'  => $r->id,
                    'naturaleza'  => $r->naturaleza,
                    'homoclave'   => $r->homoclave,
                    'estatus'     => $r->estatus,
                    'plazo'       => $plazo,
                    'cbu'         => $r->cbu_unitario,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 4: Requisitos de trámites
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en los nombres de los requisitos. Permite encontrar en qué
     * trámites se pide un documento específico.
     *
     * Cuando $regulacionIds está presente, solo devuelve requisitos de
     * trámites que tengan fundamento jurídico en esa regulación.
     *
     * Responde preguntas como:
     *   "¿Dónde me piden acta constitutiva?"
     *   "¿Qué trámite requiere CURP?"
     *   "¿Cuánto cuesta el requisito de uso de suelo?"
     */
    private function buscarEnRequisitos(
        string $consultaFt,
        string $consultaOriginal,
        ?array $regulacionIds = null
    ): array {
        $query = DB::table('requisitos as rq')
            ->join('tramites as t', 'rq.tramite_id', '=', 't.id')
            ->selectRaw("
                rq.id, rq.nombre, rq.tiempo_homologado_hrs, rq.costo_requisito,
                rq.tiene_costo, rq.costo_unidad,
                t.id as tramite_id, t.nombre_oficial as tramite_nombre, t.naturaleza,
                ts_rank(to_tsvector('spanish', coalesce(rq.nombre, '')), to_tsquery('spanish', ?)) as score
            ", [$consultaFt])
            ->whereRaw("to_tsvector('spanish', coalesce(rq.nombre, '')) @@ to_tsquery('spanish', ?)", [$consultaFt])
            ->whereNull('t.deleted_at');

        if (!empty($regulacionIds)) {
            $query->join(
                'fundamento_juridico as fj_filtro',
                fn ($join) => $join
                    ->on('fj_filtro.tramite_id', '=', 't.id')
                    ->whereIn('fj_filtro.regulacion_id', $regulacionIds)
            );
        }

        $resultados = $query
            ->distinct()
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
            ->get();

        return $resultados->map(function ($r) {
            $costo = '';
            if ($r->tiene_costo && $r->costo_requisito > 0) {
                $unidad = $r->costo_unidad ?? 'PESOS';
                $costo = $unidad === 'UMA'
                    ? number_format($r->costo_requisito, 2) . ' UMA'
                    : '$' . number_format($r->costo_requisito, 2);
            }
            $tiempo = $r->tiempo_homologado_hrs
                ? round($r->tiempo_homologado_hrs, 1) . ' hrs'
                : null;

            $nat = $r->naturaleza === 'servicio' ? 'Servicio' : 'Trámite';

            return [
                'tipo'      => 'requisito',
                'icono'     => 'ti-file-check',
                'titulo'    => $r->nombre,
                'subtitulo' => "Requisito del {$nat}: {$r->tramite_nombre}",
                'fragmento' => implode(' · ', array_filter([
                    $tiempo ? "Tiempo estimado: {$tiempo}" : null,
                    $costo  ? "Costo: {$costo}" : 'Sin costo',
                ])),
                'score'     => (float) $r->score,
                'url'       => route('tramites.show', $r->tramite_id) . '#requisitos',
                'meta'      => [
                    'requisito_id' => $r->id,
                    'tramite_id'   => $r->tramite_id,
                    'tiempo_hrs'   => $r->tiempo_homologado_hrs,
                    'costo'        => $r->costo_requisito,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 5: Fundamentos jurídicos
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en los fundamentos jurídicos que conectan trámites con
     * regulaciones. Cada fundamento tiene el nombre de la norma, el
     * artículo/fracción y un resumen.
     *
     * Cuando $regulacionIds está presente, filtra directo por regulacion_id.
     *
     * Responde preguntas como:
     *   "¿En qué ley se basa la licencia de funcionamiento?"
     *   "¿Qué artículo fundamenta el permiso de construcción?"
     */
    private function buscarEnFundamentos(
        string $consultaFt,
        string $consultaOriginal,
        ?array $regulacionIds = null
    ): array {
        $query = DB::table('fundamento_juridico as fj')
            ->join('tramites as t', 'fj.tramite_id', '=', 't.id')
            ->leftJoin('regulaciones as r', 'fj.regulacion_id', '=', 'r.id')
            ->selectRaw("
                fj.id, fj.normativa_nombre, fj.tipo_normativa,
                fj.articulo_fraccion, fj.resumen,
                fj.regulacion_id,
                t.id as tramite_id, t.nombre_oficial as tramite_nombre,
                r.nombre as regulacion_nombre,
                ts_rank(to_tsvector('spanish', coalesce(fj.normativa_nombre, '') || ' ' || coalesce(fj.articulo_fraccion, '') || ' ' || coalesce(fj.resumen, '')), to_tsquery('spanish', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish', coalesce(fj.normativa_nombre, '') || ' ' || coalesce(fj.articulo_fraccion, '') || ' ' || coalesce(fj.resumen, '')) @@ to_tsquery('spanish', ?)",
                [$consultaFt]
            )
            ->whereNull('t.deleted_at');

        if (!empty($regulacionIds)) {
            $query->whereIn('fj.regulacion_id', $regulacionIds);
        }

        $resultados = $query
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
            ->get();

        return $resultados->map(function ($r) {
            $titulo = $r->normativa_nombre ?: $r->regulacion_nombre ?: 'Fundamento jurídico';
            if ($r->articulo_fraccion) {
                $titulo .= ' — ' . $r->articulo_fraccion;
            }
            return [
                'tipo'      => 'fundamento',
                'icono'     => 'ti-gavel',
                'titulo'    => $titulo,
                'subtitulo' => "Fundamenta: {$r->tramite_nombre}",
                'fragmento' => Str::limit($r->resumen ?? '', self::LARGO_FRAGMENTO),
                'score'     => (float) $r->score,
                'url'       => $r->regulacion_id
                    ? route('regulaciones.show', $r->regulacion_id)
                    : route('tramites.show', $r->tramite_id) . '#fundamento',
                'meta'      => [
                    'fundamento_id' => $r->id,
                    'tramite_id'    => $r->tramite_id,
                    'regulacion_id' => $r->regulacion_id,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 6: Acciones de agenda (solo con filtro por regulación)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en las acciones de la agenda de simplificación y digitalización
     * cuyo trámite esté fundamentado en la regulación elegida.
     *
     * Esta fuente SOLO se consulta cuando $regulacionIds está presente para
     * no saturar los resultados generales (las acciones de agenda son datos
     * internos de gestión, no resultados relevantes para una búsqueda libre).
     *
     * Requiere: índice FULLTEXT ft_acciones_agenda_buscar en columnas
     * (descripcion, meta) — creado por la migración
     * 2026_07_06_000001_add_fulltext_to_acciones_agenda.php.
     *
     * @param  string $consultaFt      Consulta en formato FULLTEXT BOOLEAN.
     * @param  string $consultaOriginal Consulta original del usuario.
     * @param  int    $regulacionId    ID de la regulación (siempre presente en esta fuente).
     */
    /**
     * Wrapper seguro: si el índice FULLTEXT de acciones_agenda no existe
     * todavía (migración pendiente), devuelve un array vacío en vez de
     * reventar la búsqueda entera.
     */
    private function buscarEnAgendaSeguro(
        string $consultaFt,
        string $consultaOriginal,
        array $regulacionIds
    ): array {
        try {
            return $this->buscarEnAgenda($consultaFt, $consultaOriginal, $regulacionIds);
        } catch (\Illuminate\Database\QueryException $e) {
            // Error 1191: "Can't find FULLTEXT index matching the column list"
            // La migración aún no se ha corrido — omitimos esta fuente.
            if (str_contains($e->getMessage(), '1191')) {
                return [];
            }
            throw $e; // Cualquier otro error sí se propaga.
        }
    }

    private function buscarEnAgenda(
        string $consultaFt,
        string $consultaOriginal,
        array $regulacionIds
    ): array {
        $resultados = DB::table('acciones_agenda as aa')
            ->join('tramites as t', 'aa.tramite_id', '=', 't.id')
            ->join(
                'fundamento_juridico as fj',
                fn ($join) => $join
                    ->on('fj.tramite_id', '=', 't.id')
                    ->whereIn('fj.regulacion_id', $regulacionIds)
            )
            ->leftJoin('dependencias as d', 'aa.dependencia_id', '=', 'd.id')
            ->selectRaw("
                aa.id, aa.tipo, aa.descripcion, aa.meta, aa.estatus, aa.folio,
                aa.fecha_compromiso,
                t.id as tramite_id, t.nombre_oficial as tramite_nombre,
                d.nombre as dependencia_nombre,
                ts_rank(to_tsvector('spanish', coalesce(aa.descripcion, '') || ' ' || coalesce(aa.meta, '')), to_tsquery('spanish', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish', coalesce(aa.descripcion, '') || ' ' || coalesce(aa.meta, '')) @@ to_tsquery('spanish', ?)",
                [$consultaFt]
            )
            ->whereNull('aa.deleted_at')
            ->whereNull('t.deleted_at')
            ->distinct()
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
            ->get();

        return $resultados->map(function ($r) {
            $tipoLabel = $r->tipo === 'simplificacion' ? 'Simplificación' : 'Digitalización';
            $icono     = $r->tipo === 'simplificacion' ? 'ti-tools' : 'ti-device-laptop';
            $subtitulo = "Acción de agenda · {$tipoLabel}"
                . ($r->dependencia_nombre ? ' · ' . $r->dependencia_nombre : '');

            $fragmento = Str::limit($r->descripcion ?? '', self::LARGO_FRAGMENTO);
            if ($r->meta) {
                $fragmento .= $fragmento ? ' — Meta: ' . Str::limit($r->meta, 100) : Str::limit($r->meta, self::LARGO_FRAGMENTO);
            }

            return [
                'tipo'      => 'agenda',
                'icono'     => $icono,
                'titulo'    => $r->folio ?? "Acción de agenda #{$r->id}",
                'subtitulo' => $subtitulo,
                'fragmento' => $fragmento,
                'score'     => (float) $r->score,
                'url'       => route('tramites.show', $r->tramite_id),
                'meta'      => [
                    'accion_id'          => $r->id,
                    'tramite_id'         => $r->tramite_id,
                    'tramite_nombre'     => $r->tramite_nombre,
                    'tipo'               => $r->tipo,
                    'estatus'            => $r->estatus,
                    'fecha_compromiso'   => $r->fecha_compromiso,
                ],
            ];
        })->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Genera la etiqueta legible de un nodo del articulado.
     * Ej: "Artículo 15", "Fracción III", "Título I".
     */
    private function etiquetaNodo(string $tipo, ?string $numero): string
    {
        $etiquetas = [
            'titulo'   => 'Título',
            'capitulo' => 'Capítulo',
            'seccion'  => 'Sección',
            'articulo' => 'Artículo',
            'fraccion' => 'Fracción',
            'inciso'   => 'Inciso',
            'parrafo'  => 'Párrafo',
        ];

        $nombre = $etiquetas[$tipo] ?? ucfirst($tipo);
        return $numero ? "{$nombre} {$numero}" : $nombre;
    }
}
