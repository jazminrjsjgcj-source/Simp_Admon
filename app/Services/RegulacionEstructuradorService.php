<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use Illuminate\Support\Facades\DB;

/**
 * Construye el árbol de nodos (regulacion_nodos) a partir del Markdown ya
 * convertido de una regulación. Es "mejor esfuerzo": detecta con seguridad los
 * encabezados estructurales (Título, Capítulo, Sección, Artículo) y, dentro del
 * cuerpo de cada artículo, intenta separar fracciones (I., II., ...) e incisos
 * (a), b), ...). Lo que no logre detectar, el jurídico lo ajusta a mano en el
 * editor.
 *
 * No modifica el Markdown ni el índice originales: solo crea nodos y marca la
 * regulación como `estructurada`.
 */
class RegulacionEstructuradorService
{
    public function __construct(
        private RegulacionConversorService $conversor,
    ) {}

    /**
     * Importa el articulado de una regulación al árbol de nodos. Si ya estaba
     * estructurada, primero limpia sus nodos para reconstruir desde cero
     * (idempotente: se puede re-ejecutar sin duplicar).
     *
     * @return int Número de nodos creados.
     */
    public function importarDesdeMarkdown(Regulacion $regulacion): int
    {
        $markdown = $this->conversor->obtenerContenidoMarkdown($regulacion);
        if (empty($markdown)) {
            return 0;
        }

        return DB::transaction(function () use ($regulacion, $markdown) {
            // Antes de borrar, guardar cuáles nodos estaban en papelera
            // (por tipo + número) para volver a enviarlos a papelera después
            // de reconstruir. Así el usuario no pierde su trabajo de limpieza.
            $setEnPapelera = [];
            RegulacionNodo::onlyTrashed()
                ->where('regulacion_id', $regulacion->id)
                ->whereNotNull('numero')
                ->select('tipo', 'numero')
                ->get()
                ->each(function ($n) use (&$setEnPapelera) {
                    $setEnPapelera[$n->tipo . ':' . $n->numero] = true;
                });

            // Reconstrucción limpia e idempotente: se borran TODOS los nodos
            // previos (vivos y en papelera) antes de reconstruir.
            //
            // Los nodos forman un árbol (parent_id apunta a otro nodo), así que un
            // DELETE masivo en una sola sentencia no respeta el orden padre→hijo y
            // choca con las llaves foráneas. En vez de desactivarlas con
            // SET FOREIGN_KEY_CHECKS (sintaxis exclusiva de MySQL), se borra de las
            // hojas hacia la raíz: primero los nodos sin hijos, luego sus padres.
            // Es portable a cualquier motor y no desactiva las FK ni un instante.
            $this->borrarArbolDeNodos($regulacion->id);

            // Antes de estructurar se quita la maquetación del documento: los
            // encabezados y pies que se repiten en cada página, y los números de
            // página sueltos. Al extraer un PDF esa basura viene mezclada con el
            // texto y se convertía en cientos de nodos sin contenido normativo.
            $markdown = $this->quitarMaquetacion($markdown);

            $markdown = $this->normalizarFraccionesInline($markdown);
            $lineas = preg_split('/\r\n|\r|\n/', $markdown);
            $creados = 0;

            // Contexto de inserción: el nodo "contenedor" actual de cada nivel.
            // Cuando aparece un encabezado, se cuelga del contenedor de nivel
            // superior más cercano y pasa a ser el nuevo contexto.
            $contenedorPorTipo = [
                RegulacionNodo::TIPO_TITULO   => null,
                RegulacionNodo::TIPO_CAPITULO => null,
                RegulacionNodo::TIPO_SECCION  => null,
                RegulacionNodo::TIPO_ARTICULO => null,
            ];

            // Orden incremental por padre (clave: parent_id|raiz).
            $ordenPorPadre = [];

            $siguienteOrden = function (?int $parentId) use (&$ordenPorPadre): int {
                $clave = $parentId === null ? 'raiz' : (string) $parentId;
                $ordenPorPadre[$clave] = ($ordenPorPadre[$clave] ?? 0) + 1;
                return $ordenPorPadre[$clave];
            };

            $articuloActual = null; // para colgar fracciones/incisos/párrafos
            $fraccionActual = null; // última fracción abierta (para colgar incisos)
            $ultimoNodoLista = null; // última fracción/inciso creado (para anexar su continuación)

            // Buffer de párrafo: el texto corrido del PDF llega partido en muchas
            // líneas (saltos a media oración por el formato en columnas). En vez
            // de crear un párrafo por línea, acumulamos las líneas y las unimos en
            // UN solo párrafo, que se vuelca ("flush") cuando aparece un corte
            // real: un encabezado, una fracción, un inciso o el fin del documento.
            $bufferParrafo = [];
            $parentParrafo = null; // padre al que pertenece el párrafo en curso

            // Vuelca el buffer acumulado como un único nodo párrafo (si hay algo).
            $volcarParrafo = function () use (&$bufferParrafo, &$parentParrafo, $regulacion, $siguienteOrden, &$creados) {
                if (empty($bufferParrafo)) {
                    return;
                }
                // Se une con salto de línea, no con espacio, por la misma razón
                // documentada en el bloque de "continuación" más abajo: preservar
                // renglones independientes (listas sin marcador, tablas aplanadas)
                // en vez de fusionarlos en una sola oración corrida. Las vistas
                // de lectura ya usan white-space:pre-line.
                $texto = trim(implode("\n", $bufferParrafo));
                $bufferParrafo = [];
                if ($texto === '') {
                    return;
                }
                $regulacion->nodos()->create([
                    'parent_id' => $parentParrafo,
                    'tipo'      => RegulacionNodo::TIPO_PARRAFO,
                    'numero'    => null,
                    'texto'     => $texto,
                    'orden'     => $siguienteOrden($parentParrafo),
                    'estado'    => RegulacionNodo::ESTADO_VIGENTE,
                ]);
                $creados++;
            };

            foreach ($lineas as $linea) {
                $texto = trim($linea);
                if ($texto === '' || $this->esRuidoDeCabecera($texto)) {
                    continue;
                }

                $encabezado = $this->detectarEncabezado($texto);

                if ($encabezado !== null) {
                    $volcarParrafo(); // cierra el párrafo anterior antes del encabezado
                    [$tipo, $numero, $cuerpo] = $encabezado;

                    $parentId = $this->padreParaEncabezado($tipo, $contenedorPorTipo);
                    $nodo = $regulacion->nodos()->create([
                        'parent_id' => $parentId,
                        'tipo'      => $tipo,
                        'numero'    => $numero,
                        'texto'     => $cuerpo ?: null,
                        'orden'     => $siguienteOrden($parentId),
                        'estado'    => RegulacionNodo::ESTADO_VIGENTE,
                    ]);
                    $creados++;

                    // Actualizar contexto: este nodo es el nuevo contenedor de su
                    // tipo, y se invalidan los niveles inferiores.
                    $this->actualizarContexto($contenedorPorTipo, $tipo, $nodo->id);
                    $articuloActual = ($tipo === RegulacionNodo::TIPO_ARTICULO) ? $nodo : $articuloActual;
                    $fraccionActual = null; // un encabezado nuevo cierra la fracción abierta
                    // El texto del encabezado del artículo suele venir partido en
                    // varias líneas; se permite anexarle su continuación hasta que
                    // aparezca una fracción, inciso o nuevo encabezado.
                    $ultimoNodoLista = ($tipo === RegulacionNodo::TIPO_ARTICULO) ? $nodo : null;
                    if ($tipo !== RegulacionNodo::TIPO_ARTICULO
                        && in_array($tipo, [RegulacionNodo::TIPO_TITULO, RegulacionNodo::TIPO_CAPITULO, RegulacionNodo::TIPO_SECCION], true)) {
                        $articuloActual = null;
                    }
                    continue;
                }

                // No es encabezado: dentro de un artículo puede ser fracción,
                // inciso o párrafo; fuera de un artículo, es un párrafo suelto.
                if ($articuloActual !== null) {
                    $fr = $this->detectarFraccion($texto);
                    if ($fr !== null) {
                        $volcarParrafo(); // la fracción corta el párrafo en curso
                        [$num, $cuerpo] = $fr;
                        $nodoFraccion = $regulacion->nodos()->create([
                            'parent_id' => $articuloActual->id,
                            'tipo'      => RegulacionNodo::TIPO_FRACCION,
                            'numero'    => $num,
                            'texto'     => $cuerpo ?: null,
                            'orden'     => $siguienteOrden($articuloActual->id),
                            'estado'    => RegulacionNodo::ESTADO_VIGENTE,
                        ]);
                        $fraccionActual = $nodoFraccion; // los incisos siguientes cuelgan de aquí
                        $ultimoNodoLista = $nodoFraccion; // su continuación se anexa aquí
                        $creados++;
                        continue;
                    }

                    $inc = $this->detectarInciso($texto);
                    if ($inc !== null) {
                        $volcarParrafo(); // el inciso corta el párrafo en curso
                        [$num, $cuerpo] = $inc;
                        // El inciso cuelga de la fracción que lo precede; si no hay
                        // fracción abierta, cuelga directamente del artículo.
                        $padreInciso = $fraccionActual?->id ?? $articuloActual->id;
                        $nodoInciso = $regulacion->nodos()->create([
                            'parent_id' => $padreInciso,
                            'tipo'      => RegulacionNodo::TIPO_INCISO,
                            'numero'    => $num,
                            'texto'     => $cuerpo ?: null,
                            'orden'     => $siguienteOrden($padreInciso),
                            'estado'    => RegulacionNodo::ESTADO_VIGENTE,
                        ]);
                        $ultimoNodoLista = $nodoInciso; // su continuación se anexa aquí
                        $creados++;
                        continue;
                    }
                }

                // Línea de texto corrido sin marcador de fracción/inciso.
                //
                // ANTES: se pegaba con un espacio, asumiendo que el PDF había
                // cortado una oración a media palabra por el ancho de columna
                // ("que\ndeseen" -> "que deseen"). Esto funciona bien para
                // prosa legal normal, pero destruye la estructura cuando el
                // contenido real es una lista de renglones independientes sin
                // marcador (tablas de sanciones, tarifarios, listados aplanados
                // desde Word/PDF) -> todos los renglones terminaban fusionados
                // en una sola oración corrida, ilegible.
                //
                // AHORA: se une con salto de línea (\n) para conservar cada
                // renglón por separado. Las vistas de lectura del articulado
                // (nodo-lectura.blade.php, nodo-lectura-editor.blade.php) ya
                // usan white-space:pre-line, así que estos saltos se muestran
                // correctamente sin tocar ninguna vista.
                //
                // Trade-off conocido: si un artículo tiene una oración
                // genuinamente cortada a media palabra por columnas del PDF
                // original, ahora se mostrará con un salto de línea en medio
                // en vez de fusionarse en una sola línea continua. El sistema
                // no puede distinguir automáticamente ambos casos sin analizar
                // el sentido del texto.
                if ($ultimoNodoLista !== null) {
                    $ultimoNodoLista->texto = trim(($ultimoNodoLista->texto ?? '') . "\n" . $texto);
                    $ultimoNodoLista->save();
                    continue;
                }

                // Sin lista abierta: se acumula en el buffer de párrafo. El padre
                // es el contenedor más específico disponible en este punto.
                $parentActual = $articuloActual?->id
                    ?? $this->contenedorMasProfundo($contenedorPorTipo);

                // Si cambió el contenedor destino, primero cierra el párrafo previo.
                if (!empty($bufferParrafo) && $parentParrafo !== $parentActual) {
                    $volcarParrafo();
                }
                $parentParrafo = $parentActual;
                $bufferParrafo[] = $texto;
            }

            // Volcar el último párrafo pendiente al terminar el documento.
            $volcarParrafo();

            // Re-enviar a papelera los nodos que estaban ahí antes de
            // re-estructurar. Se identifica cada uno por tipo + número
            // (los IDs cambiaron al reconstruir, pero el par tipo:número
            // es estable). Así el usuario no pierde su trabajo de limpieza.
            if (!empty($setEnPapelera)) {
                $regulacion->nodos()
                    ->whereNotNull('numero')
                    ->get()
                    ->each(function ($nodo) use ($setEnPapelera) {
                        $clave = $nodo->tipo . ':' . $nodo->numero;
                        if (isset($setEnPapelera[$clave])) {
                            $nodo->delete(); // soft delete → papelera
                        }
                    });
            }

            $regulacion->update(['estructurada' => true]);

            return $creados;
        });
    }

    /**
     * Elimina TODAS las marcas de formato inline (Markdown y HTML) de una
     * línea de texto, para que los detectores de estructura (encabezado,
     * fracción, inciso) trabajen con texto plano sin importar cómo estaba
     * formateado en el documento original.
     *
     * Cubre: negrita, cursiva, negrita+cursiva, tachado, código inline,
     * y etiquetas HTML residuales (b, em, u, sub, sup, s, del, strong, code).
     */
    private function limpiarFormatoInline(string $texto): string
    {
        // 1. Quitar etiquetas HTML que algunos conversores preservan.
        $texto = strip_tags($texto);

        // 2. Marcas Markdown: más largas primero para no dejar residuos.
        $texto = str_replace(['***', '___', '**', '__', '~~', '`'], '', $texto);

        // 3. Cursiva con * o _ sueltos: solo si envuelven toda la línea.
        $texto = preg_replace('/^\*(.+)\*$/u', '$1', $texto);
        $texto = preg_replace('/^_(.+)_$/u', '$1', $texto);

        // 4. Marcadores de lista Markdown (- , * , + , 1. , 2. , etc.)
        //    que preceden fracciones romanas o incisos.
        //    Sin esto, "- I. Habilitar..." no se detecta como fracción
        //    porque el regex espera el romano al inicio de la línea.
        $texto = preg_replace('/^[\-\*\+]\s+/', '', $texto);
        $texto = preg_replace('/^\d+\.\s+/', '', $texto);

        return trim($texto);
    }

    /**
     * Detecta un encabezado estructural. Devuelve [tipo, numero, cuerpo] o null.
     * "Artículo 5. Las personas..." -> [articulo, '5', 'Las personas...'].
     * "CAPÍTULO III" -> [capitulo, 'III', ''].
     */
    private function detectarEncabezado(string $texto): ?array
    {
        // Quitar encabezado Markdown (#, ##, ...) y todo formato inline.
        $limpio = preg_replace('/^#{1,6}\s+/', '', $texto);
        $limpio = $this->limpiarFormatoInline($limpio);

        // Artículo N (acepta "Artículo 5.", "ARTÍCULO 5 BIS", "Artículo 3", etc.).
        //
        // El separador después del número (punto, guión, etc.) es OPCIONAL
        // (cuantificador *), no obligatorio. Documentos como la LNETB usan
        // encabezados Markdown sin punto después del número ("##### Artículo 3"),
        // que después de quitar los ### quedan como "Artículo 3" sin nada
        // después del número. Con el separador obligatorio (+), esos 114
        // artículos no se reconocían como encabezados y todas sus fracciones
        // terminaban apiladas como texto corrido en el buffer de párrafos.
        //
        // No hay riesgo de falso positivo: "Artículo" seguido de un número
        // AL INICIO de una línea siempre es un encabezado en la técnica
        // legislativa mexicana — la prosa normal dice "el artículo 5" con
        // minúscula y nunca al inicio de línea.
        if (preg_match('/^art[íi]culo\s+(\d+[°º\sA-Za-z]*?)[\.\-–\)]*\s*(.*)$/iu', $limpio, $m)) {
            return [RegulacionNodo::TIPO_ARTICULO, trim($m[1]), trim($m[2])];
        }

        // Artículo con ordinal en palabras: "ARTÍCULO PRIMERO.", "Artículo Segundo.-"
        // Los artículos transitorios y algunos decretos usan palabras (PRIMERO,
        // SEGUNDO) en vez de números (1, 2). Sin este regex, todo el bloque de
        // transitorios se acumula en un solo párrafo gigante que desborda la
        // columna texto (error 1406: Data too long).
        if (preg_match('/^art[íi]culo\s+(primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nico)[\.\-–\)\s]+(.*)$/iu', $limpio, $m)) {
            return [RegulacionNodo::TIPO_ARTICULO, trim($m[1]), trim($m[2])];
        }

        // ── Título ───────────────────────────────────────────────────────
        // Nivel 1: identificador reconocible (romano o palabra ordinal/especial).
        // Captura solo el identificador en `numero` y el nombre descriptivo en `texto`.
        // Ejemplo: "TÍTULO I — Disposiciones Generales" → numero="I", texto="Disposiciones Generales"
        if (preg_match(
            '/^t[íi]tulo\s+' .
            '(primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nico|preliminar|final|general|[IVXLCDM]+\b)' .
            '[\s.\-–—]*(.*)$/iu',
            $limpio, $m
        )) {
            return [RegulacionNodo::TIPO_TITULO, mb_strtoupper(trim($m[1])), trim($m[2])];
        }

        // Nivel 2: título sin identificador estándar (p. ej. "TÍTULO DE LA RESPONSABILIDAD").
        // Solo se acepta si el texto no empieza con minúscula: "título sea descubierta..."
        // empieza con "s" → es prosa dentro de un artículo, no un encabezado → se rechaza.
        // Se trunca defensivamente a LARGO_MAXIMO_NUMERO para garantizar que no explota el insert.
        if (preg_match('/^t[íi]tulo\s+(.+)$/iu', $limpio, $m)) {
            $id = trim($m[1]);
            if (!preg_match('/^[a-záéíóúñü]/u', $id)) {
                return [RegulacionNodo::TIPO_TITULO, $this->truncarNumero($id), ''];
            }
            // Comienza con minúscula: es prosa, no un encabezado. Ignorar.
        }

        // ── Capítulo ─────────────────────────────────────────────────────
        // Misma estrategia de dos niveles que el título.
        if (preg_match(
            '/^cap[íi]tulo\s+' .
            '(primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nico|[IVXLCDM]+\b)' .
            '[\s.\-–—]*(.*)$/iu',
            $limpio, $m
        )) {
            return [RegulacionNodo::TIPO_CAPITULO, mb_strtoupper(trim($m[1])), trim($m[2])];
        }
        if (preg_match('/^cap[íi]tulo\s+(.+)$/iu', $limpio, $m)) {
            $id = trim($m[1]);
            if (!preg_match('/^[a-záéíóúñü]/u', $id)) {
                return [RegulacionNodo::TIPO_CAPITULO, $this->truncarNumero($id), ''];
            }
        }

        // ── Sección ──────────────────────────────────────────────────────
        if (preg_match(
            '/^secci[óo]n\s+' .
            '(primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nica?|[IVXLCDM]+\b)' .
            '[\s.\-–—]*(.*)$/iu',
            $limpio, $m
        )) {
            return [RegulacionNodo::TIPO_SECCION, mb_strtoupper(trim($m[1])), trim($m[2])];
        }
        if (preg_match('/^secci[óo]n\s+(.+)$/iu', $limpio, $m)) {
            $id = trim($m[1]);
            if (!preg_match('/^[a-záéíóúñü]/u', $id)) {
                return [RegulacionNodo::TIPO_SECCION, $this->truncarNumero($id), ''];
            }
        }
        if (preg_match('/^(transitorios?)\b(.*)$/iu', $limpio, $m)) {
            // Los transitorios se modelan como un capítulo contenedor.
            return [RegulacionNodo::TIPO_CAPITULO, 'Transitorios', trim($m[2])];
        }

        return null;
    }

    /** Fracción: "I. texto", "II.- texto", "XIV.texto", "I.-Accidente", "I." */
    private function detectarFraccion(string $texto): ?array
    {
        // Romanos en mayúscula al inicio, seguidos de uno o más separadores
        // (punto, guión, guión largo, paréntesis) con espacio opcional.
        // Cubre: "I. Habilitar", "I.-Accidente", "II.Agente", "XIV.) texto", "I."
        // La parte del texto (.*)$ es opcional: el romano puede estar solo en la
        // línea si la normalización partió el texto después del punto.
        $texto = $this->limpiarFormatoInline($texto);
        // El \s* inicial permite que la fracción venga con sangría, como pasa en
        // los PDF con maquetación ("    IV. La fusión de sociedades;").
        if (preg_match('/^\s*([IVXLCDM]+)\s*[\.\-–—\)]+\s*(.*)$/u', $texto, $m)) {
            $romano = trim($m[1]);
            // Validar que sea un número romano canónico (rechaza "MIL", "IIII",
            // "VV", etc. que podrían colar palabras en mayúscula con puntuación).
            if ($this->esRomanoCanonico($romano)) {
                $cuerpo = trim($m[2]);
                return [$romano, $cuerpo !== '' ? $cuerpo : null];
            }
        }
        return null;
    }

    /**
     * Pre-procesamiento: inserta saltos de línea antes de cada fracción romana
     * o inciso que aparezca inline (mid-párrafo) después de un separador.
     *
     * Los documentos legales mexicanos a menudo listan fracciones en un solo
     * párrafo separadas por punto y coma:
     *
     *   "...las siguientes: I. Simplificación; II. Digitalización; III. Atención"
     *
     * El estructurador necesita que cada fracción empiece al inicio de una línea
     * para que el regex `^([IVXLCDM]+)` la detecte. Este método normaliza el
     * texto insertando `\n` antes de cada patrón de fracción o inciso que esté
     * precedido por un separador (`;`, `,`, `:`, `)`) + espacio.
     *
     * No inserta `\n` al inicio de la línea (la primera fracción ya está al
     * inicio) ni cuando el romano está dentro de una palabra ("CIVIL" no se
     * parte, porque no está precedido por un separador + espacio).
     */
    private function normalizarFraccionesInline(string $markdown): string
    {
        // Las fracciones (I, II, III...) y los incisos (a, b, c...) de una ley van
        // en SECUENCIA. Esa pista es la que hace fiable el corte: en vez de adivinar
        // por la puntuación que los precede —lo que dejaba fracciones e incisos sin
        // detectar cuando el documento venía con el texto corrido en un párrafo— se
        // busca el SIGUIENTE marcador esperado.
        //
        // Así funciona igual aunque el texto venga mal formateado (que es como suele
        // quedar al extraerlo de un PDF), sin depender de que cada fracción empiece
        // en su propia línea.
        return $this->separarMarcadores($markdown);
    }

    /** Romanos válidos como número de fracción (I a XL cubre de sobra). */
    private const ROMANOS_FRACCION = [
        'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
        'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX',
        'XXI', 'XXII', 'XXIII', 'XXIV', 'XXV', 'XXVI', 'XXVII', 'XXVIII', 'XXIX', 'XXX',
        'XXXI', 'XXXII', 'XXXIII', 'XXXIV', 'XXXV', 'XXXVI', 'XXXVII', 'XXXVIII', 'XXXIX', 'XL',
    ];

    /**
     * Inserta un salto de línea antes de cada fracción e inciso, siguiendo su
     * secuencia (I, II, III... y a, b, c...). Recorre el texto línea por línea.
     *
     * Reglas de reinicio, que reflejan la jerarquía de una ley:
     *   - Un encabezado (Artículo, Capítulo...) reinicia fracciones e incisos.
     *   - Una fracción nueva reinicia los incisos (cada fracción empieza en "a").
     */
    private function separarMarcadores(string $markdown): string
    {
        $resultado     = [];
        $ultimaFraccion = -1; // índice del último romano aceptado
        $ultimoInciso   = -1; // índice de la última letra aceptada

        foreach (preg_split('/\R/u', $markdown) as $linea) {
            // Un encabezado reinicia la numeración; aun así se le buscan marcadores
            // DENTRO, por si el artículo y sus fracciones vinieron en la misma línea.
            if ($this->pareceEncabezado($linea)) {
                $ultimaFraccion = -1;
                $ultimoInciso   = -1;
            }

            [$piezas, $ultimaFraccion, $ultimoInciso] =
                $this->cortarLinea($linea, $ultimaFraccion, $ultimoInciso);

            foreach ($piezas as $pieza) {
                $resultado[] = $pieza;
            }
        }

        return implode("\n", $resultado);
    }

    /**
     * Parte una línea en sus fracciones e incisos, en una sola pasada y respetando
     * el orden en que aparecen. Devuelve las piezas y los índices actualizados, para
     * que la secuencia continúe en la línea siguiente.
     */
    private function cortarLinea(string $linea, int $ultimaFraccion, int $ultimoInciso): array
    {
        $romanos = array_flip(self::ROMANOS_FRACCION);
        $letras  = array_flip(range('a', 'z'));

        // Se recogen todos los marcadores candidatos de la línea, con su posición.
        $marcas = [];

        // Fracciones: romano + separador + espacio + texto.
        if (preg_match_all(
            '/(?<![A-ZÁÉÍÓÚÑ0-9])([IVXLCDM]+)\s*[\.\-–—\)]+\s+(?=\S)/u',
            $linea, $hallazgos, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            foreach ($hallazgos as $m) {
                $romano = $m[1][0];
                $inicio = $m[0][1];
                if (! isset($romanos[$romano]) || $this->esSigla($linea, $inicio)) {
                    continue;
                }
                $marcas[] = ['pos' => $inicio, 'tipo' => 'fraccion', 'idx' => $romanos[$romano]];
            }
        }

        // Incisos: letra minúscula suelta + separador + espacio + texto.
        if (preg_match_all(
            '/(?<![a-záéíóúñA-ZÁÉÍÓÚÑ0-9])([a-z])\s*[\)\.\-–—]+\s+(?=\S)/u',
            $linea, $hallazgos, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            foreach ($hallazgos as $m) {
                $letra  = $m[1][0];
                $inicio = $m[0][1];
                if (! isset($letras[$letra])) {
                    continue;
                }
                $marcas[] = ['pos' => $inicio, 'tipo' => 'inciso', 'idx' => $letras[$letra]];
            }
        }

        // Se procesan en el orden en que aparecen en la línea.
        usort($marcas, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        $piezas = [];
        $pos    = 0;

        foreach ($marcas as $marca) {
            if ($marca['pos'] < $pos) {
                continue; // ya quedó dentro de un corte anterior
            }

            if ($marca['tipo'] === 'fraccion') {
                // Se acepta si continúa la secuencia, o si es la primera que aparece.
                if ($marca['idx'] === $ultimaFraccion + 1 || $ultimaFraccion === -1) {
                    $piezas[]       = rtrim(substr($linea, $pos, $marca['pos'] - $pos));
                    $pos            = $marca['pos'];
                    $ultimaFraccion = $marca['idx'];
                    $ultimoInciso   = -1; // fracción nueva: los incisos empiezan en "a"
                }
            } else {
                // Inciso: se acepta si sigue la secuencia, o si es la "a" inicial.
                if ($marca['idx'] === $ultimoInciso + 1 || ($ultimoInciso === -1 && $marca['idx'] === 0)) {
                    $piezas[]     = rtrim(substr($linea, $pos, $marca['pos'] - $pos));
                    $pos          = $marca['pos'];
                    $ultimoInciso = $marca['idx'];
                }
            }
        }

        $piezas[] = substr($linea, $pos);

        $piezas = array_values(array_filter(
            $piezas,
            fn (string $p) => trim($p) !== ''
        ));

        return [$piezas, $ultimaFraccion, $ultimoInciso];
    }

    /**
     * ¿El romano forma parte de una sigla en mayúsculas (y por tanto NO es una
     * fracción)? Una fracción va precedida de puntuación o del inicio de la línea;
     * una sigla va pegada a otra palabra en mayúsculas ("CODIGO CIVIL").
     */
    private function esSigla(string $linea, int $inicio): bool
    {
        $antes = rtrim(substr($linea, 0, $inicio));

        if ($antes === '') {
            return false; // al inicio de la línea: es fracción
        }
        if (preg_match('/[;:,\.\)\-–—]$/u', $antes)) {
            return false; // precedida de puntuación: es fracción
        }

        return (bool) preg_match('/[A-ZÁÉÍÓÚÑ]{2,}$/u', $antes);
    }

    /**
     * ¿La línea es el encabezado de un artículo, capítulo, título...? Se usa para
     * reiniciar la numeración (cada artículo empieza de nuevo en la fracción I).
     */
    private function pareceEncabezado(string $linea): bool
    {
        return (bool) preg_match(
            '/^\s*#{0,6}\s*(art[íi]culo|cap[íi]tulo|t[íi]tulo|secci[óo]n|libro|transitori)/iu',
            $linea
        );
    }

    /** Inciso: "a) texto", "b.- texto", "a)Que se admita". */
    private function detectarInciso(string $texto): ?array
    {
        $texto = $this->limpiarFormatoInline($texto);
        // Una letra minúscula + uno o más separadores + el texto del inciso.
        //
        // El \s* inicial es imprescindible: en los PDF los incisos vienen con
        // sangría ("    a)   En el acto..."). Sin él, el ^ exigía la letra en la
        // primera columna y esos incisos no se detectaban.
        if (preg_match('/^\s*([a-z])\s*[\)\.\-–—]+\s*(\S.*)$/u', $texto, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        return null;
    }

    /** Verifica que una cadena sea un número romano canónico (1-3999). */
    private function esRomanoCanonico(string $s): bool
    {
        if ($s === '' || !preg_match('/^[IVXLCDM]+$/', $s)) {
            return false;
        }
        return (bool) preg_match(
            '/^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/',
            $s
        );
    }

    /** Padre correcto para un encabezado, según el contenedor abierto superior. */
    private function padreParaEncabezado(string $tipo, array $contenedorPorTipo): ?int
    {
        return match ($tipo) {
            RegulacionNodo::TIPO_TITULO   => null,
            RegulacionNodo::TIPO_CAPITULO => $contenedorPorTipo[RegulacionNodo::TIPO_TITULO],
            RegulacionNodo::TIPO_SECCION  => $contenedorPorTipo[RegulacionNodo::TIPO_CAPITULO]
                                              ?? $contenedorPorTipo[RegulacionNodo::TIPO_TITULO],
            RegulacionNodo::TIPO_ARTICULO => $contenedorPorTipo[RegulacionNodo::TIPO_SECCION]
                                              ?? $contenedorPorTipo[RegulacionNodo::TIPO_CAPITULO]
                                              ?? $contenedorPorTipo[RegulacionNodo::TIPO_TITULO],
            default => null,
        };
    }

    /** Actualiza el contenedor activo de cada tipo e invalida los inferiores. */
    private function actualizarContexto(array &$contenedorPorTipo, string $tipo, int $nodoId): void
    {
        $jerarquia = [
            RegulacionNodo::TIPO_TITULO,
            RegulacionNodo::TIPO_CAPITULO,
            RegulacionNodo::TIPO_SECCION,
            RegulacionNodo::TIPO_ARTICULO,
        ];
        $pos = array_search($tipo, $jerarquia, true);
        if ($pos === false) {
            return;
        }
        $contenedorPorTipo[$tipo] = $nodoId;
        // Invalidar niveles inferiores (un nuevo capítulo reinicia sección/artículo).
        for ($i = $pos + 1; $i < count($jerarquia); $i++) {
            $contenedorPorTipo[$jerarquia[$i]] = null;
        }
    }

    /** El contenedor abierto más profundo (para colgar párrafos sueltos). */
    private function contenedorMasProfundo(array $contenedorPorTipo): ?int
    {
        foreach ([
            RegulacionNodo::TIPO_SECCION,
            RegulacionNodo::TIPO_CAPITULO,
            RegulacionNodo::TIPO_TITULO,
        ] as $tipo) {
            if (!empty($contenedorPorTipo[$tipo])) {
                return $contenedorPorTipo[$tipo];
            }
        }
        return null; // párrafo a nivel raíz de la regulación
    }

    /**
     * Trunca un valor al límite de la columna `numero` (VARCHAR(60)).
     *
     * Es una red de seguridad defensiva: si algún regex captura más texto del
     * esperado, este método garantiza que el INSERT nunca revienta con
     * SQLSTATE[22001] (Data too long for column 'numero').
     *
     * Usa mb_substr para no partir caracteres multibyte (á, é, ñ, ü...).
     */
    private function truncarNumero(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        return mb_substr($valor, 0, RegulacionNodo::LARGO_MAXIMO_NUMERO);
    }

    /** Líneas de la cabecera autogenerada del conversor que no son articulado. */
    private function esRuidoDeCabecera(string $texto): bool
    {
        $patrones = ['Regulación generada automáticamente', '**Tipo:**', '**Fecha', '**Dependencia'];
        foreach ($patrones as $p) {
            if (str_contains($texto, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Borra todos los nodos de una regulación respetando la jerarquía: en cada
     * vuelta elimina las hojas (los nodos que no son padres de nadie), hasta que
     * no queda ninguno. Así nunca se intenta borrar un padre antes que sus hijos,
     * que es lo que chocaba con las llaves foráneas.
     */
    private function borrarArbolDeNodos(int $regulacionId): void
    {
        // Tope de seguridad: el árbol nunca debería ser tan profundo, pero evita
        // un bucle infinito si los datos quedaran inconsistentes.
        for ($vuelta = 0; $vuelta < 100; $vuelta++) {
            $base = fn () => RegulacionNodo::withTrashed()->where('regulacion_id', $regulacionId);

            if (! $base()->exists()) {
                return; // el árbol ya quedó vacío
            }

            // Nodos que todavía figuran como padre de alguien.
            $padres = $base()->whereNotNull('parent_id')->pluck('parent_id')->unique();

            // Las hojas son las que no aparecen como padre de nadie.
            $hojas = $base()->when(
                $padres->isNotEmpty(),
                fn ($q) => $q->whereNotIn('id', $padres)
            );

            if (! $hojas->exists()) {
                // Sin hojas identificables (datos inconsistentes): se borra el resto
                // de una vez, para no dejar la reconstrucción a medias.
                $base()->forceDelete();
                return;
            }

            $hojas->forceDelete();
        }
    }

    /** Cuántas veces debe repetirse una línea para considerarla maquetación. */
    private const REPETICIONES_MAQUETACION = 4;

    /**
     * Quita del texto la maquetación del documento: los encabezados y pies que se
     * repiten en cada página ("LEY DE HACIENDA...", "H. Congreso del Estado...",
     * "Oficialía Mayor") y los números de página sueltos.
     *
     * El criterio es la REPETICIÓN: el texto de un artículo aparece una vez, pero
     * un encabezado de página aparece en las cien páginas. No hace falta conocer el
     * documento: se detecta solo, sea la ley que sea.
     *
     * Salvaguarda: una línea con marcador de estructura (artículo, fracción, inciso)
     * NUNCA se borra, aunque se repita. Una ley puede decir "I. La solicitud;" en
     * varios artículos y eso es contenido legítimo, no maquetación.
     */
    private function quitarMaquetacion(string $markdown): string
    {
        $lineas = preg_split('/\R/u', $markdown);

        // 1) Contar cuántas veces aparece cada línea (ignorando la sangría).
        $conteo = [];
        foreach ($lineas as $linea) {
            $clave = trim($linea);
            if ($clave === '') {
                continue;
            }
            $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
        }

        // 2) Las que se repiten mucho y no son contenido: son maquetación.
        $maquetacion = [];
        foreach ($conteo as $texto => $veces) {
            if ($veces >= self::REPETICIONES_MAQUETACION && ! $this->esLineaDeContenido((string) $texto)) {
                $maquetacion[(string) $texto] = true;
            }
        }

        // 3) Reconstruir el texto sin la maquetación ni los números de página.
        $limpias = [];
        foreach ($lineas as $linea) {
            $clave = trim($linea);

            if ($clave === '') {
                $limpias[] = $linea; // las líneas en blanco separan párrafos
                continue;
            }
            if (isset($maquetacion[$clave])) {
                continue; // encabezado o pie de página
            }
            if (preg_match('/^\d{1,4}$/', $clave)) {
                continue; // número de página suelto
            }

            $limpias[] = $linea;
        }

        return implode("\n", $limpias);
    }

    /**
     * ¿La línea lleva un marcador de estructura legal (artículo, capítulo, fracción,
     * inciso...)? Esas líneas son contenido y no se descartan aunque se repitan.
     */
    private function esLineaDeContenido(string $linea): bool
    {
        // Encabezados: Artículo, Capítulo, Título, Sección, Libro, Transitorios.
        if (preg_match('/^\s*#{0,6}\s*(art[íi]culo|cap[íi]tulo|t[íi]tulo|secci[óo]n|libro|transitori)\b/iu', $linea)) {
            return true;
        }

        // Fracción: romano en mayúscula + separador + espacio ("VII. La...").
        if (preg_match('/^\s*[IVXLCDM]+\s*[\.\-–—\)]+\s/u', $linea)) {
            return true;
        }

        // Inciso: letra MINÚSCULA + separador + espacio ("a) En el acto...").
        // Se exige minúscula a propósito: así una línea como "H. Congreso del
        // Estado..." (pie de página) no se confunde con un inciso.
        if (preg_match('/^\s*[a-záéíóúñ]\s*[\)\.\-–—]+\s/u', $linea)) {
            return true;
        }

        return false;
    }
}
