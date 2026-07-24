<?php

namespace App\Services;

use App\Models\RegulacionNodo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Redacta una respuesta en lenguaje natural a partir de lo que el buscador YA encontró.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA REGLA QUE GOBIERNA TODO ESTE ARCHIVO
 * ══════════════════════════════════════════════════════════════════════
 *
 *     LA IA NO APORTA INFORMACIÓN. APORTA REDACCIÓN.
 *
 * Este servicio NO busca, NO consulta la base de datos, NO sabe nada de PUNTA. Recibe la
 * pregunta del ciudadano y los resultados que BuscadorService ya encontró, y solo hace una
 * cosa: leerlos y redactar.
 *
 * Todo lo que diga tiene que salir de esas fuentes. Si no contienen la respuesta, devuelve
 * null — y el usuario ve la lista de resultados de siempre, exactamente como hoy.
 *
 * ── POR QUÉ ESTA REGLA Y NO OTRA ──
 *
 * No es una precaución exagerada. El propio código del buscador ya la había establecido. En
 * FeaturedAnswerService, sobre las definiciones extraídas automáticamente:
 *
 *     "la confianza es «media» porque la extracción fue automática (basada en patrones de
 *      texto, no curada por un humano), pero sigue siendo UNA FUENTE LEGAL REAL —
 *      NO ES TEXTO INVENTADO."
 *
 * Quien construyó PUNTA ya decidió que el buscador no inventa: cita.
 *
 * Una IA generativa suelta rompería esa regla el primer día. Un ciudadano preguntaría
 * "¿cuánto cuesta mi licencia de funcionamiento?", el modelo produciría una cifra
 * perfectamente plausible, y NADIE PODRÍA DETECTARLA COMO FALSA.
 *
 * Sería el bug número catorce de este proyecto. Y sería exactamente igual que los trece
 * anteriores: un número que parece bueno y no lo es, que no da ningún error, y que nadie
 * descubre hasta que un ciudadano se presenta en ventanilla con la cifra equivocada.
 *
 * ══════════════════════════════════════════════════════════════════════
 * CÓMO SE HACE CUMPLIR LA REGLA (tres candados, no uno)
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un buen prompt no basta. Un modelo puede ignorarlo, y lo hará alguna vez. Por eso hay tres
 * capas, y las tres tienen que pasar:
 *
 *   1. EL PROMPT. Se le dice que solo puede usar las fuentes dadas, y que si no bastan tiene
 *      que decirlo. Es la primera línea, y la más débil.
 *
 *   2. EL FORMATO. Se le exige que devuelva JSON con `suficiente` y `fuentes` (los números de
 *      las fuentes que usó). Una respuesta sin fuentes citadas se RECHAZA aquí mismo, aunque
 *      el texto sea precioso.
 *
 *   3. LA VERIFICACIÓN. Los índices que dice haber usado se comprueban contra los que se le
 *      pasaron. Si cita una fuente que no existe —se la inventó— se descarta TODA la
 *      respuesta. Un modelo que inventa citas también inventa contenido.
 *
 * ══════════════════════════════════════════════════════════════════════
 * Y SI ALGO FALLA, NO PASA NADA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Todos los caminos de error terminan igual: `return null`. Y el buscador sigue.
 *
 *   · El asistente está apagado          → null
 *   · No hay API key                     → null
 *   · No hay resultados que resumir      → null
 *   · La API tarda más de 8 segundos     → null
 *   · La API devuelve un error           → null
 *   · El JSON viene malformado           → null
 *   · El modelo dice que no le basta     → null
 *   · El modelo cita una fuente inventada→ null
 *
 * En los ocho casos, el ciudadano ve su lista de resultados. Es lo que ve HOY, y es lo que
 * debe seguir viendo si la IA no está.
 *
 * Un buscador municipal no puede depender de que una API externa esté de humor.
 */
class AsistenteRespuestaService
{
    /**
     * Intenta redactar una respuesta a partir de los resultados del buscador.
     *
     * @param  string     $consulta   Lo que escribió el ciudadano, tal cual.
     * @param  Collection $resultados Lo que BuscadorService encontró.
     * @param  ?string    $intencion  DEFINICION, COSTO, REQUISITOS, FUNDAMENTO... o null.
     *
     * @return array|null  Una respuesta destacada con la misma forma que las de
     *                     FeaturedAnswerService, o null si no se pudo construir.
     */
    public function construir(string $consulta, Collection $resultados, ?string $intencion = null): ?array
    {
        if (! $this->estaActivo()) {
            return null;
        }

        $fuentes = $this->prepararFuentes($resultados);

        // Sin fuentes no hay respuesta. Ni se intenta.
        //
        // Este `if` es el corazón de todo el diseño: la IA NUNCA se llama "a ver qué dice".
        // Solo se la llama cuando ya hay material que redactar. Si el buscador no encontró
        // nada, el asistente tampoco puede saber nada — y pedirle que responda de todas formas
        // es pedirle explícitamente que se lo invente.
        if ($fuentes === []) {
            return null;
        }

        // La caché evita pagar dos veces por la misma pregunta, y evita que cien ciudadanos
        // preguntando lo mismo produzcan cien llamadas.
        //
        // La clave incluye los IDs de las fuentes: si mañana se reestructura una regulación y
        // el buscador devuelve otros artículos, la clave cambia y se vuelve a preguntar. La
        // caché se invalida sola, sin que nadie tenga que acordarse.
        $clave = $this->claveDeCache($consulta, $fuentes);

        return Cache::remember(
            $clave,
            now()->addHours((int) config('punta.asistente.cache_horas', 24)),
            fn () => $this->preguntarAlModelo($consulta, $fuentes, $intencion)
        );
    }

    /* ----------------------------------------------------------------------
     | El interruptor
     |----------------------------------------------------------------------*/

    /**
     * ¿Está encendido Y configurado?
     *
     * Las dos cosas. Un `activo = true` sin API key no es "medio encendido": es un servicio
     * que va a fallar en cada búsqueda, tardando 8 segundos en fallar. Peor que apagado.
     */
    private function estaActivo(): bool
    {
        return (bool) config('punta.asistente.activo')
            && ! empty(config('punta.asistente.api_key'));
    }

    /* ----------------------------------------------------------------------
     | Las fuentes
     |----------------------------------------------------------------------*/

    /**
     * Convierte los resultados del buscador en fuentes numeradas para el modelo.
     *
     * ── QUÉ SE ENVÍA, Y QUÉ NO ──
     *
     * Se envía: texto de artículos de regulaciones (documentos PÚBLICOS, publicados en el
     * boletín oficial), nombres de trámites, requisitos y costos — todo lo que ya aparece en
     * el portal ciudadano.
     *
     * NO se envía: nada de la tabla users, ni firmantes, ni bitácora, ni datos personales.
     *
     * Y no es una promesa: es una GARANTÍA POR CONSTRUCCIÓN. Este método solo puede leer lo
     * que BuscadorService le pasa, y BuscadorService solo devuelve contenido público. Hay una
     * prueba que lo vigila (test_no_se_envia_ningun_dato_personal).
     *
     * ── POR QUÉ SOLO OCHO ──
     *
     * No es tacañería. Con cuarenta fragmentos, el modelo se pierde y mezcla fuentes: cita el
     * artículo 15 de un reglamento y le atribuye el texto del artículo 20 de otro. Con ocho
     * —los mejor puntuados por el buscador, que para eso los puntúa— tiene lo que necesita
     * sin ruido.
     *
     * Menos contexto y mejor elegido produce respuestas más fiables que más contexto y peor
     * elegido. Es contraintuitivo y es así.
     */
    private function prepararFuentes(Collection $resultados): array
    {
        $maxFuentes = (int) config('punta.asistente.max_fuentes', 8);
        $maxChars   = (int) config('punta.asistente.max_caracteres_por_fuente', 800);

        // Se guarda en $fuentes (en vez de devolver directamente aquí) para que el
        // resultado pase por inyectarContextoPermanente() antes de salir: es ahí donde
        // se añaden los artículos-catálogo (la escala y las clases) que evitan la cita
        // falsa. Devolver aquí dejaba esa inyección como código muerto.
        $fuentes = $resultados
            ->take($maxFuentes)
            ->values()
            ->map(function ($r, $i) use ($maxChars) {

                // ── LA FORMA REAL DE UN RESULTADO ──
                //
                // BuscadorService devuelve SIEMPRE esta estructura, para las seis fuentes:
                //
                //     ['tipo', 'icono', 'titulo', 'subtitulo', 'fragmento', 'score', 'url', 'meta']
                //
                // La primera versión de este método buscaba claves que NO EXISTEN ('texto',
                // 'descripcion', 'fuente', 'articulo'). Me las inventé sin mirar el contrato.
                //
                // El resultado fue peor que un error: el asistente recibía fuentes SIN NINGÚN
                // TEXTO —solo el título, "Artículo 1"— y el modelo, muy correctamente, respondía
                // que las fuentes no le bastaban. Todo "funcionaba": el buscador encontraba 11
                // resultados, el modelo contestaba, el sistema descartaba la respuesta por falta
                // de fuentes... y el ciudadano no veía nada, sin ningún error en ningún log.
                //
                // El bug catorce, y con mi firma. Un mecanismo plausible que no hace nada.
                $texto = $this->textoCompleto($r);

                return [
                    'n'         => $i + 1, // el número que el modelo tendrá que citar
                    'tipo'      => $r['tipo']      ?? 'resultado',
                    'titulo'    => $r['titulo']    ?? '',
                    'subtitulo' => $r['subtitulo'] ?? '',
                    'texto'     => Str::limit($texto, $maxChars),

                    // La cita se arma con lo que hay: el título es el artículo ("Artículo 15"),
                    // el subtítulo es la regulación ("Ley de Hacienda"). No hay campos separados
                    // de 'articulo' y 'fraccion': el buscador los junta en la etiqueta.
                    'contexto'      => $this->contextoDelNodo($r),

                    'fuente'        => $r['subtitulo']            ?? null,

                    // La cita COMPLETA, con el artículo padre si el nodo es una fracción o un inciso.
                    // Ver citaCompleta(): "Fracción I" a secas no se puede encontrar en una ley.
                    'articulo'      => $this->citaCompleta($r),
                    'fraccion'      => null,
                    'regulacion_id' => $r['meta']['regulacion_id'] ?? null,
                    'url'           => $r['url']                  ?? null,

                    // Página del PDF original y enlace directo a ella. Viajan desde el
                    // resultado para que las fuentes citadas se puedan abrir en el
                    // documento oficial, en el punto exacto, y no solo en el articulado.
                    'pagina'        => $r['meta']['pagina']  ?? null,
                    'pdf_url'       => $r['meta']['pdf_url'] ?? null,

                    // Viaja desde el resultado del buscador para que la red dura de
                    // armarRespuesta() pueda avisar si el modelo usa una ley de otra jurisdicción.
                    'fuera_de_jurisdiccion' => $r['fuera_de_jurisdiccion'] ?? false,
                ];
            })
            // Una fuente sin texto no sirve de nada: el modelo no puede redactar a partir de un
            // título. Se descartan aquí en vez de mandárselas y dejar que él se apañe.
            ->filter(fn ($f) => $f['texto'] !== '')
            ->values()
            ->all();

        return $this->inyectarContextoPermanente($fuentes);
    }

    /**
     * Añade los artículos-CATÁLOGO de una regulación cuando alguna de sus fuentes ya está presente.
     *
     * ══════════════════════════════════════════════════════════════════════
     * POR QUÉ EL ASISTENTE INVENTABA LA CIFRA EQUIVOCADA
     * ══════════════════════════════════════════════════════════════════════
     *
     * Ante "cuánto es la multa por obstruir la banqueta", el buscador encuentra el artículo 65 del
     * Bando (la conducta: "poner obstáculos en banquetas sin permiso") pero NO los artículos 104 y
     * 105 —la escala de clases y el catálogo—, porque esos no mencionan "banqueta".
     *
     * Sin esos dos, el asistente tenía la conducta pero no la tabla que la convierte en cifra. Y
     * rellenaba el hueco con lo que sí tenía a mano: el artículo 154 de la Ley de Hacienda (multas
     * de TRÁNSITO). Daba 3 UMA donde la ley marca 31-100, con una cita que sonaba autorizada. El
     * peor error posible: equivocado y con fundamento equivocado, dicho con confianza.
     *
     * ── Qué hace este método ──
     *
     * Mira qué regulaciones aparecen entre las fuentes que trajo el buscador. Para cada una, añade
     * sus artículos marcados con `tipo_referencia` (el catálogo de clases del Bando), si no
     * estaban ya. Así el asistente recibe las TRES piezas de la cadena —conducta + catálogo +
     * escala— y arma la respuesta correcta sin inventar nada.
     *
     * El marcado lo pone la IA al cargar la ley (DetectorCatalogosService), no una regla fija: por
     * eso funciona con cualquier ley de cualquier jurisdicción.
     *
     * ── Por qué no rompe las demás respuestas ──
     *
     *   · Solo se inyecta el catálogo de una regulación SI esa regulación ya está entre las
     *     fuentes. Una búsqueda de la Ley de Hacienda no arrastra el catálogo del Bando.
     *   · Casi ningún nodo lleva etiqueta (índice parcial), así que la consulta es mínima.
     *   · Si el catálogo ya venía entre las fuentes, no se duplica.
     *
     * @param  array<int, array<string, mixed>>  $fuentes
     * @return array<int, array<string, mixed>>
     */
    private function inyectarContextoPermanente(array $fuentes): array
    {
        if ($fuentes === []) {
            return $fuentes;
        }

        $regulacionesPresentes = collect($fuentes)
            ->pluck('regulacion_id')
            ->filter()
            ->unique()
            ->values();

        if ($regulacionesPresentes->isEmpty()) {
            return $fuentes;
        }

        // Artículos de referencia de esas regulaciones. El índice parcial hace esta consulta
        // instantánea: casi ninguna fila lleva etiqueta.
        $catalogos = \App\Models\RegulacionNodo::query()
            ->whereIn('regulacion_id', $regulacionesPresentes)
            ->whereNotNull('tipo_referencia')
            ->with('hijos:id,parent_id,texto,orden')
            ->get();

        if ($catalogos->isEmpty()) {
            return $fuentes;
        }

        // Qué artículos-catálogo ya venían, para no duplicar. Se compara por regulación + cita.
        $yaPresentes = collect($fuentes)
            ->map(fn ($f) => ($f['regulacion_id'] ?? '') . ':' . ($f['articulo'] ?? ''))
            ->all();

        // Qué regulaciones presentes son de otra jurisdicción, para heredar la marca a
        // sus artículos-catálogo inyectados: comparten ley, así que comparten jurisdicción.
        $fueraPorRegulacion = collect($fuentes)
            ->filter(fn ($f) => $f['fuera_de_jurisdiccion'] ?? false)
            ->pluck('regulacion_id')
            ->flip();

        $n = count($fuentes);

        foreach ($catalogos as $nodo) {
            $cita  = trim(($nodo->tipo === RegulacionNodo::TIPO_ARTICULO ? 'Artículo ' : '') . $nodo->numero);
            $clave = $nodo->regulacion_id . ':' . $cita;

            if (in_array($clave, $yaPresentes, true)) {
                continue; // ya lo trajo el buscador
            }

            $fuentes[] = [
                'n'             => ++$n,
                'tipo'          => $nodo->tipo,
                'titulo'        => $cita,
                'subtitulo'     => optional($nodo->regulacion)->nombre ?? '',
                'texto'         => $this->textoConCatalogoCompleto($nodo),
                'contexto'      => $nodo->contexto,
                'fuente'        => optional($nodo->regulacion)->nombre ?? null,
                'articulo'      => $cita,
                'fraccion'      => null,
                'regulacion_id' => $nodo->regulacion_id,
                'url'           => null,

                // Mismo enlace al PDF que arma el buscador: la ruta de vista previa
                // más el fragmento #page=N. Sin página guardada se abre en la primera.
                'pagina'        => $nodo->pagina,
                'pdf_url'       => $nodo->regulacion_id
                    ? route('regulaciones.preview', $nodo->regulacion_id)
                        . ($nodo->pagina ? '#page=' . $nodo->pagina : '')
                    : null,
                'fuera_de_jurisdiccion' => $fueraPorRegulacion->has($nodo->regulacion_id),
            ];
        }

        return $fuentes;
    }

    /**
     * El texto de un nodo-catálogo PARA EL PROMPT, incluyendo sus hijos.
     *
     * Por qué los hijos: la sustancia de una escala vive en ellos. El artículo 104
     * del Bando solo dice en su texto propio "…se clasifican de la siguiente manera:";
     * las cuantías ("Clase D: Multa de 31 a 100 UMA") están en los incisos hijos. Sin
     * ellos, el asistente ve la clase pero no el monto, y no puede cuantificar la
     * multa —justo el hueco del caso de la banqueta—. Es el mismo principio que ya usa
     * el detector para clasificar (texto con hijos).
     */
    private function textoConCatalogoCompleto($nodo): string
    {
        $partes = [trim((string) $nodo->texto)];

        foreach ($nodo->hijos->sortBy('orden') as $hijo) {
            $t = trim((string) $hijo->texto);
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return Str::limit(implode("\n", array_filter($partes)), 1500);
    }

    /**
     * El texto que se le da al modelo. Y aquí hay una trampa que casi se me pasa.
     *
     * ── POR QUÉ NO BASTA CON EL `fragmento` ──
     *
     * BuscadorService recorta cada resultado a 250 caracteres (LARGO_FRAGMENTO). Es lo correcto
     * PARA LO QUE SIRVE: pintar una lista de resultados donde el usuario ve un extracto y
     * decide en cuál hacer clic.
     *
     * Pero darle 250 caracteres de un artículo a un modelo es darle un artículo MUTILADO.
     *
     * Un artículo de la Ley de Hacienda con las tarifas del predial no cabe en 250 caracteres:
     * el modelo vería la mitad de una tabla y tendría que adivinar el resto. Y solo puede hacer
     * dos cosas con eso: inventarse el final, o decir que no le basta.
     *
     * Lo segundo es lo que pasó la primera vez que probamos esto. Lo primero habría sido mucho
     * peor.
     *
     * El fragmento y el contexto del modelo son DOS USOS DISTINTOS con dos necesidades
     * distintas. Confundirlos fue el error.
     *
     * ── Qué hace ahora ──
     *
     * Para los artículos —que son la fuente que de verdad importa— va a buscar el TEXTO COMPLETO
     * del nodo a la base. Para el resto (trámites, requisitos, regulaciones) el fragmento ya
     * contiene lo esencial: un requisito ES su nombre y su descripción, no hay más.
     *
     * El coste de la consulta extra es despreciable: son ocho nodos, buscados por su clave
     * primaria, una sola vez por búsqueda (y luego cacheada).
     */
    private function textoCompleto(array $resultado): string
    {
        $nodoId = $resultado['meta']['nodo_id'] ?? null;

        if ($nodoId !== null) {
            $nodo = RegulacionNodo::whereKey($nodoId)->first(['texto', 'contexto']);

            if ($nodo && ! empty($nodo->texto)) {
                return trim((string) $nodo->texto);
            }
        }

        // Para lo que no es un nodo del articulado, el fragmento ya trae lo que hay.
        return trim((string) ($resultado['fragmento'] ?? ''));
    }

    /**
     * El CONTEXTO del artículo: el título, el capítulo y la sección de los que cuelga.
     *
     * ══════════════════════════════════════════════════════════════════════
     * POR QUÉ EL MODELO LO NECESITA (y no solo el buscador)
     * ══════════════════════════════════════════════════════════════════════
     *
     * Alguien pregunta: "¿cuáles son los impuestos aplicables al patrimonio?"
     *
     * El buscador encuentra el artículo 26 —el del Impuesto Predial— porque busca sobre texto +
     * contexto, y su contexto dice "CAPÍTULO II IMPUESTOS SOBRE EL PATRIMONIO".
     *
     * Pero al modelo se le pasaba solo el TEXTO:
     *
     *     "Son objeto del Impuesto Predial, la propiedad, usufructo, goce, uso y posesión..."
     *
     * Y ahí no aparece la palabra "patrimonio" por ningún lado. El modelo lee eso, no sabe que
     * ese artículo cuelga del capítulo de los impuestos patrimoniales, y responde —muy
     * honestamente— que no encontró una lista de impuestos al patrimonio.
     *
     * Tenía la respuesta delante y no podía verla.
     *
     * ── La misma isla sin contexto, un nivel más arriba ──
     *
     * Es EXACTAMENTE el bug que acabábamos de arreglar en el buscador, repetido en el asistente.
     * Metimos el contexto en el WHERE y en el ts_rank... y nos olvidamos de metérselo al modelo.
     *
     * Un abogado que lee el artículo 26 sabe que es patrimonial porque VE EL ENCABEZADO tres
     * líneas más arriba. Al modelo le estábamos dando la página arrancada del libro.
     *
     * ── Y esto es lo que permite responder "cuáles son" ──
     *
     * Ningún artículo dice "los impuestos al patrimonio son estos tres". Esa lista NO EXISTE COMO
     * FRASE: existe como ESTRUCTURA. Está en el árbol, no en el texto.
     *
     * Con el contexto delante, el modelo ve que el 26, el 38 y los de urbanización cuelgan todos
     * del mismo capítulo, y puede responder. Sin él, solo ve tres artículos sueltos que hablan de
     * predios, escrituras y pavimentos.
     */
    private function contextoDelNodo(array $resultado): ?string
    {
        $nodoId = $resultado['meta']['nodo_id'] ?? null;

        if ($nodoId === null) {
            return null;
        }

        $contexto = RegulacionNodo::whereKey($nodoId)->value('contexto');

        return ! empty($contexto) ? trim((string) $contexto) : null;
    }

    /**
     * La cita COMPLETA de un nodo: con su artículo padre si hace falta.
     *
     * ══════════════════════════════════════════════════════════════════════
     * POR QUÉ "FRACCIÓN I" NO ES UNA CITA
     * ══════════════════════════════════════════════════════════════════════
     *
     * El buscador etiqueta cada nodo con su tipo y su número: "Artículo 31", "Fracción I",
     * "Inciso e". Para una lista de resultados es suficiente: el usuario hace clic y lo ve.
     *
     * Pero como CITA de una respuesta legal, no vale nada:
     *
     *     "Ley de Hacienda, Fracción I"
     *
     * Hay DECENAS de "Fracción I" en una ley. El ciudadano lee eso y NO PUEDE ENCONTRARLA.
     *
     * ── Y eso rompe lo único que sostiene todo este servicio ──
     *
     * La cita es lo que separa una respuesta REDACTADA de una respuesta INVENTADA. Es lo que
     * permite al ciudadano comprobar que el 2 al millar existe de verdad, y al Ayuntamiento
     * defenderlo si alguien lo discute.
     *
     * Si la cita no se puede seguir, el ciudadano solo tiene LA PALABRA DEL MODELO. Que es
     * exactamente lo que este servicio existe para evitar.
     *
     *     UNA CITA QUE NO SE PUEDE COMPROBAR NO ES UNA CITA.
     *
     * ── Qué hace ──
     *
     * Sube por el árbol hasta encontrar el artículo del que cuelga el nodo:
     *
     *     "Fracción I"   →   "Artículo 31, Fracción I"      ✓ comprobable
     *     "Inciso e"     →   "Artículo 88, Inciso e"        ✓ comprobable
     *     "Artículo 65"  →   "Artículo 65"                  (ya lo era)
     *
     * ── Y si no encuentra artículo padre ──
     *
     * Devuelve la etiqueta tal cual. Un párrafo suelto no cuelga de ningún artículo, y forzar una
     * cita inventada sería peor que dar una incompleta.
     */
    private function citaCompleta(array $resultado): ?string
    {
        $etiqueta = $resultado['titulo'] ?? null;
        $nodoId   = $resultado['meta']['nodo_id'] ?? null;

        if ($etiqueta === null || $nodoId === null) {
            return $etiqueta;
        }

        // Si ya ES un artículo, no hay nada que anteponer.
        if (($resultado['meta']['tipo_nodo'] ?? null) === RegulacionNodo::TIPO_ARTICULO) {
            return $etiqueta;
        }

        $articulo = $this->articuloPadre($nodoId);

        return $articulo !== null
            ? "Artículo {$articulo}, {$etiqueta}"
            : $etiqueta;
    }

    /**
     * Sube por el árbol hasta el artículo del que cuelga un nodo. Null si no hay ninguno.
     *
     * El límite de vueltas es una red contra un ciclo en parent_id. No debería existir —esto es
     * un árbol, no un grafo— pero un bucle infinito aquí colgaría la búsqueda de un ciudadano sin
     * dar ningún error, y eso es peor que una cita incompleta.
     */
    private function articuloPadre(int $nodoId): ?string
    {
        $actual  = RegulacionNodo::whereKey($nodoId)->first(['parent_id']);
        $vueltas = 0;

        while ($actual?->parent_id !== null && $vueltas++ < 10) {
            $padre = RegulacionNodo::whereKey($actual->parent_id)
                ->first(['id', 'parent_id', 'tipo', 'numero']);

            if (! $padre) {
                return null;
            }

            if ($padre->tipo === RegulacionNodo::TIPO_ARTICULO) {
                return $padre->numero;
            }

            $actual = $padre;
        }

        return null;
    }

    private function claveDeCache(string $consulta, array $fuentes): string
    {
        $ids = implode(',', array_map(
            fn ($f) => ($f['tipo'] ?? '') . ':' . ($f['articulo'] ?? '') . ':' . ($f['regulacion_id'] ?? ''),
            $fuentes
        ));

        return 'asistente:' . md5(mb_strtolower(trim($consulta)) . '|' . $ids);
    }

    /* ----------------------------------------------------------------------
     | La llamada
     |----------------------------------------------------------------------*/

    private function preguntarAlModelo(string $consulta, array $fuentes, ?string $intencion): ?array
    {
        try {
            $respuesta = Http::withToken(config('punta.asistente.api_key'))
                ->timeout((int) config('punta.asistente.timeout', 8))
                ->acceptJson()
                ->post(config('punta.asistente.url'), [
                    'model'    => config('punta.asistente.modelo', 'deepseek-v4-flash'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instrucciones()],
                        ['role' => 'user',   'content' => $this->pregunta($consulta, $fuentes, $intencion)],
                    ],

                    // ── APAGAR EL "THINKING" NO ES OPCIONAL ──
                    //
                    // Los modelos V4 traen el modo de razonamiento ENCENDIDO por defecto. Eso
                    // multiplica los tokens de salida —y por tanto la factura— sin mejorar en
                    // absoluto lo que este asistente hace.
                    //
                    // Porque lo que hace es simple: leer cuatro artículos y resumirlos en tres
                    // frases. No hay nada que razonar en cadena. Pagar por un razonamiento que
                    // nadie va a leer es tirar el dinero del Ayuntamiento.
                    'thinking' => ['type' => 'disabled'],

                    // temperature 0: se quiere REDACCIÓN, no creatividad. Una respuesta legal no
                    // puede variar entre dos ciudadanos que preguntan lo mismo.
                    'temperature'     => 0,
                    'max_tokens'      => 600,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($respuesta->failed()) {
                Log::warning('El asistente del buscador devolvió un error.', [
                    'estado'  => $respuesta->status(),
                    'cuerpo'  => Str::limit($respuesta->body(), 300),
                ]);

                return null;
            }

            $contenido = $respuesta->json('choices.0.message.content');

            return $this->interpretar($contenido, $fuentes);

        } catch (Throwable $e) {
            // Timeout, DNS caído, cortafuegos, la API que se fue de vacaciones... da igual.
            //
            // El buscador NO PUEDE ROMPERSE porque una API externa esté de mal humor. Se
            // registra y se devuelve null: el ciudadano ve su lista de resultados, exactamente
            // como la vería hoy sin asistente.
            Log::warning('El asistente del buscador no respondió.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /* ----------------------------------------------------------------------
     | Los candados
     |----------------------------------------------------------------------*/

    /**
     * Las instrucciones al modelo. Es el PRIMER candado, y el más débil de los tres.
     *
     * Se escribe con cuidado, pero no se confía en él: un modelo puede ignorar cualquier
     * instrucción, y algún día lo hará. Los candados de verdad están en interpretar().
     */
    private function instrucciones(): string
    {
        return <<<TXT
        Eres un asistente del portal de trámites de un ayuntamiento mexicano. Ayudas a la
        ciudadanía a entender trámites, requisitos, costos y reglamentos municipales.

        REGLA ABSOLUTA, POR ENCIMA DE CUALQUIER OTRA:
        Solo puedes usar la información de las FUENTES que se te entregan. No puedes usar tu
        conocimiento general sobre trámites, leyes mexicanas ni nada más. Si las fuentes no
        contienen la respuesta, tienes que decirlo. NO INVENTES NUNCA un dato, una cifra, un
        plazo, un requisito ni un artículo.

        Es preferible decir "no lo sé" a dar una respuesta plausible pero inventada. Una cifra
        equivocada hace que una persona se presente en ventanilla con el dinero equivocado.

        TU SEGUNDA TAREA: SEPARAR EL GRANO DE LA PAJA.

        Las fuentes vienen de una búsqueda amplia, así que MUCHAS NO TIENEN NADA QUE VER con la
        pregunta. Salieron porque comparten alguna palabra, no porque respondan.

        Ejemplo real de este sistema. A la pregunta "¿cuánto cuesta el permiso para ambulantes?"
        la búsqueda devuelve, entre otras:

          · Un artículo sobre SANCIONES POR DESACATO al Bando de Policía, que menciona el retiro
            del "permiso" a los vendedores "ambulantes". Comparte las dos palabras. NO RESPONDE
            NADA.

          · Un inciso que dice "Ambulantes 0.05 UMA por día, pudiendo realizar el pago semanal o
            mensual". Solo comparte una palabra. RESPONDE EXACTAMENTE LA PREGUNTA.

        La primera es basura. La segunda es la respuesta. Tu trabajo es distinguirlas — y para eso
        te contratamos, porque un buscador no sabe hacerlo: solo cuenta palabras.

        IGNORA las fuentes que no respondan. No las cites, no las menciones, no las uses "por si
        acaso". Una respuesta construida a medias con una fuente irrelevante es una respuesta
        equivocada con aspecto de correcta.

        NO DEDUZCAS. NO INFIERAS. NO CONCLUYAS.

        Esta es la regla más difícil de seguir y la más importante. Un ejemplo real:

        La fuente dice: "Ambulantes 0.05 UMA por día, pudiendo realizar el pago semanal o
        mensual."

        Y alguien pregunta: "¿cuánto DURA el permiso?"

        Es TENTADOR razonar: "si se cobra por día, la vigencia la elige el contribuyente". Y
        probablemente sea cierto. Pero eso NO LO DICE LA FUENTE: lo estarías deduciendo tú.

        El problema es que otra deducción, igual de lógica, sería FALSA: "como el pago puede ser
        mensual, el máximo son 30 días". Suena impecable. Y podría arruinarle el permiso a alguien
        que deje de renovar creyendo que ese es el tope legal.

        Tú no puedes distinguir un razonamiento correcto de uno plausible. Nadie puede, sin
        conocer cómo funciona ese ayuntamiento por dentro. Así que no razonas: REPITES.

        Di lo que la fuente dice. Deja que la persona saque sus propias conclusiones — las sacará,
        y serán suyas, no del Ayuntamiento.

        TIENES TRES SALIDAS POSIBLES, Y HAY QUE ELEGIR BIEN:

        1) RESPONDO ("suficiente": true)
           Las fuentes contienen la respuesta directa. La repites en lenguaje claro.

        2) NO RESPONDO PERO CUENTO LO QUE SÍ HAY ("suficiente": false, "relacionado" con texto)
           Las fuentes NO responden lo que preguntan, pero SÍ dicen cosas sobre el mismo tema.
           Entonces no te callas: dices qué SÍ dicen.

           Ejemplo. Preguntan "cuánto dura el permiso de ambulantes" y las fuentes solo hablan del
           costo. Respuesta correcta:

             "No encontré ningún artículo que diga cuánto dura el permiso. Lo que sí dicen las
              regulaciones es que se cobra 0.05 UMA por día, y que el pago puede hacerse de forma
              semanal o mensual."

           Fíjate en lo que NO hace: no concluye que la vigencia la elija el contribuyente. Solo
           dice lo que la ley dice. La persona sacará esa conclusión sola, y será suya.

        3) NO HAY NADA ("suficiente": false, "relacionado" vacío)
           Las fuentes no responden NI hablan del tema. No hay nada que contar.

        Responde SIEMPRE con un JSON con exactamente esta forma:

        {
          "suficiente": true|false,
          "respuesta": "la respuesta directa, si la hay",
          "relacionado": "qué SÍ dicen las fuentes sobre el tema, si no hay respuesta directa",
          "fuentes": [1, 3]
        }

        - "suficiente": true SOLO si las fuentes responden lo que preguntan. Si tienes que deducir
          algo para llegar a la respuesta, es false.
        - "respuesta": 2 a 4 frases. Lenguaje sencillo, sin jerga jurídica. Si citas una cifra o un
          plazo, tiene que estar LITERALMENTE en alguna fuente.
        - "relacionado": se usa cuando "suficiente" es false PERO las fuentes hablan del tema.
          Empieza diciendo qué NO encontraste, y sigue con lo que SÍ dicen. Sin deducir nada.
        - "fuentes": los números de las fuentes que usaste, en cualquiera de los dos casos. NUNCA
          cites una fuente que no se te entregó.

        ══════════════════════════════════════════════════════════════════
        NUNCA ESCRIBAS "(fuente 3)" NI LLAMES "ARTÍCULO 23" A LA FUENTE [23]
        ══════════════════════════════════════════════════════════════════

        Los números entre corchetes —[1], [2], [23]— son etiquetas INTERNAS para que TÚ me digas
        cuáles usaste. La persona que lee tu respuesta NO LOS VE. No significan nada para ella.

        DOS ERRORES QUE NO PUEDES COMETER:

        1) Escribir "(fuente 3)" dentro del texto de la respuesta. El ciudadano lee eso y no sabe
           qué es. Es basura del andamiaje interno.

        2) MUCHO PEOR: confundir el número de fuente con un número de artículo.

           Si la fuente [23] es el Artículo 31, NO ESCRIBAS "artículo 23". Eso es una CITA FALSA
           con aspecto de correcta: alguien va a buscar el artículo 23, encontrará otra cosa, y
           habrá perdido el tiempo por culpa tuya. Es lo peor que puedes hacer.

        CÓMO SE CITA BIEN:

           Usa el número REAL que aparece en la cabecera de la fuente.

           Si la cabecera dice "[23] Artículo 31 · Ley de Hacienda", escribes "el artículo 31".
           NUNCA "el artículo 23".

           Y en el campo "fuentes" del JSON pones el 23, que es la etiqueta interna.

        Los dos números son distintos y sirven para cosas distintas. No los mezcles jamás.

        ══════════════════════════════════════════════════════════════════
        CADA MULTA, PEGADA A SU CONDUCTA. SUPUESTOS DISTINTOS, SEPARADOS.
        ══════════════════════════════════════════════════════════════════

        A veces las fuentes traen VARIOS artículos que aplican a SITUACIONES DISTINTAS. No los
        encadenes con "además" como si fueran una sola respuesta: eso confunde. Sepáralos, y pega
        cada cifra a la conducta que le corresponde.

        Ejemplo real de este sistema. A "¿cuánto es la multa por obstruir la banqueta?" llegan dos
        artículos que hablan de cosas distintas:

          · El artículo 65 del Bando sanciona PONER OBSTÁCULOS en la banqueta. El catálogo lo pone
            en Clase D, y la escala dice que la Clase D es de 31 a 100 UMA.
          · El artículo 154 de la Ley de Hacienda sanciona obstruir el paso a un peatón CON UN
            VEHÍCULO (materia de tránsito): 3 UMA.

        Son supuestos DISTINTOS, no un "además". Respuesta correcta:

          "Depende de qué situación sea. Si pusiste obstáculos en la banqueta, es el artículo 65
           del Bando: infracción Clase D, con multa de 31 a 100 UMA. Caso distinto: si obstruiste
           el paso a un peatón con un vehículo, eso es el artículo 154 de la Ley de Hacienda, con
           multa de 3 UMA."

        Fíjate: cada multa va JUNTO a su conducta. El 3 UMA no queda suelto en medio, y el 65 no
        arranca sin su cifra. Y los dos casos se presentan como alternativas ("depende", "caso
        distinto"), no como una suma.

        Esto NO es deducir: las dos cosas —que el 65 es Clase D, y que la Clase D es de 31 a 100
        UMA— están LITERALMENTE en las fuentes (el catálogo y la escala). Solo las presentas en
        orden. Si alguna de esas piezas NO estuviera en las fuentes, no la inventes.

        ══════════════════════════════════════════════════════════════════
        FUENTES DE OTRA JURISDICCIÓN
        ══════════════════════════════════════════════════════════════════

        Algunas fuentes pueden venir marcadas con "⚠ [FUERA DE JURISDICCIÓN]" en su cabecera. Son
        leyes de OTRO estado o municipio, que probablemente NO le apliquen a esta persona.

        Si respondes usando una fuente marcada así, tienes que ADVERTIRLO con claridad: empieza
        diciendo que esa disposición es de otra jurisdicción y podría no aplicarle. NUNCA la cites
        como si fuera la ley local.

        Si tienes una fuente local (sin la marca) que responde lo mismo, prefiere esa y no uses la
        de otra jurisdicción.
        TXT;
    }

    private function pregunta(string $consulta, array $fuentes, ?string $intencion): string
    {
        $bloques = collect($fuentes)->map(function ($f) {
            // La cabecera lleva el nombre de la regulación y la etiqueta del nodo tal cual
            // ("Artículo 65", "Inciso e"), sin anteponerle nada. El buscador ya la construye
            // completa: anteponer "artículo" produce cosas como "artículo Inciso e", que no
            // existen.
            // Se le da al modelo la cita COMPLETA ("Artículo 31, Fracción I"), no la etiqueta
            // suelta. Si el modelo ve "Fracción I", eso es lo que escribirá — y una cita que el
            // ciudadano no puede encontrar no sirve de nada.
            $cabecera = "[{$f['n']}] " . ($f['articulo'] ?: $f['titulo'] ?: $f['tipo']);

            if (! empty($f['fuente'])) {
                $cabecera .= ' · ' . $f['fuente'];
            }

            // Marca visible para el modelo: esta fuente es de OTRA jurisdicción (otro estado o
            // municipio). Llega solo cuando el ciudadano pidió "ver todo". El prompt le dice qué
            // hacer con ella; la garantía dura está en armarRespuesta().
            if (! empty($f['fuera_de_jurisdiccion'])) {
                $cabecera .= ' ⚠ [FUERA DE JURISDICCIÓN]';
            }

            // ── EL CONTEXTO: dónde vive este artículo dentro de la ley ──
            //
            // Sin esta línea, el modelo lee el artículo 26 —"Son objeto del Impuesto Predial, la
            // propiedad, usufructo, goce..."— y NO SABE que cuelga del capítulo "IMPUESTOS SOBRE
            // EL PATRIMONIO". Le estamos dando una página arrancada del libro.
            //
            // Y no es un detalle: hay preguntas que SOLO se pueden responder con la estructura.
            // "¿Cuáles son los impuestos al patrimonio?" no la responde ningún artículo — la
            // responde el ÁRBOL. Ningún artículo dice "los impuestos al patrimonio son estos
            // tres"; están juntos porque cuelgan del mismo capítulo.
            $bloque = $cabecera;

            if (! empty($f['contexto'])) {
                $bloque .= "\n   Ubicación en la ley: {$f['contexto']}";
            }

            return $bloque . "\n" . $f['texto'];
        })->implode("\n\n");

        $pista = match ($intencion) {
            'costo'      => 'La persona pregunta por un COSTO. Si las fuentes traen una cifra, dila con claridad.',
            'requisitos' => 'La persona pregunta por REQUISITOS. Si las fuentes los enumeran, enuméralos.',
            'fundamento' => 'La persona pregunta por el FUNDAMENTO JURÍDICO. Cita el artículo exacto.',
            'definicion' => 'La persona pregunta QUÉ ES algo. Explícalo en lenguaje sencillo.',
            default      => '',
        };

        $total = count($fuentes);

        return "PREGUNTA DE LA PERSONA:\n{$consulta}\n\n{$pista}\n\n"
            . "FUENTES ENCONTRADAS ({$total}). Muchas NO responden la pregunta: salieron por "
            . "compartir alguna palabra. Usa SOLO las que de verdad respondan, e ignora el resto.\n\n"
            . $bloques;
    }

    /**
     * SEGUNDO Y TERCER CANDADO: el formato y la verificación de las citas.
     *
     * Aquí es donde de verdad se impide que el asistente invente.
     */
    private function interpretar(?string $contenido, array $fuentes): ?array
    {
        if (empty($contenido)) {
            return null;
        }

        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            Log::warning('El asistente devolvió algo que no es JSON.', [
                'contenido' => Str::limit($contenido, 200),
            ]);

            return null;
        }

        // ── Candado 2: ¿qué de las TRES salidas eligió el modelo? ──
        //
        // 1) suficiente=true  + respuesta     → responde directamente.
        // 2) suficiente=false + relacionado   → NO responde, pero cuenta qué SÍ dicen las
        //                                        fuentes sobre el tema.
        // 3) suficiente=false + nada          → no hay nada. Se devuelve null y la pantalla
        //                                        enseña la lista de resultados a secas.
        //
        // La salida 2 es la que se añadió después, y responde a un caso real:
        //
        //   Preguntan "cuánto DURA el permiso de ambulantes". Las fuentes solo dicen que se cobra
        //   0.05 UMA por día. El modelo, correctamente, no puede responder.
        //
        //   Pero callarse del todo desperdicia información ÚTIL. "Se cobra por día" no dice cuánto
        //   dura, pero le dice muchísimo a quien pregunta — de hecho, casi se lo dice todo.
        //
        //   La clave está en QUIÉN saca la conclusión. Si el modelo dijera "por tanto la vigencia
        //   la elige el contribuyente", estaría DEDUCIENDO, y una deducción igual de lógica podría
        //   ser falsa ("como el pago puede ser mensual, el máximo son 30 días"). Nadie puede
        //   distinguirlas sin conocer el ayuntamiento por dentro.
        //
        //   Así que el modelo REPITE, y la persona DEDUCE. Su conclusión será suya, no del
        //   Ayuntamiento. Y si mañana aparece un reglamento con un tope de 90 días, el
        //   Ayuntamiento no habrá dicho nada falso.
        $esRespuestaDirecta = ! empty($datos['suficiente']) && ! empty($datos['respuesta']);
        $esRelacionado      = empty($datos['suficiente']) && ! empty($datos['relacionado']);

        if (! $esRespuestaDirecta && ! $esRelacionado) {
            return null; // salida 3: no hay nada que contar
        }

        $texto = $esRespuestaDirecta
            ? (string) $datos['respuesta']
            : (string) $datos['relacionado'];

        $citadas = array_filter(
            array_map('intval', (array) ($datos['fuentes'] ?? [])),
            fn ($n) => $n > 0
        );

        // ── Candado 3a: una respuesta sin citas se descarta ──
        //
        // Aunque el texto sea perfecto. Si el modelo no puede decir de DÓNDE lo sacó, es que
        // no lo sacó de ningún sitio: lo produjo. Y eso es exactamente lo que no queremos.
        if ($citadas === []) {
            Log::warning('El asistente respondió sin citar ninguna fuente. Se descarta.');

            return null;
        }

        // ── Candado 3b: una cita INVENTADA invalida TODA la respuesta ──
        //
        // Si el modelo cita la fuente [9] y solo se le dieron 6, se la inventó. Y no se
        // descarta solo esa cita: se descarta la respuesta ENTERA.
        //
        // Puede parecer excesivo. No lo es. Un modelo que inventa una cita también inventa el
        // contenido que le atribuye: no son dos fallos independientes, son el mismo fallo
        // asomando por dos sitios. Quedarse con "la parte buena" de una respuesta que ya
        // demostró estar inventando es exactamente el error que este servicio existe para
        // evitar.
        $numerosValidos = array_column($fuentes, 'n');
        $inventadas     = array_diff($citadas, $numerosValidos);

        if ($inventadas !== []) {
            Log::warning('El asistente citó fuentes que no existen. Se descarta la respuesta entera.', [
                'inventadas' => array_values($inventadas),
                'validas'    => $numerosValidos,
            ]);

            return null;
        }

        // ── CANDADO 4: quitar las fugas del andamiaje interno ──
        //
        // El modelo escribe a veces "(fuente 3)" dentro del texto. Esos números son etiquetas
        // internas —le sirven a él para decirme qué usó— y el ciudadano NO LOS VE en ningún
        // sitio. Leer "(fuente 3)" en una respuesta oficial es leer basura.
        //
        // Se limpian aquí y no solo en el prompt, porque un prompt es una sugerencia y esto es
        // una garantía. Ya sabemos que el modelo ignora instrucciones de vez en cuando.
        //
        // Lo que NO se puede limpiar automáticamente es el error grave: que llame "artículo 23"
        // a la fuente [23]. Eso es una cita FALSA con aspecto de correcta, y desde aquí es
        // indistinguible de una cita legítima al artículo 23 (que existe). Contra eso solo está
        // el prompt, y hay que vigilarlo leyendo respuestas reales.
        $texto = preg_replace('/\s*\((?:fuente|fuentes)\s+[\d,\s y]+\)/iu', '', $texto);
        $texto = trim(preg_replace('/\s{2,}/', ' ', $texto));

        if ($texto === '') {
            return null;
        }

        return $this->armarRespuesta($texto, $citadas, $fuentes, $esRespuestaDirecta);
    }

    /**
     * Arma la respuesta con la MISMA FORMA que las de FeaturedAnswerService.
     *
     * Es deliberado: la vista ya sabe pintar esa estructura, y no tiene que aprender una nueva.
     *
     * Lo único que cambia es `confianza`, que pasa a valer 'generada'. Ese campo NO es
     * decorativo: es lo que permite a la pantalla marcar la respuesta como REDACTADA POR UNA
     * IA, distinta de una definición legal curada por una persona.
     *
     * Si la vista no distingue las dos cosas, este servicio entero es un riesgo en vez de una
     * ayuda. Un ciudadano tiene derecho a saber si lo que está leyendo lo escribió el
     * Ayuntamiento o lo redactó una máquina.
     */
    private function armarRespuesta(string $texto, array $citadas, array $fuentes, bool $esDirecta = true): array
    {
        $porNumero = collect($fuentes)->keyBy('n');
        $usadas    = collect($citadas)->map(fn ($n) => $porNumero[$n])->filter()->values();

        $principal = $usadas->first();

        // ── RED DURA: el aviso de jurisdicción, por código ──
        //
        // Si la respuesta se apoya en una fuente de otra jurisdicción, se antepone el aviso AQUÍ,
        // sin depender de que el modelo obedeciera el prompt. El prompt es la capa blanda; esta es
        // la garantía. Se marca además la respuesta para que la vista pueda pintarla distinta.
        $usaOtraJurisdiccion = $usadas->contains(fn ($f) => $f['fuera_de_jurisdiccion'] ?? false);

        if ($usaOtraJurisdiccion) {
            $texto = 'Aviso: parte de esta respuesta se basa en una disposición de otra '
                   . 'jurisdicción (otro estado o municipio) que podría no aplicarte. Verifícalo '
                   . 'antes de actuar. ' . trim($texto);
        }

        return [
            'termino'    => null, // no es una definición de un término: es una respuesta
            'definicion' => trim($texto),

            // Marca que la respuesta usa al menos una fuente de otra jurisdicción, para que la
            // vista pueda señalarlo visualmente además del aviso ya incrustado en el texto.
            'fuera_de_jurisdiccion' => $usaOtraJurisdiccion,

            // La cita de la fuente principal. Sin esto, la respuesta no se puede publicar.
            'fuente'        => $principal['fuente']        ?? $principal['titulo'] ?? null,
            'articulo'      => $principal['articulo']      ?? null,
            'fraccion'      => $principal['fraccion']      ?? null,
            'regulacion_id' => $principal['regulacion_id'] ?? null,

            // 'generada'     → el modelo respondió la pregunta.
            // 'relacionada'  → NO la respondió, pero contó qué sí dicen las fuentes del tema.
            //
            // La pantalla los pinta distinto, y tiene que hacerlo: no es lo mismo "esto es lo que
            // pagas" que "no encontré cuánto dura, pero esto es lo que sí dice la ley". La segunda
            // es una respuesta a medias, y el ciudadano tiene que saberlo.
            'confianza' => $esDirecta ? 'generada' : 'relacionada',
            'motivo'    => $esDirecta
                ? 'Respuesta redactada automáticamente a partir de las regulaciones que el buscador '
                  . 'encontró. Revise las fuentes citadas.'
                : 'No se encontró una respuesta directa. Esto es lo que sí dicen las regulaciones '
                  . 'sobre el tema, sin deducir nada.',

            // Las fuentes adicionales llevan la MISMA cita completa. Si la principal dice
            // "Artículo 31, Fracción I" y las de abajo dicen "Fracción III" a secas, el ciudadano
            // solo puede comprobar una de cinco.
            'definiciones_adicionales' => $usadas->skip(1)->map(fn ($f) => [
                'fuente'        => $f['fuente'] ?? $f['titulo'],
                'articulo'      => $f['articulo'],
                'fraccion'      => $f['fraccion'],
                'regulacion_id' => $f['regulacion_id'],

                // Para que cada fuente citada se pueda abrir en el PDF oficial, en la
                // página donde está: comprobar una cita no debería costar una búsqueda.
                'pagina'        => $f['pagina']  ?? null,
                'pdf_url'       => $f['pdf_url'] ?? null,
            ])->values()->toArray(),
        ];
    }
}
