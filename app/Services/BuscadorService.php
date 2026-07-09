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
    ) {}

    /**
     * Número máximo de resultados por fuente.
     * Se limita para que una fuente con muchos matches no desplace a las demás.
     */
    private const LIMITE_POR_FUENTE = 10;

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

        $consultaFt = $this->prepararConsultaFulltext($consulta);

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

            return [
                'resultados'          => $resultados->sortByDesc('score')->values(),
                'respuesta_destacada' => $respuestaDestacada,
                'modo'                => 'filtrado',
            ];
        }

        // ── Búsqueda sin filtro de regulación ────────────────────────────
        // Si hay filtro de tipos activo, se desactiva el enrutamiento por
        // concepto — el usuario ya eligió en qué fuentes quiere buscar.
        if (!$forzarCompleto && $tipos === null) {
            $metodoEnfocado = $this->determinarMetodoEnfocado($palabras);

            if ($metodoEnfocado !== null) {
                $resultadosEnfocados = collect($this->$metodoEnfocado($consultaFt, $consulta));

                if ($resultadosEnfocados->isNotEmpty()) {
                    return [
                        'resultados'          => $resultadosEnfocados->sortByDesc('score')->values(),
                        'respuesta_destacada' => $respuestaDestacada,
                        'modo'                => 'enfocado',
                    ];
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
        $resultados = collect();
        if ($incluir('articulo'))   $resultados = $resultados->merge($this->buscarEnArticulado($consultaFt, $consulta));
        if ($incluir('regulacion')) $resultados = $resultados->merge($this->buscarEnRegulaciones($consultaFt, $consulta));
        if ($incluir('tramite'))    $resultados = $resultados->merge($this->buscarEnTramites($consultaFt, $consulta));
        if ($incluir('requisito'))  $resultados = $resultados->merge($this->buscarEnRequisitos($consultaFt, $consulta));
        if ($incluir('fundamento')) $resultados = $resultados->merge($this->buscarEnFundamentos($consultaFt, $consulta));

        return [
            'resultados'          => $resultados->sortByDesc('score')->values(),
            'respuesta_destacada' => $respuestaDestacada,
            'modo'                => 'completo',
        ];
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
    private function prepararConsultaFulltext(string $consulta): string
    {
        $palabras = preg_split('/\s+/', $consulta);

        $terminos = [];
        foreach ($palabras as $palabra) {
            // Limpiar caracteres especiales de FULLTEXT que podrían romper la query
            $limpia = preg_replace('/[+\-><()~*"@]/', '', $palabra);
            if (mb_strlen($limpia) >= 3) {
                $terminos[] = '+' . $limpia . '*';
            }
        }

        // Si no quedaron términos válidos (todo era muy corto), usar la
        // consulta original sin operadores para que FULLTEXT haga match natural.
        if (empty($terminos)) {
            return $consulta;
        }

        return implode(' ', $terminos);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fuente 1: Articulado de regulaciones (regulacion_nodos)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Busca en el texto de los nodos del articulado (artículos, fracciones,
     * incisos, párrafos). Cada nodo es un fragmento específico de una
     * regulación. El resultado incluye la ruta al artículo dentro de la
     * regulación (ej. "Título I > Capítulo II > Artículo 15").
     *
     * Cuando $regulacionIds está presente, solo devuelve nodos de esa ley.
     *
     * Responde preguntas como:
     *   "¿Quién puede emitir una licencia?"
     *   "¿Qué dice el artículo 45?"
     *   "¿Cuántos días tiene la autoridad para resolver?"
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
                MATCH(n.texto) AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereRaw('MATCH(n.texto) AGAINST(? IN BOOLEAN MODE)', [$consultaFt])
            ->whereNull('n.deleted_at');

        if (!empty($regulacionIds)) {
            $query->whereIn('n.regulacion_id', $regulacionIds);
        }

        $resultados = $query
            ->orderByDesc('score')
            ->limit(self::LIMITE_POR_FUENTE)
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
                MATCH(nombre, resumen, objetivo, palabras_clave, materia)
                    AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereNull('deleted_at');

        if (!empty($regulacionIds)) {
            // Con filtro activo mostramos SIEMPRE las regulaciones elegidas,
            // sin importar el score — el usuario ya las eligió a propósito.
            $query->whereIn('id', $regulacionIds);
        } else {
            $query->whereRaw(
                'MATCH(nombre, resumen, objetivo, palabras_clave, materia) AGAINST(? IN BOOLEAN MODE)',
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
                MATCH(t.nombre_oficial, t.objetivo, t.poblacion_objetivo)
                    AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereRaw(
                'MATCH(t.nombre_oficial, t.objetivo, t.poblacion_objetivo) AGAINST(? IN BOOLEAN MODE)',
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
                MATCH(rq.nombre) AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereRaw('MATCH(rq.nombre) AGAINST(? IN BOOLEAN MODE)', [$consultaFt])
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
                MATCH(fj.normativa_nombre, fj.articulo_fraccion, fj.resumen)
                    AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereRaw(
                'MATCH(fj.normativa_nombre, fj.articulo_fraccion, fj.resumen) AGAINST(? IN BOOLEAN MODE)',
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
                MATCH(aa.descripcion, aa.meta) AGAINST(? IN BOOLEAN MODE) as score
            ", [$consultaFt])
            ->whereRaw(
                'MATCH(aa.descripcion, aa.meta) AGAINST(? IN BOOLEAN MODE)',
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
