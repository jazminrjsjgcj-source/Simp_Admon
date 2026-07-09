<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Support\TextoNormalizador;
use Illuminate\Support\Facades\DB;

/**
 * Extrae definiciones legales de CUALQUIER regulación ya estructurada en
 * PUNTA, detectando automáticamente los artículos que definen términos.
 *
 * No depende de ninguna ley específica. Funciona sobre el patrón de
 * redacción que casi todo reglamento, ley o bando mexicano usa cerca de su
 * inicio:
 *
 *   Artículo 4. Para efectos del presente Reglamento, se entenderá por:
 *   I. Autoridad Municipal: el Ayuntamiento de La Paz...
 *   II. Licencia: el documento que autoriza...
 *
 * El servicio busca dos señales:
 *   1. El artículo CONTENEDOR menciona una frase disparadora ("se entenderá
 *      por", "para efectos de", "se entiende por", "significarán").
 *   2. Sus fracciones/incisos HIJOS (ya existen como nodos separados gracias
 *      al estructurador) siguen el patrón "Término: definición".
 *
 * Cuando encuentra esa combinación, guarda cada término en
 * definiciones_legales apuntando al regulacion_id y nodo_id reales de
 * donde salió — nunca copia texto de una fuente externa.
 */
class DefinitionExtractorService
{
    /**
     * Frases que, en la técnica legislativa mexicana, anuncian que un
     * artículo va a definir términos. Se buscan ya normalizadas (sin
     * acentos, minúsculas) contra el texto también normalizado del artículo.
     *
     * Esta lista se amplió a partir de un caso real: el Artículo 6 de la
     * LNETB introduce una lista de "principios" (Confianza ciudadana,
     * Certeza jurídica, etc.) con la estructura EXACTA de una lista de
     * definiciones ("Nombre: explicación", numerada con romanos) pero sin
     * usar ninguna de las fórmulas de "definición" que la lista original
     * cubría. El criterio real no es si el documento le llama
     * "definición" a la lista — es si la lista TIENE esa estructura.
     * Como no se puede detectar la estructura sin antes decidir revisar el
     * artículo, esta lista funciona como un primer filtro barato: cualquier
     * artículo que mencione alguna de estas frases se intenta procesar;
     * la validación real de que cada elemento sea "Nombre: explicación"
     * ocurre después, en separarTerminoYDefinicion() y
     * separarVariasDefiniciones() (con sus propios límites de longitud
     * que rechazan términos u definiciones que no tengan forma razonable).
     *
     * No se agregan frases de introducción de sanciones, procedimientos u
     * obligaciones ("serán sancionados", "deberá presentar", "el trámite
     * consiste en") a propósito: esas listas también pueden tener la
     * estructura "Nombre: explicación", pero su contenido no responde
     * "¿qué es X?" — describen una consecuencia o un paso, no un concepto.
     * Mostrar eso como si fuera la definición de un término confundiría
     * más de lo que ayudaría.
     */
    private const FRASES_DISPARADORAS = [
        'se entendera por',
        'para efectos de',
        'para los efectos de',
        'se entiende por',
        'significaran',
        'en singular o plural',
        'se entendera como',
        'los siguientes principios',
        'los siguientes criterios',
        'los siguientes lineamientos',
        'los siguientes conceptos',
        'las siguientes definiciones',
        'los siguientes terminos',
    ];

    /**
     * Longitud máxima de un término válido. Protege contra falsos positivos
     * donde el texto tiene un ":" que no separa un término de su definición
     * (por ejemplo, una cita textual con dos puntos dentro de una fracción
     * que no es realmente una definición).
     */
    private const LARGO_MAXIMO_TERMINO = 80;

    /**
     * Longitud mínima de una definición válida. Evita guardar "definiciones"
     * de una sola palabra que probablemente sean un falso positivo del
     * patrón "Término: definición" (por ejemplo, una referencia cruzada
     * corta que casualmente tiene dos puntos).
     */
    private const LARGO_MINIMO_DEFINICION = 15;

    /**
     * Recorre TODAS las regulaciones ya estructuradas y extrae sus
     * definiciones. Es idempotente: antes de insertar, borra las
     * definiciones previamente extraídas de cada regulación, para que se
     * pueda volver a ejecutar tantas veces como sea necesario (por ejemplo,
     * después de reestructurar una regulación) sin duplicar filas.
     *
     * @return array{regulaciones_procesadas: int, definiciones_encontradas: int}
     */
    public function extraerDeTodasLasRegulaciones(): array
    {
        $regulaciones = Regulacion::where('estructurada', true)->get();

        $totalDefiniciones = 0;
        foreach ($regulaciones as $regulacion) {
            $totalDefiniciones += $this->extraerDeRegulacion($regulacion);
        }

        return [
            'regulaciones_procesadas'  => $regulaciones->count(),
            'definiciones_encontradas' => $totalDefiniciones,
        ];
    }

    /**
     * Extrae las definiciones de UNA regulación específica. Público para
     * poder llamarse justo después de estructurar una regulación individual
     * (ver la integración sugerida en RegulacionController::estructurar()
     * en la siguiente entrega), sin tener que reprocesar todas las demás.
     *
     * @return int Número de definiciones encontradas en esta regulación.
     */
    public function extraerDeRegulacion(Regulacion $regulacion): int
    {
        return DB::transaction(function () use ($regulacion) {
            // Idempotente: se borran las definiciones previas de esta
            // regulación antes de reconstruir, igual que el estructurador
            // hace con sus propios nodos.
            DB::table('definiciones_legales')
                ->where('regulacion_id', $regulacion->id)
                ->delete();

            $articulosContenedores = $this->encontrarArticulosDeDefiniciones($regulacion);

            $creadas = 0;
            foreach ($articulosContenedores as $articulo) {
                $creadas += $this->extraerDefinicionesDeArticulo($regulacion, $articulo);
            }

            return $creadas;
        });
    }

    /**
     * Busca, dentro de una regulación, los nodos tipo 'articulo' que
     * contengan alguna de las frases disparadoras de definiciones.
     *
     * Busca la frase en DOS lugares, no solo en uno:
     *   1. En el propio campo `texto` del nodo artículo (para el caso en
     *      que el encabezado y el texto introductorio están en la misma
     *      línea: "Artículo 3. Para los efectos de esta Ley, se entenderá
     *      por:" — el estructurador pone todo después del número como
     *      'texto' del nodo).
     *   2. En el texto de sus HIJOS DIRECTOS (para el caso en que el
     *      encabezado está solo: "Artículo 3" en una línea, y "Para los
     *      efectos..." en la siguiente — el estructurador crea el artículo
     *      con texto=null, y la frase queda en un nodo hijo tipo párrafo
     *      o en la primera fracción).
     *
     * Sin el punto 2, cualquier regulación donde el encabezado del artículo
     * esté solo en su línea (como la LNETB) nunca se detecta como artículo
     * de definiciones — el filtro whereNotNull('texto') de la versión
     * anterior lo descartaba antes de revisar sus hijos.
     */
    private function encontrarArticulosDeDefiniciones(Regulacion $regulacion): \Illuminate\Support\Collection
    {
        // Traer TODOS los artículos, incluyendo los que tienen texto=null
        // (encabezado solo en su línea, sin texto después del número).
        $articulos = $regulacion->nodos()
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->get();

        return $articulos->filter(function (RegulacionNodo $articulo) {
            // Punto 1: buscar la frase en el propio texto del artículo.
            if (!empty($articulo->texto) && $this->contieneFrageDisparadora($articulo->texto)) {
                return true;
            }

            // Punto 2: buscar la frase en los hijos directos del artículo
            // (párrafos, fracciones). Solo se revisan los hijos directos
            // (un solo nivel), no todo el subárbol recursivo — la frase
            // introductoria de un artículo de definiciones siempre está
            // justo debajo del encabezado, nunca enterrada dentro de un
            // inciso tres niveles más abajo.
            $hijosDirectos = RegulacionNodo::where('parent_id', $articulo->id)
                ->whereNotNull('texto')
                ->get();

            foreach ($hijosDirectos as $hijo) {
                if ($this->contieneFrageDisparadora($hijo->texto)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Verifica si un texto contiene alguna de las frases que, en la
     * técnica legislativa mexicana, anuncian que un artículo va a definir
     * términos. Se compara normalizando ambos lados (sin acentos,
     * minúsculas) para que funcione sin importar cómo se hayan capturado
     * las mayúsculas o los acentos durante la conversión del documento.
     */
    private function contieneFrageDisparadora(string $texto): bool
    {
        $normalizado = TextoNormalizador::normalizar($texto);
        foreach (self::FRASES_DISPARADORAS as $frase) {
            if (str_contains($normalizado, $frase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recorre las fracciones e incisos hijos de un artículo de definiciones
     * y extrae el patrón "Término: definición" de cada uno.
     *
     * Maneja dos casos reales de redacción legislativa mexicana:
     *
     *   Caso A — cada fracción es un nodo separado (el estructurador creó
     *   un nodo por cada "I.", "II.", etc.). Cada nodo tiene exactamente un
     *   par "Término: definición". Este es el caso ideal, donde
     *   separarTerminoYDefinicion() basta por sí solo.
     *
     *   Caso B — todas las fracciones están concatenadas en un solo nodo
     *   (el documento original las escribió en un solo párrafo corrido
     *   separado por punto y coma: "; II. Agenda de Simplificación...; III.
     *   Análisis de impacto..."). El estructurador no pudo separarlas en
     *   nodos individuales porque no encontró un numeral romano al inicio
     *   de una línea propia — todo el bloque quedó como el texto de un solo
     *   nodo. Aquí, separarVariasDefiniciones() detecta el patrón "; ROMANO."
     *   y parte el texto antes de intentar la separación individual.
     *
     * El orden importa: primero se intenta el Caso B (separar varias). Si
     * el texto no tiene el patrón "; ROMANO.", separarVariasDefiniciones()
     * devuelve null y se cae al Caso A (separar una sola), que es el
     * comportamiento original que ya funcionaba para regulaciones con
     * fracciones bien separadas en nodos individuales.
     */
    private function extraerDefinicionesDeArticulo(Regulacion $regulacion, RegulacionNodo $articulo): int
    {
        $hijos = $this->hijosRecursivos($articulo);

        // Si el artículo mismo no tiene hijos (todas las fracciones están
        // dentro de su propio texto, como párrafo corrido), también hay que
        // intentar extraer del texto del artículo directamente.
        if ($hijos->isEmpty() && !empty($articulo->texto)) {
            return $this->extraerDefinicionesDeTexto($regulacion, $articulo, $articulo);
        }

        $creadas = 0;
        foreach ($hijos as $hijo) {
            $creadas += $this->extraerDefinicionesDeTexto($regulacion, $articulo, $hijo);
        }

        return $creadas;
    }

    /**
     * Extrae definiciones de un solo bloque de texto (ya sea un nodo hijo
     * individual, o el texto del propio artículo cuando no tiene hijos).
     *
     * Primero intenta el Caso B (varias definiciones concatenadas por
     * "; ROMANO."); si no aplica, intenta el Caso A (una sola definición).
     */
    private function extraerDefinicionesDeTexto(
        Regulacion $regulacion,
        RegulacionNodo $articulo,
        RegulacionNodo $nodo,
    ): int {
        // Caso B: varias definiciones concatenadas en un solo párrafo.
        $varias = $this->separarVariasDefiniciones($nodo->texto);
        if ($varias !== null) {
            $creadas = 0;
            foreach ($varias as $par) {
                [$termino, $definicion, $fraccion] = $par;
                DB::table('definiciones_legales')->insert([
                    'termino'       => $termino,
                    'definicion'    => $definicion,
                    'regulacion_id' => $regulacion->id,
                    'nodo_id'       => $nodo->id,
                    'articulo'      => $articulo->numero,
                    'fraccion'      => $fraccion,
                    'fuente'        => $regulacion->nombre,
                    'activo'        => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $creadas++;
            }
            return $creadas;
        }

        // Caso A: una sola definición en este nodo.
        $par = $this->separarTerminoYDefinicion($nodo->texto);
        if ($par === null) {
            return 0;
        }

        [$termino, $definicion] = $par;

        DB::table('definiciones_legales')->insert([
            'termino'       => $termino,
            'definicion'    => $definicion,
            'regulacion_id' => $regulacion->id,
            'nodo_id'       => $nodo->id,
            'articulo'      => $articulo->numero,
            'fraccion'      => in_array($nodo->tipo, [RegulacionNodo::TIPO_FRACCION, RegulacionNodo::TIPO_INCISO], true)
                                ? $nodo->numero
                                : null,
            'fuente'        => $regulacion->nombre,
            'activo'        => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return 1;
    }

    /**
     * Todos los hijos de un nodo (fracciones e incisos), recursivamente,
     * porque una definición puede estar directamente en una fracción o,
     * si esa fracción a su vez tiene incisos, dentro de un inciso.
     *
     * Consulta directamente por la columna `parent_id` en vez de usar un
     * método de relación del modelo (como `children()`), porque esta clase
     * no puede confirmar si esa relación está definida en RegulacionNodo.
     * Lo único que se necesita, y que sí está confirmado, es que la tabla
     * regulacion_nodos tiene la columna `parent_id` autorreferenciada.
     */
    private function hijosRecursivos(RegulacionNodo $nodo): \Illuminate\Support\Collection
    {
        $directos = RegulacionNodo::where('parent_id', $nodo->id)
            ->whereNotNull('texto')
            ->orderBy('orden')
            ->get();

        $todos = collect($directos);

        foreach ($directos as $hijo) {
            $todos = $todos->merge($this->hijosRecursivos($hijo));
        }

        return $todos;
    }

    /**
     * Detecta si un texto contiene varias fracciones concatenadas con el
     * patrón "; ROMANO." (punto y coma, numeral romano, punto) y las separa
     * en pares [término, definición, fracción] individuales.
     *
     * Este patrón aparece cuando el documento original redactó todas las
     * fracciones de un artículo en un solo párrafo corrido, como es común
     * en la técnica legislativa mexicana real:
     *
     *   "I. Agenda Regulatoria: herramienta de planeación...; II. Agenda
     *   de Simplificación y Digitalización: herramienta...; III. Análisis
     *   de impacto regulatorio: herramienta..."
     *
     * El estructurador no pudo separar estas fracciones en nodos
     * individuales porque los numerales romanos no estaban al inicio de
     * una línea propia (la única forma que el estructurador actual reconoce).
     * Por eso todas quedaron dentro del texto de un solo nodo.
     *
     * El patrón que se busca es: punto y coma, opcionalmente espacios, un
     * numeral romano (una o más letras de I, V, X, L, C, D, M), un punto,
     * y al menos un espacio. Esto es lo suficientemente específico para no
     * confundirse con usos normales del punto y coma dentro de una oración
     * legal (que nunca va seguido de un numeral romano + punto).
     *
     * @return array<array{0: string, 1: string, 2: string}>|null
     *         Arreglo de [término, definición, fracción] por cada concepto
     *         encontrado, o null si el texto no contiene este patrón.
     */
    private function separarVariasDefiniciones(?string $texto): ?array
    {
        if ($texto === null) {
            return null;
        }

        // Umbral mínimo: si no hay al menos 2 ocurrencias de "; ROMANO.",
        // no es un párrafo de múltiples fracciones concatenadas — es una
        // fracción normal que casualmente tiene un punto y coma.
        $conteo = preg_match_all('/;\s*[IVXLCDM]+\.\s+/', $texto);
        if ($conteo < 2) {
            return null;
        }

        // Separar: el primer fragmento NO tiene punto y coma antes (es la
        // fracción I, que empieza sin separador), y cada fragmento siguiente
        // viene precedido por "; ROMANO.". preg_split con PREG_SPLIT_DELIM_CAPTURE
        // captura también el numeral, así que los fragmentos impares son los
        // numerales y los pares son el texto de cada fracción.
        $fragmentos = preg_split('/;\s*([IVXLCDM]+)\.\s+/', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($fragmentos) < 3) {
            return null; // no se pudo separar — no forzar un resultado incorrecto
        }

        $resultados = [];

        // Primer fragmento: corresponde a la fracción "I" (o la que sea que
        // empiece el artículo), sin numeral capturado antes.
        $primerTexto = trim($fragmentos[0]);
        // Detectar si empieza con un numeral propio (ej. "I. Agenda...")
        $primerNumeral = 'I';
        if (preg_match('/^([IVXLCDM]+)\.\s+/', $primerTexto, $m)) {
            $primerNumeral = $m[1];
            $primerTexto = trim(preg_replace('/^[IVXLCDM]+\.\s+/', '', $primerTexto));
        }
        $par = $this->separarTerminoYDefinicion($primerTexto);
        if ($par !== null) {
            $resultados[] = [$par[0], $par[1], $primerNumeral];
        }

        // Fragmentos siguientes: vienen en pares (numeral, texto).
        for ($i = 1; $i < count($fragmentos) - 1; $i += 2) {
            $numeral = $fragmentos[$i];
            $textoFragmento = trim($fragmentos[$i + 1] ?? '');

            $par = $this->separarTerminoYDefinicion($textoFragmento);
            if ($par !== null) {
                $resultados[] = [$par[0], $par[1], $numeral];
            }
        }

        return count($resultados) >= 2 ? $resultados : null;
    }

    /**
     * Intenta separar un texto en "término" y "definición" usando el
     * primer ":" como separador. Devuelve null si el texto no sigue este
     * patrón, o si el resultado no pasa las validaciones mínimas de
     * longitud (protección contra falsos positivos).
     *
     * @return array{0: string, 1: string}|null [termino, definicion]
     */
    private function separarTerminoYDefinicion(?string $texto): ?array
    {
        if ($texto === null || !str_contains($texto, ':')) {
            return null;
        }

        [$termino, $definicion] = explode(':', $texto, 2);
        $termino    = trim($termino);
        $definicion = trim($definicion);

        if ($termino === '' || $definicion === '') {
            return null;
        }

        if (mb_strlen($termino) > self::LARGO_MAXIMO_TERMINO) {
            return null; // probablemente no es un término, sino una oración larga con ":"
        }

        if (mb_strlen($definicion) < self::LARGO_MINIMO_DEFINICION) {
            return null; // definición demasiado corta para ser confiable
        }

        return [$termino, $definicion];
    }
}
