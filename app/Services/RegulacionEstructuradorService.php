<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            // Se desactivan temporalmente las llaves foráneas porque parent_id
            // tiene cascadeOnDelete: un DELETE masivo del árbol en una sola
            // sentencia no respeta el orden padre→hijo y choca con las FK,
            // dejando la mayoría de las filas sin borrar (era la causa de que
            // cada re-estructuración apilara otra copia en vez de reemplazar).
            // El try/finally garantiza que las FK se reactiven pase lo que pase.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                RegulacionNodo::withTrashed()
                    ->where('regulacion_id', $regulacion->id)
                    ->forceDelete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

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
            '([IVXLCDM]+|primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nico|preliminar|final|general)' .
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
            '([IVXLCDM]+|primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nico)' .
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
            '([IVXLCDM]+|primer[oa]|segund[oa]|tercer[oa]|cuart[oa]|quint[oa]|sext[oa]|s[eé]ptim[oa]|octav[oa]|noven[oa]|d[eé]cim[oa]|und[eé]cim[oa]|duod[eé]cim[oa]|[uú]nica?)' .
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
        if (preg_match('/^([IVXLCDM]+)\s*[\.\-–—\)]+\s*(.*)$/u', $texto, $m)) {
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
        // 1. Fracción romana inline después de separador seguro.
        //    "...servicios; II. Generar..." → "...servicios;\nII. Generar..."
        //    Separadores: ; , : ) — los 4 más seguros. El punto (.) NO se
        //    incluye aquí porque causa falsos positivos con siglas en
        //    mayúsculas como "CIVIL." o "MUNICIPAL." (todas letras de [IVXLCDM]).
        $markdown = preg_replace(
            '/([;,:\)]\s*)([IVXLCDM]+[\.\-–—\)]+\s)/u',
            "$1\n$2",
            $markdown
        );

        // 2. Fracción romana después de fin de oración (minúscula + punto).
        //    "...obligación. II. Ser el vínculo..." → split seguro porque
        //    la letra minúscula antes del punto confirma fin de oración
        //    (no una sigla como "CIVIL." que termina en mayúscula).
        $markdown = preg_replace(
            '/([a-záéíóúñü]\.\s+)([IVXLCDM]+[\.\-–—\)]+\s)/u',
            "$1\n$2",
            $markdown
        );

        // 3. Inciso inline: "...siguientes: a) El Modelo...; b) El Modelo..."
        $markdown = preg_replace(
            '/([;,:\)]\s*)([a-z][\)\.\-–]+\s)/u',
            "$1\n$2",
            $markdown
        );

        return $markdown;
    }

    /** Inciso: "a) texto", "b.- texto", "a)Que se admita". */
    private function detectarInciso(string $texto): ?array
    {
        $texto = $this->limpiarFormatoInline($texto);
        // Una letra minúscula + uno o más separadores + texto (espacio opcional).
        if (preg_match('/^([a-z])\s*[\)\.\-–—]+\s*(\S.*)$/u', $texto, $m)) {
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
}
