<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Buscador global de PUNTA.
 *
 * El ranking y el enrutamiento se deciden con reglas explícitas y con el scoring
 * de búsqueda de texto completo de PostgreSQL (to_tsvector / ts_rank), no con IA.
 * El asistente redacta a partir de lo que este servicio encuentra, pero no decide
 * qué se encuentra.
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
 */
class BuscadorService
{
    public function __construct(
        private SearchQueryNormalizer $normalizador,
        private LegalDictionaryService $diccionario,
        private FeaturedAnswerService $respuestaDestacada,
        private AsistenteRespuestaService $asistente,
        private SearchIntentDetector $detector,
        private ReformuladorConsultaService $reformulador,
        private TesauroService $tesauro,
        private VocabularioCorpusService $vocabulario,
    ) {}

    /**
     * ¿Incluir leyes de otra jurisdicción en esta búsqueda? Lo fija buscar() al
     * empezar, y filtrarPorJurisdiccion() lo consulta. Por defecto NO: el buscador
     * del ciudadano filtra por jurisdicción salvo que se pida "ver todo".
     *
     * Es estado POR BÚSQUEDA (buscar() lo reasigna en cada llamada), no una
     * preferencia persistente. Se guarda como propiedad, en vez de enhebrarlo por
     * todas las firmas de las fuentes, porque el único punto que lo necesita es
     * filtrarPorJurisdiccion(), el embudo del filtro.
     */
    private bool $incluirOtrasJurisdicciones = false;

    /**
     * Configuración de búsqueda de texto de PostgreSQL. Es 'spanish_unaccent', no
     * 'spanish', y la diferencia importa.
     *
     * La consulta del ciudadano llega sin acentos (SearchQueryNormalizer los quita),
     * y el stemmer español no reconoce "habitacion" sin tilde: la deja entera, en vez
     * de reducirla a la raíz 'habit' con la que está indexado el texto. Resultado:
     * cero coincidencias para una palabra que sí está en la ley. 'spanish_unaccent'
     * quita los acentos antes de aplicar el stemmer, de los dos lados.
     *
     * Es constante porque el archivo tiene seis consultas de texto completo y basta
     * con que una se quede en 'spanish' para que esa fuente deje de encontrar, en
     * silencio. Debe coincidir además con la del índice GIN de la migración: si no,
     * PostgreSQL no puede usar el índice y cada búsqueda recalcula el tsvector de
     * miles de nodos —seguiría funcionando, pero lentísimo y sin avisar—.
     */
    private const CONFIG_BUSQUEDA = 'spanish_unaccent';

    /**
     * Por encima de este porcentaje del articulado, una palabra deja de distinguir.
     *
     * En la Ley de Hacienda de La Paz, "publicos" aparece en el 11,7% de los nodos
     * (vía pública, orden público, notarios públicos...) y exigirla no acota nada,
     * mientras que "espectaculos" está en el 2,9% y sí señala un tema. El 5% deja
     * fuera la primera y conserva la segunda. Es un número elegido con criterio, no
     * medido: afinarlo debería hacerse mirando las regulaciones reales del municipio.
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

    /**
     * Cuántos resultados aporta cada fuente.
     *
     * El articulado lleva más que el resto porque una sola ley puede tener decenas de
     * artículos sobre el mismo tema, y el único que da la cifra suele ser el más
     * corto —y ts_rank premia la repetición, así que los largos lo desplazan—. Con un
     * tope de 10 ese artículo quedaba fuera y el asistente respondía que no encontraba
     * cómo se calcula. Treinta y no más, porque el asistente solo lee las 20 mejores
     * (punta.asistente.max_fuentes) y cada consulta cuesta tiempo.
     */
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
    /**
     * Enseña las tripas de una consulta: qué palabras sobreviven y qué tsquery sale.
     *
     * ══════════════════════════════════════════════════════════════════════
     * POR QUÉ ESTE MÉTODO ES PÚBLICO
     * ══════════════════════════════════════════════════════════════════════
     *
     * El buscador toma cuatro decisiones invisibles antes de tocar la base de datos:
     *
     *   1. El normalizador quita las palabras que describen la FORMA de la pregunta.
     *   2. El filtro tira las que no existen y las demasiado comunes.
     *   3. El tesauro traduce las que quedan al vocabulario de la ley.
     *   4. Y de ahí sale un tsquery que nadie ve nunca.
     *
     * Cada paso es correcto por separado. Los fallos aparecen en las COSTURAS entre ellos —y las
     * costuras no se ven—. Un caso real: el filtro tiraba "casa" por demasiado común (el stemmer
     * la confunde con "caso"), el AND desaparecía, y la búsqueda devolvía 88 artículos en vez de
     * cero. No daba ningún error. Parecía que funcionaba.
     *
     * ── Y por qué NO se hace desde fuera ──
     *
     * El comando buscador:diagnosticar lo intentó: copió estas reglas en su propio código para
     * poder enseñarlas.
     *
     * Y en cuanto una regla cambió aquí, el comando siguió razonando con la vieja. Decía "casa SE
     * TIRA" cuando el buscador ya la conservaba. Una herramienta de diagnóstico desincronizada del
     * sistema no diagnostica: MIENTE CON AUTORIDAD, y manda a quien la lee a arreglar un bug que
     * ya no existe.
     *
     * Por eso las reglas viven en UN SITIO, y este método las abre en canal. Quien quiera saber
     * qué decide el buscador, que se lo pregunte AL BUSCADOR.
     *
     * @return array{palabras: array<string>, conservadas: array<string>, tsquery: string}
     */
    public function diagnosticar(string $consulta): array
    {
        $normalizado = $this->normalizador->normalizar(trim($consulta));

        $palabras    = $normalizado['palabras'];
        $conservadas = $this->descartarPalabrasQueNoDistinguen($palabras);

        return [
            'palabras'    => $palabras,
            'conservadas' => $conservadas,
            'tsquery'     => $this->prepararConsultaFulltext(
                $conservadas,
                $normalizado['consulta_normalizada']
            ),
        ];
    }

    public function buscar(
        string $consulta,
        bool $forzarCompleto = false,
        ?array $regulacionIds = null,
        ?array $tipos = null,
        bool $incluirOtrasJurisdicciones = false
    ): array {
        // "Ver todo": si es true, filtrarPorJurisdiccion() no filtra. El marcador
        // fuera_de_jurisdiccion se calcula igual, así que lo que entre viene rotulado.
        $this->incluirOtrasJurisdicciones = $incluirOtrasJurisdicciones;

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
        // AND sobre los sustantivos que discriminan, EXPANDIDOS con el tesauro.
        // Ver prepararConsultaFulltext(), que explica el porqué de los paréntesis.
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

        // Búsqueda completa: las fuentes según el filtro de tipos, o las cinco de
        // siempre si no hay filtro. Se ejecuta cuando no se reconoció un concepto
        // enfocable, cuando la búsqueda enfocada no encontró nada, o cuando el
        // usuario pidió ver todos los resultados.
        //
        // La consulta se arma con AND sobre los SUSTANTIVOS del tema. Lo que decide
        // si una palabra sirve no es su frecuencia sino su categoría gramatical: en
        // una ley el sustantivo coincide con el del ciudadano ("espectáculos" se
        // escribe igual en ambos lados), mientras que el verbo no —el ciudadano dice
        // "cómo se calculan" y la ley dice "pagarán"—. Exigir los verbos deja la
        // búsqueda en cero; soltarlos todos con OR mete cualquier nodo que comparta
        // una palabra común. SearchQueryNormalizer se lleva verbos y palabras vacías,
        // así que lo que llega aquí ya son los sustantivos.
        $resultados = $this->buscarEnTodasLasFuentes($consultaFt, $consulta, $incluir);

        // ══════════════════════════════════════════════════════════════════════
        // RESCATE BARATO: EL AND FUE DEMASIADO ESTRICTO
        // ══════════════════════════════════════════════════════════════════════
        //
        // Se hace AQUÍ, sobre la búsqueda base, ANTES de que el asistente opine. Y a propósito:
        //
        // El asistente a veces "responde" con un artículo equivocado y confianza alta. Ejemplo
        // real: "multa por obstruir la banqueta" → el buscador trae el art. 154 (multas de
        // tránsito) y el asistente redacta "3 UMA" tan tranquilo. Si el rescate dependiera de
        // "¿el asistente respondió?", NUNCA se dispararía: el asistente cree que respondió.
        //
        // Por eso el disparo mira la BÚSQUEDA, no la respuesta: si el mejor resultado apenas cubre
        // los conceptos que preguntó el ciudadano, es que el AND descartó el artículo bueno
        // (exigió una palabra que ese artículo no contiene, como "multa" en un artículo que
        // describe la conducta). Se suelta el brazo más común y se reintenta. Ver
        // reintentarSoltandoUnBrazo().
        $rescatadosBrazo = $this->reintentarSoltandoUnBrazo($consulta, $consultaNormalizada, $resultados, $incluir);

        if ($rescatadosBrazo->isNotEmpty()) {
            $resultados = $resultados
                ->merge($rescatadosBrazo)
                ->unique(fn ($r) => ($r['meta']['nodo_id'] ?? null) ?? $r['url'] ?? spl_object_id((object) $r))
                ->values();
        }

        // La reformulación con IA ya no se decide aquí, sino en responder(): "cero
        // resultados" no es la única forma de fallar —treinta resultados irrelevantes
        // fallan igual— y allí se mira si el asistente pudo usar lo encontrado.


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
     * Descarta las palabras que aparecen en tantos artículos que no acotan nada.
     *
     * Hace falta porque el artículo que responde suele no repetir el título de su
     * capítulo: el que fija el impuesto sobre espectáculos dice "el espectáculo que
     * corresponda", en singular y sin "públicos", porque ya está dentro del capítulo
     * que lo dice. Exigir todas las palabras del ciudadano dejaría fuera justo ese
     * artículo.
     *
     * El corte lo pone UMBRAL_PALABRA_COMUN, con MINIMO_PARA_DESCARTAR como suelo
     * para que un corpus pequeño no dispare el filtro.
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

        // El umbral es un porcentaje, y un porcentaje sobre un corpus pequeño no
        // significa nada: con tres nodos el 5% es cero y cualquier palabra repetida
        // se consideraría común. De ahí el suelo mínimo.
        $umbral = max(
            self::MINIMO_PARA_DESCARTAR,
            (int) ceil($totalNodos * self::UMBRAL_PALABRA_COMUN)
        );

        $utiles = [];

        foreach ($palabras as $palabra) {
            $frecuencia = $this->frecuenciaEnArticulado($palabra);

            // Excepción del tesauro: la regla de arriba descarta las palabras con
            // frecuencia cero, porque exigir en un AND algo que no aparece en ningún
            // artículo garantiza cero resultados. Pero el tesauro existe justo para
            // esas palabras: el ciudadano dice "comprar" y la ley dice "adquisición".
            // Si se descartara antes de traducirla, el tesauro no llegaría a actuar.
            if ($this->tesauro->tieneTraduccionUtil($palabra)) {
                $utiles[] = $palabra;
                continue;
            }

            // Se conserva la palabra solo si cumple LAS DOS condiciones:
            //
            //   frecuencia > 0        → existe en el corpus. Una palabra que no aparece en ningún
            //                           artículo garantiza cero resultados si se exige en un AND
            //                           —salvo que el tesauro sepa traducirla, que es la excepción
            //                           de arriba. "Calculan" parecía cumplir esto (sale en 7
            //                           nodos), pero solo por el stemmer: PostgreSQL la iguala a
            //                           "cálculo", y esos 7 nodos hablan de recargos y notarios.
            //                           Por eso los verbos se filtran ANTES, en
            //                           SearchQueryNormalizer.
            //
            //   frecuencia <= umbral  → no es tan común que deje de distinguir. "Públicos" sale
            //                           en el 5,7% del articulado: exigirla no acota nada. Y aquí
            //                           el tesauro NO rescata a nadie: una palabra demasiado común
            //                           es inútil en un AND, la traduzca quien la traduzca.
            if ($frecuencia > 0 && $frecuencia <= $umbral) {
                $utiles[] = $palabra;
            }
        }

        // Si el filtro se lo llevó TODO, se devuelven las originales. Más vale un AND con ruido
        // que una consulta vacía: al menos el asistente tendrá algo que leer.
        return $utiles !== [] ? $utiles : $palabras;
    }

    /**
     * En cuántos nodos del articulado aparece esta palabra.
     *
     * El cómo vive ahora en VocabularioCorpusService, porque el TESAURO necesita lo mismo: para
     * decidir qué sinónimos existen de verdad en las regulaciones cargadas.
     *
     * Ahí está también el comentario que explica por qué la frecuencia se cuenta SOLO sobre el
     * texto y no sobre el contexto — la decisión que rompió el buscador cuando se hizo al revés.
     * Léelo antes de tocar nada de esto.
     */
    private function frecuenciaEnArticulado(string $palabra): int
    {
        return $this->vocabulario->frecuencia($palabra);
    }

    /**
     * Le pide a la IA otras palabras, y busca con ellas.
     *
     * Solo se llama cuando la búsqueda normal se quedó a CERO. Nunca antes.
     *
     * ── Qué se hace con los resultados ──
     *
     * Se juntan los de todas las reformulaciones, se quitan los duplicados (una misma fracción
     * puede salir por dos consultas distintas) y se ordenan por score.
     *
     * ── Y qué pasa si la IA está apagada o falla ──
     *
     * reformular() devuelve un array vacío, este método devuelve una colección vacía, y el
     * buscador enseña "no se encontraron resultados". Exactamente como se comportaba ayer.
     *
     * Este servicio es una MEJORA, nunca un requisito.
     */
    private function buscarConOtrasPalabras(string $consulta, callable $incluir): Collection
    {
        $alternativas = $this->reformulador->reformular($consulta);

        if ($alternativas === []) {
            // ── LA RED SE RINDE. Y DEJA CONSTANCIA, a propósito. ──
            //
            // Esta es la última línea de defensa: el asistente ya no pudo responder, y esto era el
            // último intento. Si se rinde EN SILENCIO, nadie se entera de que un ciudadano se fue
            // sin respuesta — y no hay forma de saber si fue porque la IA está apagada, porque la
            // llamada falló, o porque la IA no supo proponer nada.
            //
            // Las tres se ven igual desde fuera: una lista vacía. El log las separa. Sin esto, el
            // caso "multa por extensión de la vía pública" parecía un misterio; con esto, el log
            // dice si el reformulador ni se intentó (IA apagada) o lo intentó y no encontró
            // palabras mejores.
            Log::info('Red de seguridad: el reformulador no aportó consultas alternativas.', [
                'consulta' => $consulta,
            ]);

            return collect();
        }

        $resultados = collect();

        foreach ($alternativas as $alternativa) {
            $normalizado = $this->normalizador->normalizar($alternativa);

            $tsquery = $this->prepararConsultaFulltext(
                $this->descartarPalabrasQueNoDistinguen($normalizado['palabras']),
                $alternativa
            );

            $resultados = $resultados->merge(
                $this->buscarEnTodasLasFuentes($tsquery, $alternativa, $incluir)
            );
        }

        // Una misma fracción puede aparecer por dos consultas distintas. Se deduplica por el id
        // del nodo (o por la url, para las fuentes que no son del articulado).
        return $resultados
            ->unique(fn ($r) => ($r['meta']['nodo_id'] ?? null) ?? $r['url'] ?? spl_object_id((object) $r))
            ->values();
    }

    /**
     * Si el AND fue demasiado estricto, suelta el brazo más común y reintenta.
     *
     * Una pregunta larga genera un AND de muchos brazos, y el artículo que responde
     * rara vez contiene todas las palabras: la ley reparte los términos del tema por
     * su estructura. Soltar el brazo más frecuente —el que menos acota— recupera el
     * artículo sin abrir la búsqueda a todo.
     */
    private function reintentarSoltandoUnBrazo(
        string $consulta,
        string $consultaNormalizada,
        Collection $resultados,
        callable $incluir
    ): Collection {
        $palabras = $this->descartarPalabrasQueNoDistinguen(
            $this->normalizador->normalizar($consultaNormalizada)['palabras']
        );

        // Hace falta un mínimo de 3 conceptos para que soltar uno deje al menos dos: un AND de
        // dos brazos sigue acotando; uno de un solo brazo se acerca al OR peligroso.
        if (count($palabras) < 3) {
            return collect();
        }

        // ¿Algún ARTÍCULO entre los resultados ya cubre bien la pregunta? Entonces no hay nada
        // que rescatar.
        //
        // Ojo: se miran solo los resultados de tipo artículo, NO la ficha de la regulación. La
        // ficha ("Bando de Policía... reglamento de convivencia...") suele salir primera por score
        // y su descripción menciona muchos temas de refilón —cubriría conceptos por casualidad y
        // abortaría el rescate—. Lo que importa es si un ARTÍCULO concreto responde, no si la ley
        // en general habla del tema.
        $mejorCobertura = 0;

        foreach ($resultados as $r) {
            if (($r['tipo'] ?? '') !== 'articulo') {
                continue; // la ficha de la regulación no cuenta
            }

            $cobertura = $this->coberturaDeConceptos($r, $palabras);

            if ($cobertura > $mejorCobertura) {
                $mejorCobertura = $cobertura;
            }
        }

        // Si el mejor artículo cubre 2 o más conceptos, la búsqueda base ya acertó: no se rescata.
        if ($mejorCobertura > 1) {
            return collect();
        }

        // El brazo a soltar: el más común (el que aparece en más artículos del corpus). Es el que
        // menos acota y, en preguntas con sanción, suele ser la palabra-sanción. Se decide por
        // DATO (frecuencia en el corpus), no por una lista fija de palabras.
        $brazoASoltar = $this->palabraMasComun($palabras);

        if ($brazoASoltar === null) {
            return collect();
        }

        $palabrasReducidas = array_values(array_filter(
            $palabras,
            fn ($p) => $p !== $brazoASoltar
        ));

        if ($palabrasReducidas === []) {
            return collect();
        }

        // Se busca de nuevo con un brazo menos, manteniendo el AND sobre el resto.
        $tsquery = $this->prepararConsultaFulltext($palabrasReducidas, $consulta);

        Log::info('Rescate por soltar un brazo del AND.', [
            'consulta'     => $consulta,
            'brazo_soltado' => $brazoASoltar,
            'quedan'       => $palabrasReducidas,
        ]);

        return $this->buscarEnTodasLasFuentes($tsquery, $consulta, $incluir);
    }

    /**
     * Cuántos de los conceptos-tema de la pregunta aparecen en el texto de un resultado.
     *
     * Se mide sobre el texto COMPLETO del nodo (texto_completo), no sobre el fragmento recortado:
     * un concepto que aparece más allá del carácter 250 contaría como ausente si midiéramos sobre
     * el recorte, y dispararía rescates de más.
     *
     * La comparación es sin acentos y por raíz aproximada (el concepto "obstruir" cubre
     * "obstáculos"/"obstrucción" por prefijo de 4+ letras), para no fallar por una conjugación.
     */
    private function coberturaDeConceptos(array $resultado, array $conceptos): int
    {
        $texto = Str::ascii(mb_strtolower(
            ($resultado['texto_completo'] ?? $resultado['fragmento'] ?? '') . ' ' .
            ($resultado['titulo'] ?? '')
        ));

        $cubiertos = 0;

        foreach ($conceptos as $concepto) {
            $c = Str::ascii(mb_strtolower($concepto));

            // Prefijo de hasta 5 letras: "obstruir"→"obstr" cubre "obstáculos"; "banqueta"→"banqu".
            // Suficiente para tolerar conjugaciones y plurales sin casar palabras no relacionadas.
            $raiz = mb_substr($c, 0, min(5, mb_strlen($c)));

            if ($raiz !== '' && str_contains($texto, $raiz)) {
                $cubiertos++;
            }
        }

        return $cubiertos;
    }

    /**
     * La palabra que aparece en MÁS artículos del corpus (la menos específica). Es el brazo que
     * menos acota, y el candidato a soltar. Se decide por frecuencia real en el corpus, un dato,
     * no por una lista fija de palabras.
     */
    private function palabraMasComun(array $palabras): ?string
    {
        $masComun     = null;
        $maxArticulos = -1;

        foreach ($palabras as $palabra) {
            // frecuencia() cuenta en cuántos nodos del corpus aparece la palabra: es exactamente
            // "cómo de común es". La más común es la que menos acota, la candidata a soltar.
            $enCuantos = $this->vocabulario->frecuencia($palabra);

            if ($enCuantos > $maxArticulos) {
                $maxArticulos = $enCuantos;
                $masComun     = $palabra;
            }
        }

        return $masComun;
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
        $intencion  = $this->detector->detectar($consultaNormalizada);

        if ($destacada === null && $resultados->isNotEmpty()) {
            $destacada = $this->asistente->construir($consulta, $resultados, $intencion);
        }

        // ══════════════════════════════════════════════════════════════════════
        // SI EL ASISTENTE NO PUDO RESPONDER, SE REFORMULA Y SE BUSCA OTRA VEZ
        // ══════════════════════════════════════════════════════════════════════
        //
        // ── El bug que esto arregla ──
        //
        // La condición anterior era:
        //
        //     if ($resultados->isEmpty()) { reformular(); }
        //
        // Es decir: se reformulaba SOLO si no había NINGÚN resultado.
        //
        // Y con "cuánto se paga por comprar una casa" el buscador devolvía TREINTA resultados...
        // todos basura. Capítulos, definiciones, aprovechamientos. Ninguno respondía.
        //
        // El reformulador NUNCA SE LLAMABA. Porque "encontré algo" no es lo mismo que "encontré
        // lo correcto", y mi condición confundía las dos cosas.
        //
        // Es el MISMO error que ya cometí con la cascada AND→OR: el AND encontraba el artículo
        // equivocado, así que el OR nunca se disparaba. Lo repetí.
        //
        // ── La condición correcta ──
        //
        // No es "¿hay resultados?". Es "¿ALGUIEN PUDO RESPONDER CON ELLOS?".
        //
        // Y quien decide eso es el asistente, que sabe leer. Si con treinta artículos delante no
        // encuentra la respuesta, es que no está entre ellos — y hay que buscar de otra forma.
        //
        //     EL QUE SABE LEER DECIDE SI LO QUE HAY SIRVE.
        //
        // Encaja con todo el diseño: el buscador no tiene que acertar, solo no perderse la
        // respuesta; filtrar es trabajo de quien sabe leer. Y ahora, decidir si hay que volver a
        // buscar, también.
        //
        // ── El coste ──
        //
        // Una llamada más a la IA (el reformulador) y una ronda más de consultas. Solo cuando el
        // asistente no pudo responder, que es el caso raro.
        //
        // Y hay una asimetría que lo justifica: una búsqueda lenta que encuentra la respuesta
        // vale infinitamente más que una rápida que no encuentra nada. El ciudadano no vuelve a
        // preguntar: se va, y llama por teléfono.
        if ($this->elAsistenteNoRespondio($destacada) && $modo === 'completo') {
            $rescatados = $this->buscarConOtrasPalabras($consulta, fn () => true);

            if ($rescatados->isNotEmpty()) {
                // Se juntan con los originales, sin duplicar: puede que entre los treinta hubiera
                // algo útil que el asistente no supo aprovechar solo.
                $resultados = $resultados
                    ->merge($rescatados)
                    ->unique(fn ($r) => ($r['meta']['nodo_id'] ?? null) ?? $r['url'] ?? spl_object_id((object) $r))
                    ->sortByDesc('score')
                    ->values();

                $destacada = $this->asistente->construir($consulta, $resultados, $intencion);
            }
        }

        return [
            'resultados'          => $resultados,
            'respuesta_destacada' => $destacada,
            'modo'                => $modo,
        ];
    }

    /**
     * ¿El asistente NO pudo responder la pregunta?
     *
     * ══════════════════════════════════════════════════════════════════════
     * UNA RENDICIÓN CONFESADA NO ES UN NULL. Y ESO NOS COSTÓ UN CASO ENTERO.
     * ══════════════════════════════════════════════════════════════════════
     *
     * La condición anterior era `$destacada === null`. Y parecía suficiente, porque el asistente
     * devuelve null cuando no tiene material con el que trabajar.
     *
     * Pero hay una tercera posibilidad que no es ni "respondí" ni "null":
     *
     *     confianza = 'generada'     → el modelo RESPONDIÓ la pregunta.
     *     confianza = 'relacionada'  → NO la respondió. Contó qué sí dicen las fuentes del tema,
     *                                  y avisó de que no era lo que se preguntaba.
     *
     * Eso último es una RENDICIÓN. Es el asistente diciendo, con todas sus letras:
     *
     *     "No encontré una respuesta clara a tu pregunta. Encontré 2 documentos relacionados,
     *      pero ninguno responde exactamente lo que preguntas."
     *
     * Y no es null. Así que la red de seguridad no se desplegaba: el sistema trataba una confesión
     * de fracaso como si fuera un éxito.
     *
     * ── El caso real ──
     *
     * "cuánto cuesta la multa por extensión de la vía pública".
     *
     * La ley SÍ lo responde: el artículo 88, fracción VII TER, "Extensión en la vía pública", con
     * su cuota. Pero la ley no lo llama MULTA: lo llama DERECHO. No te sanciona por extender tu
     * negocio a la banqueta — te cobra.
     *
     * Como el ciudadano dijo "multa", el AND exigía (multa | sancion | infraccion | recargo), y la
     * fracción VII TER no dice ninguna de esas palabras. Se quedó fuera. Y salieron dos artículos
     * de relleno sobre pavimentación.
     *
     * ── Por qué esto NO se arregla en el tesauro ──
     *
     * La tentación es traducir multa → derecho. Y es VENENO: un ciudadano que pregunte "¿qué multa
     * me ponen por vender sin licencia?" recibiría cuotas y tarifas. Una SANCIÓN y un COBRO no son
     * lo mismo, y confundirlos en un buscador municipal es grave.
     *
     * Cuando el ciudadano usa una palabra que significa OTRA COSA, no hay tabla que lo salve. Hace
     * falta alguien que entienda la pregunta. Para eso está el reformulador.
     *
     *     EL QUE SABE LEER DECIDE SI LO QUE HAY SIRVE.
     *
     * ── El coste ──
     *
     * Ahora, también cuando el
     * asistente se rinde — que es más frecuente. Una llamada más, de unos segundos, en el caso en
     * que el ciudadano se iba a ir con las manos vacías.
     *
     * La asimetría lo justifica: una búsqueda lenta que encuentra la respuesta vale infinitamente
     * más que una rápida que no encuentra nada. El ciudadano no vuelve a preguntar: se va, y llama
     * por teléfono.
     */
    public function elAsistenteNoRespondio(?array $destacada): bool
    {
        if ($destacada === null) {
            return true;
        }

        // 'relacionada' es el propio asistente diciendo "esto NO responde lo que preguntaste".
        // Cualquier otra confianza ('generada', o una definición curada del diccionario jurídico)
        // significa que alguien SÍ respondió.
        return ($destacada['confianza'] ?? null) === 'relacionada';
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
     * Se filtran palabras de menos de 3 caracteres porque la búsqueda de texto
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
        // ══════════════════════════════════════════════════════════════════════
        // EL TESAURO: OR DENTRO DE CADA PALABRA, AND ENTRE ELLAS
        // ══════════════════════════════════════════════════════════════════════
        //
        // Un ciudadano pregunta "cuánto se paga por comprar una casa". Quedan ['comprar', 'casa'].
        //
        // Y el artículo 38 responde exactamente eso:
        //
        //     "El impuesto sobre ADQUISICIÓN de BIENES INMUEBLES será el que resulte de aplicar
        //      al valor del inmueble la tasa del 3%."
        //
        // Pero NO DICE "comprar". NO DICE "casa". El AND lo descarta.
        //
        // ── Lo que hace el tesauro ──
        //
        //     comprar  →  adquisicion, adquirir, enajenacion
        //     casa     →  casa habitacion, predio urbano, bien inmueble
        //
        // Y el tsquery pasa de:
        //
        //     comprar:* & casa:*                                     ← cero resultados
        //
        // A:
        //
        //     (comprar:* | adquisicion:* | adquirir:*) & (casa:* | predio:* | inmueble:*)
        //
        // ── FÍJATE EN LOS PARÉNTESIS. SON TODO. ──
        //
        // El OR va DENTRO de cada palabra. El AND ENTRE palabras SE MANTIENE.
        //
        // El artículo tiene que hablar de comprar (o adquirir, o enajenar) Y de casas (o predios,
        // o inmuebles). No basta con que mencione una de las seis palabras.
        //
        // Si se pusiera OR entre todo —(comprar | adquisicion | casa | predio)— buscar "comprar
        // casa" devolvería CUALQUIER artículo que mencione "predio". Y en una Ley de Hacienda eso
        // son cientos.
        //
        // Ya cometimos ese error una vez, y devolvía guías de traslado de animales al rastro.
        //
        // SE RELAJA CADA PALABRA. NO SE RELAJA LA CONSULTA.
        $expandidas = $this->tesauro->expandir($palabras);

        $terminos = [];

        foreach ($expandidas as $alternativas) {
            $variantes = [];

            foreach ($alternativas as $palabra) {
                // Se limpian los operadores de tsquery (& | ! : * paréntesis, comillas) para que
                // una palabra escrita por el usuario no rompa la consulta.
                //
                // Y los espacios: un término del tesauro puede ser compuesto ("casa habitacion").
                // Se parte en palabras sueltas, porque un tsquery no admite frases con ':*'.
                foreach (preg_split('/\s+/u', $palabra) as $trozo) {
                    $limpia = preg_replace('/[&|!():*\'"]/', '', trim($trozo));

                    if (mb_strlen($limpia) >= 3) {
                        // ':*' busca por prefijo: "semifijo:*" también encuentra "semifijos".
                        $variantes[] = $limpia . ':*';
                    }
                }
            }

            $variantes = array_values(array_unique($variantes));

            if ($variantes === []) {
                continue;
            }

            // Una sola variante no necesita paréntesis. Varias, sí: sin ellos, el OR se mezclaría
            // con el AND de fuera y PostgreSQL lo interpretaría al revés de lo que queremos.
            $terminos[] = count($variantes) === 1
                ? $variantes[0]
                : '(' . implode(' | ', $variantes) . ')';
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
        // Y eso acababa dentro de to_tsquery('spanish_unaccent', que es un).
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
     * Restringe una consulta a las regulaciones de la jurisdicción de esta
     * instalación: federal (aplica a todo el país), estatal del estado
     * configurado, o municipal del municipio configurado.
     *
     * Una regulación SIN clasificar (ambito NULL) queda EXCLUIDA: no está confirmada
     * como de esta jurisdicción, y desde que toda ley nueva nace con ámbito por
     * defecto (ver Regulacion::booted), un NULL solo puede ser un dato faltante que
     * corregir, no una ley legítima que ocultar.
     *
     * Se aplica solo a las fuentes que surten LEYES (articulado, regulaciones,
     * fundamentos). Trámites y requisitos son procedimientos del propio
     * municipio y no llevan jurisdicción.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $r  Alias de la tabla regulaciones en la consulta ('r' o 'regulaciones').
     */
    private function filtrarPorJurisdiccion($query, string $r): void
    {
        // "Ver todo": el ciudadano pidió incluir otras jurisdicciones. No se filtra
        // (pero el marcador fuera_de_jurisdiccion se sigue calculando, para rotular).
        if ($this->incluirOtrasJurisdicciones) {
            return;
        }

        $estado    = config('punta.jurisdiccion.estado');
        $municipio = config('punta.jurisdiccion.municipio');

        $query->where(function ($q) use ($r, $estado, $municipio) {
            $q->where("{$r}.ambito", 'federal')                // federal → aplica en todo el país
              ->orWhere(function ($q) use ($r, $estado) {       // estatal del estado configurado
                  $q->where("{$r}.ambito", 'estatal')
                    ->where("{$r}.estado", $estado);
              })
              ->orWhere(function ($q) use ($r, $estado, $municipio) { // municipal del municipio configurado
                  $q->where("{$r}.ambito", 'municipal')
                    ->where("{$r}.estado", $estado)
                    ->where("{$r}.municipio", $municipio);
              });
        });
    }

    /**
     * ¿Esta ley es de OTRA jurisdicción (no aplica aquí)? Es la contraparte del
     * filtro: la misma regla, pero para MARCAR un resultado en vez de excluirlo.
     *
     * Se usa cuando el filtro está apagado ("ver todo"): el resultado llega, pero
     * marcado, para que el ciudadano y el asistente sepan que es de otro lado.
     *
     * NULL (sin clasificar) SÍ se marca: no está confirmada como de esta
     * jurisdicción, igual que el filtro ahora la excluye. Con "ver todo" aparece,
     * pero señalada para que se verifique.
     *
     * OJO: esta regla y la de filtrarPorJurisdiccion() son la MISMA, expresada dos
     * veces —una en SQL (para excluir) y otra en PHP (para marcar)— porque una
     * corre en la base y la otra sobre la fila ya traída. Si cambia una, cambia la
     * otra.
     */
    private function esDeOtraJurisdiccion(?string $ambito, ?string $estado, ?string $municipio): bool
    {
        if ($ambito === 'federal') {
            return false;
        }

        $estadoLocal    = config('punta.jurisdiccion.estado');
        $municipioLocal = config('punta.jurisdiccion.municipio');

        if ($ambito === 'estatal') {
            return $estado !== $estadoLocal;
        }

        if ($ambito === 'municipal') {
            return $estado !== $estadoLocal || $municipio !== $municipioLocal;
        }

        return true; // ámbito desconocido: por seguridad, se marca.
    }

    /**
     * Busca en el articulado: artículos, fracciones e incisos.
     *
     * Tres decisiones de esta consulta que no son obvias:
     *
     * 1. Se busca sobre TEXTO + CONTEXTO. En una ley bien redactada un artículo no
     *    repite el título de su capítulo —sería redundante—, así que mirar solo su
     *    texto lo vuelve invisible para quien pregunta por el tema: el artículo del
     *    impuesto predial nunca dice "patrimonio", lo dice el capítulo que lo
     *    contiene. La columna `contexto` guarda el texto de los ancestros del nodo.
     *
     * 2. ts_rank lleva un tercer argumento (2) que normaliza la puntuación por la
     *    longitud del texto. Sin él gana la repetición, y con ella los artículos
     *    largos: el que enumera un impuesto desplaza al que dice cuánto se paga.
     *
     * 3. El tope es LIMITE_ARTICULADO (30) y no 10: una sola ley puede tener decenas
     *    de artículos sobre el mismo tema, y el único que da la cifra puede quedar
     *    fuera de un corte corto.
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
                n.pagina,
                n.regulacion_id,
                r.nombre as regulacion_nombre,
                r.ambito,
                r.estado,
                r.municipio,
                -- texto + contexto, y ts_rank normalizado por longitud (ver docblock).
                ts_rank(
                    to_tsvector('spanish_unaccent', coalesce(n.texto, '') || ' ' || coalesce(n.contexto, '')),
                    to_tsquery('spanish_unaccent', ?),
                    2
                ) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish_unaccent', coalesce(n.texto, '') || ' ' || coalesce(n.contexto, '')) @@ to_tsquery('spanish_unaccent', ?)",
                [$consultaFt]
            )

            // Fuera la ESTRUCTURA: títulos, capítulos y secciones son letreros, no
            // contenido. Aportan contexto —y eso ya lo hacen a través de la columna
            // `contexto`—, pero devolverlos como resultado equivale a contestar con el
            // índice de la ley en vez de con el artículo.
            //
            // Importa porque compiten y ganan: un capítulo de cinco palabras tiene
            // densidad perfecta, y con ts_rank normalizado por longitud desplaza a
            // cualquier artículo real.
            //
            // El segundo filtro descarta los rótulos huérfanos: párrafos en MAYÚSCULAS
            // de menos de 60 caracteres que el estructurador no reconoció como títulos.
            // Se limita a los párrafos a propósito: un artículo, una fracción o un
            // inciso no se descartan nunca por cortos que sean, porque el que dice
            // "el 8% del monto total" tiene que llegar siempre.
            //
            // Se resuelve aquí y no en el estructurador porque cambiarlo allí obligaría
            // a reestructurar todas las regulaciones ya cargadas.
            ->whereNotIn('n.tipo', ['titulo', 'capitulo', 'seccion'])
            ->where(function ($q) {
                $q->where('n.tipo', '!=', 'parrafo')
                  ->orWhereRaw('LENGTH(n.texto) >= 60')
                  ->orWhereRaw('n.texto <> UPPER(n.texto)');
            })
            ->whereNull('n.deleted_at');

        if (!empty($regulacionIds)) {
            $query->whereIn('n.regulacion_id', $regulacionIds);
        }

        // No mezclar derecho de otra jurisdicción: solo artículos de leyes que aplican aquí.
        $this->filtrarPorJurisdiccion($query, 'r');

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

                // El texto SIN recortar, para medir cobertura de conceptos (ver
                // coberturaDeConceptos). El fragmento se recorta a 250 para MOSTRAR; medir la
                // cobertura sobre el recorte daría falsos negativos (un concepto que aparece más
                // allá del carácter 250 contaría como ausente). No se muestra al usuario; es un
                // dato interno que se usa y se descarta.
                'texto_completo' => (string) $r->texto,

                'score'     => (float) $r->score,
                'fuera_de_jurisdiccion' => $this->esDeOtraJurisdiccion($r->ambito, $r->estado, $r->municipio),
                'url'       => route('regulaciones.show', $r->regulacion_id),
                'meta'      => [
                    'regulacion_id' => $r->regulacion_id,
                    'nodo_id'       => $r->id,
                    'tipo_nodo'     => $r->tipo,
                    'pagina'        => $r->pagina,

                    // URL del PDF ORIGINAL abierto en la página del artículo. Si el
                    // nodo no tiene página (regulación Word, o no emparejada), el
                    // fragmento #page se omite y el visor abre en la primera página.
                    'pdf_url'       => route('regulaciones.preview', $r->regulacion_id)
                        . ($r->pagina ? '#page=' . $r->pagina : ''),
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
                ambito, estado, municipio,
                ts_rank(to_tsvector('spanish_unaccent', coalesce(nombre, '') || ' ' || coalesce(resumen, '') || ' ' || coalesce(objetivo, '') || ' ' || coalesce(palabras_clave, '') || ' ' || coalesce(materia, '')), to_tsquery('spanish_unaccent', ?)) as score
            ", [$consultaFt])
            ->whereNull('deleted_at');

        if (!empty($regulacionIds)) {
            // Con filtro activo mostramos SIEMPRE las regulaciones elegidas,
            // sin importar el score — el usuario ya las eligió a propósito.
            $query->whereIn('id', $regulacionIds);
        } else {
            $query->whereRaw(
                "to_tsvector('spanish_unaccent', coalesce(nombre, '') || ' ' || coalesce(resumen, '') || ' ' || coalesce(objetivo, '') || ' ' || coalesce(palabras_clave, '') || ' ' || coalesce(materia, '')) @@ to_tsquery('spanish_unaccent', ?)",
                [$consultaFt]
            );
        }

        // No mezclar derecho de otra jurisdicción: solo fichas de leyes que aplican aquí.
        $this->filtrarPorJurisdiccion($query, 'regulaciones');

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
                'fuera_de_jurisdiccion' => $this->esDeOtraJurisdiccion($r->ambito, $r->estado, $r->municipio),
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
                ts_rank(to_tsvector('spanish_unaccent', coalesce(t.nombre_oficial, '') || ' ' || coalesce(t.objetivo, '') || ' ' || coalesce(t.poblacion_objetivo, '')), to_tsquery('spanish_unaccent', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish_unaccent', coalesce(t.nombre_oficial, '') || ' ' || coalesce(t.objetivo, '') || ' ' || coalesce(t.poblacion_objetivo, '')) @@ to_tsquery('spanish_unaccent', ?)",
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
                ts_rank(to_tsvector('spanish_unaccent', coalesce(rq.nombre, '')), to_tsquery('spanish_unaccent', ?)) as score
            ", [$consultaFt])
            ->whereRaw("to_tsvector('spanish_unaccent', coalesce(rq.nombre, '')) @@ to_tsquery('spanish_unaccent', ?)", [$consultaFt])
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
                r.ambito,
                r.estado,
                r.municipio,
                ts_rank(to_tsvector('spanish_unaccent', coalesce(fj.normativa_nombre, '') || ' ' || coalesce(fj.articulo_fraccion, '') || ' ' || coalesce(fj.resumen, '')), to_tsquery('spanish_unaccent', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish_unaccent', coalesce(fj.normativa_nombre, '') || ' ' || coalesce(fj.articulo_fraccion, '') || ' ' || coalesce(fj.resumen, '')) @@ to_tsquery('spanish_unaccent', ?)",
                [$consultaFt]
            )
            ->whereNull('t.deleted_at');

        if (!empty($regulacionIds)) {
            $query->whereIn('fj.regulacion_id', $regulacionIds);
        }

        // Solo fundamentos que citan leyes de esta jurisdicción. Como el JOIN a
        // regulaciones es LEFT, un fundamento sin ley vinculada tiene ambito NULL
        // y por tanto se incluye (NULL = incluido).
        $this->filtrarPorJurisdiccion($query, 'r');

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
                'fuera_de_jurisdiccion' => $this->esDeOtraJurisdiccion($r->ambito, $r->estado, $r->municipio),
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
                ts_rank(to_tsvector('spanish_unaccent', coalesce(aa.descripcion, '') || ' ' || coalesce(aa.meta, '')), to_tsquery('spanish_unaccent', ?)) as score
            ", [$consultaFt])
            ->whereRaw(
                "to_tsvector('spanish_unaccent', coalesce(aa.descripcion, '') || ' ' || coalesce(aa.meta, '')) @@ to_tsquery('spanish_unaccent', ?)",
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
