<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Separa palabras de español que quedaron pegadas sin espacio durante la
 * extracción de texto de un PDF (ej. "Lospreceptos" -> "Los preceptos").
 *
 * ── Por qué existe esta clase ──────────────────────────────────────────
 *
 * Algunos PDF codifican el espacio visual entre dos palabras únicamente
 * como un desplazamiento del cursor de dibujo, sin guardar un carácter de
 * espacio real en el contenido del archivo. La librería que extrae el texto
 * (smalot/pdfparser, en RegulacionConversorService::extraerTextoPdf) no tiene
 * ningún carácter que copiar en ese hueco, así que concatena las dos
 * palabras: "Los" + "preceptos" se convierte en "Lospreceptos".
 *
 * ── Por qué NO se resuelve con una regla simple de prefijos ───────────
 *
 * Una regla como "si una palabra empieza con 'con' seguido de 4+ letras,
 * insertar un espacio después de 'con'" arreglaría "conotras" -> "con otras",
 * pero destrozaría por accidente "construcción" -> "con strucción",
 * "consejo" -> "con sejo", "conforme" -> "con forme". Cualquier prefijo
 * corto que se use como pista (con, los, la, de, en, por, su, el) es también
 * el inicio legítimo de cientos de palabras españolas distintas.
 *
 * ── Cómo se resuelve aquí: verificación exhaustiva contra diccionario ──
 *
 * Esta clase prueba todas las formas posibles de cortar una palabra
 * sospechosa, y SOLO acepta un corte si TODAS las partes resultantes existen
 * como palabras reales en un diccionario de español de 636,598 entradas
 * (resources/dictionaries/es_palabras.sqlite, del paquete público
 * "an-array-of-spanish-words"). Si ninguna combinación de cortes produce
 * puras palabras reales, la palabra se deja exactamente como estaba.
 *
 * Ejemplo de por qué esto es seguro:
 *   "Lospreceptos" -> prueba "los" (existe) + "preceptos" (existe) -> CORTA
 *   "construcción" -> ya es una palabra real completa -> NO SE TOCA
 *   "consejo"      -> ya es una palabra real completa -> NO SE TOCA
 *
 * ── Normalización de acentos ────────────────────────────────────────────
 *
 * El diccionario fuente guarda la mayoría de las palabras SIN el acento de
 * intensidad (construcción -> construccion, artículo -> articulo), pero SÍ
 * distingue la ñ como letra propia (año y ano son entradas distintas). Por
 * eso la búsqueda quita tildes de vocales antes de comparar, pero nunca
 * toca la ñ. Esto se verificó contra el diccionario real antes de escribir
 * esta clase, no es una suposición.
 */
class SegmentadorPalabrasService
{
    /**
     * Longitud mínima de una palabra sospechosa para intentar segmentarla.
     *
     * Por debajo de este umbral, el riesgo de falso positivo sube: palabras
     * cortas tienen más probabilidad de coincidir por casualidad con dos
     * entradas del diccionario sin ser realmente un caso de "glue" de PDF.
     * "conotras" (8 caracteres) y "Lospreceptos" (12) quedan cómodamente
     * por encima de este umbral.
     */
    private const LONGITUD_MINIMA_PALABRA = 6;

    /**
     * Longitud máxima de una palabra sospechosa para intentar segmentarla.
     *
     * Tokens más largos que esto casi nunca son un caso real de dos palabras
     * pegadas (que en la práctica suman menos de 30 caracteres) — es más
     * probable que sea basura de extracción (OCR roto, artefacto binario).
     * No tiene sentido forzar una segmentación sobre eso; se deja intacto.
     */
    private const LONGITUD_MAXIMA_PALABRA = 30;

    /**
     * Longitud mínima de cada pieza resultante de un corte.
     *
     * Coincide con la entrada más corta real del diccionario (los términos
     * de 1 letra, tipo conjunciones sueltas, no forman parte de esta lista
     * fuente). Evita cortes en piezas de una sola letra que casi nunca son
     * el patrón real de un "glue" de PDF (que junta palabras completas).
     */
    private const LONGITUD_MINIMA_PIEZA = 2;

    /**
     * Número máximo de piezas que un corte puede producir.
     *
     * El patrón real observado (y el que reportó el usuario) es de 2
     * palabras pegadas. Permitir hasta 3 da margen para casos como
     * "delosestados" sin abrir la puerta a que una palabra rara pero real
     * termine fragmentada en 4-5 pedacitos que casualmente calzan con
     * entradas cortas del diccionario.
     */
    private const MAXIMO_PIEZAS = 3;

    /**
     * Ruta del archivo de diccionario dentro del proyecto.
     *
     * Es una base de datos SQLite de un solo archivo, con una tabla
     * `palabras (palabra TEXT PRIMARY KEY) WITHOUT ROWID` — el diseño que
     * la propia documentación de SQLite recomienda para tablas de una sola
     * columna que es su propia llave: el motor guarda las 636,598 palabras
     * ordenadas en un B-tree en disco, y cada búsqueda solo lee las pocas
     * páginas necesarias para encontrar una fila, sin cargar el diccionario
     * completo en memoria.
     *
     * No requiere ningún servidor de base de datos: PDO SQLite lee el
     * archivo directamente. Solo necesita que la extensión `pdo_sqlite` de
     * PHP esté habilitada (viene por defecto en instalaciones estándar como
     * Laragon); si no lo está, diccionarioDisponible() lo detecta y la
     * segmentación se salta sin romper el resto del sistema.
     */
    private const RUTA_DICCIONARIO = 'dictionaries/es_palabras.sqlite';

    /**
     * Conexión PDO reutilizable dentro del mismo request. Se abre una sola
     * vez (la primera palabra que se necesite verificar) y las siguientes
     * búsquedas reutilizan la misma conexión y la misma sentencia preparada.
     */
    private ?\PDO $conexion = null;
    private ?\PDOStatement $sentenciaExiste = null;

    /**
     * Aplica la segmentación a todo un texto: recorre cada palabra, y si es
     * candidata (longitud dentro del rango y no es ya una palabra real),
     * intenta separarla. Las palabras que no califican, o que no logran
     * segmentarse con éxito, se devuelven exactamente como llegaron.
     *
     * No toca espacios, puntuación, números ni saltos de línea — solo
     * reemplaza, dentro del texto, las palabras individuales que sí logró
     * separar con éxito.
     */
    public function aplicarATexto(string $texto): string
    {
        if (!$this->diccionarioDisponible()) {
            // Si el archivo de diccionario no está desplegado todavía, no
            // se rompe la conversión — simplemente no se aplica este paso.
            return $texto;
        }

        return preg_replace_callback('/[\p{L}]+/u', function (array $m) {
            $palabra = $m[0];
            $longitud = mb_strlen($palabra);

            if ($longitud < self::LONGITUD_MINIMA_PALABRA || $longitud > self::LONGITUD_MAXIMA_PALABRA) {
                return $palabra;
            }

            // Proteger siglas institucionales (SEDATU, COEPRIS, PROFEPA...).
            //
            // El "glue" real de extracción de PDF (el problema que este
            // servicio existe para resolver) siempre mezcla mayúsculas y
            // minúsculas de forma normal: "Los" + "preceptos" = "Lospreceptos",
            // nunca dos palabras completas en mayúsculas pegadas entre sí.
            //
            // Una sigla en mayúsculas, en cambio, puede coincidir por pura
            // casualidad con la concatenación de dos palabras reales del
            // diccionario sin que eso tenga ningún significado real: "SEDATU"
            // en minúsculas ("sedatu") se puede cortar como "seda" + "tu",
            // ambas palabras reales, pero el resultado no tiene relación
            // alguna con lo que la sigla significa. El algoritmo de
            // segmentación no tiene forma de saber que "SEDATU" es una sigla
            // y no dos palabras pegadas — para él, ambos casos se ven
            // idénticos: una cadena de letras que no es una palabra del
            // diccionario por sí sola, pero que sí se puede cortar en dos
            // que sí existen.
            //
            // Excluir toda palabra que esté COMPLETAMENTE en mayúsculas
            // evita este problema sin necesitar una lista manual de siglas
            // conocidas (que se quedaría corta en cuanto apareciera una
            // sigla nueva en cualquier regulación).
            if (mb_strtoupper($palabra, 'UTF-8') === $palabra) {
                return $palabra;
            }

            $piezas = $this->segmentar($palabra);

            return $piezas !== null ? implode(' ', $piezas) : $palabra;
        }, $texto);
    }

    /**
     * Intenta segmentar una sola palabra sospechosa en 2 o más palabras
     * reales. Devuelve el arreglo de piezas (con el mayúsculas/minúsculas y
     * acentos ORIGINALES, no la versión normalizada de búsqueda) si logra
     * un corte donde todas las partes son palabras reales, o null si la
     * palabra ya es válida tal cual, o si ningún corte funciona.
     */
    public function segmentar(string $palabraOriginal): ?array
    {
        $normalizada = $this->normalizarParaBusqueda($palabraOriginal);

        // Ya es una palabra real completa: no se toca.
        if ($this->existePalabra($normalizada)) {
            return null;
        }

        $memoFallidos = [];
        $longitud = mb_strlen($normalizada);
        $longitudesPiezas = $this->buscarCorte($normalizada, $longitud, $memoFallidos);

        if ($longitudesPiezas === null || count($longitudesPiezas) > self::MAXIMO_PIEZAS) {
            return null;
        }

        // Reconstruir las piezas sobre la palabra ORIGINAL (no la normalizada)
        // usando las mismas longitudes, para conservar mayúsculas y acentos
        // exactamente como venían en el texto fuente. Como quitar tildes no
        // cambia la cantidad de caracteres, los mismos offsets aplican.
        $piezas = [];
        $cursor = 0;
        foreach ($longitudesPiezas as $len) {
            $piezas[] = mb_substr($palabraOriginal, $cursor, $len);
            $cursor += $len;
        }

        return $piezas;
    }

    /**
     * Búsqueda recursiva con memoización, DE DERECHA A IZQUIERDA: desde la
     * posición $fin (que arranca en el final del texto), prueba el SUFIJO
     * más largo posible primero, y retrocede hacia la izquierda si no
     * encuentra una palabra real. Al llegar a la posición 0, la búsqueda
     * fue exitosa.
     *
     * ── Por qué de derecha a izquierda y no de izquierda a derecha ──────
     *
     * Una palabra pegada puede tener más de una forma válida de cortarse en
     * puras palabras reales. Ejemplo real encontrado durante las pruebas:
     * "conotras" se puede leer como "cono" + "tras" (ambas palabras reales)
     * o como "con" + "otras" (también ambas palabras reales). Probar el
     * PREFIJO más largo primero desde la izquierda encuentra "cono" (4
     * letras) antes que "con" (3 letras) y se queda con ese corte
     * incorrecto, sin saber que existía una opción mejor más adelante.
     *
     * Probar el SUFIJO más largo primero desde la derecha resuelve este
     * caso: encuentra "otras" (5 letras) como el sufijo real más largo
     * antes que "tras" (4 letras), dejando "con" como el resto correcto.
     * En la práctica, el patrón real de "glue" de los PDF suele ser una
     * palabra funcional corta (artículo, preposición, conjunción) pegada
     * al inicio de una palabra de contenido más larga — por eso maximizar
     * el sufijo (la parte de contenido) da mejores resultados que maximizar
     * el prefijo.
     *
     * Igual que antes, solo se memorizan los fracasos (posiciones desde las
     * que NUNCA hay solución hacia la izquierda).
     *
     * @return array<int>|null  Longitudes de cada pieza encontrada, en
     *                          orden de izquierda a derecha, o null si no
     *                          hay ningún corte válido.
     */
    private function buscarCorte(string $textoNormalizado, int $fin, array &$memoFallidos): ?array
    {
        if ($fin === 0) {
            return []; // se cubrió toda la palabra: éxito, sin piezas adicionales
        }

        if (isset($memoFallidos[$fin])) {
            return null; // ya se sabe que desde aquí no hay solución
        }

        $maxSufijo = min(30, $fin); // ninguna palabra real mide más de 30
        for ($len = $maxSufijo; $len >= self::LONGITUD_MINIMA_PIEZA; $len--) {
            $inicio = $fin - $len;
            $pieza  = mb_substr($textoNormalizado, $inicio, $len);

            if (!$this->existePalabra($pieza)) {
                continue;
            }

            $resto = $this->buscarCorte($textoNormalizado, $inicio, $memoFallidos);
            if ($resto !== null) {
                $resto[] = $len; // esta pieza va al final del recorrido (venimos de la derecha)
                return $resto;
            }
        }

        $memoFallidos[$fin] = true;
        return null;
    }

    /**
     * ¿Existe esta palabra (ya normalizada, en minúsculas y sin tildes) en
     * el diccionario de español?
     *
     * Consulta el archivo SQLite con una sentencia preparada y reutilizable.
     * SQLite resuelve esto con el índice de la llave primaria (búsqueda en
     * el B-tree, tiempo logarítmico) sin necesidad de cargar las 636,598
     * palabras en memoria de PHP en ningún momento.
     */
    private function existePalabra(string $palabraNormalizada): bool
    {
        $sentencia = $this->obtenerSentenciaExiste();

        if ($sentencia === null) {
            return false; // diccionario no disponible: no se puede confirmar nada
        }

        $sentencia->execute([$palabraNormalizada]);
        $existe = $sentencia->fetchColumn() !== false;
        $sentencia->closeCursor();

        return $existe;
    }

    /**
     * Normaliza una palabra para comparar contra el diccionario: minúsculas
     * y sin tilde de intensidad en las vocales. La ñ/Ñ se preserva tal cual
     * porque el diccionario fuente la distingue como letra propia (año y
     * ano son entradas distintas) — verificado contra el archivo real antes
     * de escribir esta regla.
     */
    private function normalizarParaBusqueda(string $palabra): string
    {
        $palabra = mb_strtolower($palabra, 'UTF-8');

        return strtr($palabra, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u',
        ]);
    }

    /**
     * ¿El diccionario se puede usar en este servidor?
     *
     * Requiere que la extensión pdo_sqlite de PHP esté habilitada (viene
     * por defecto en instalaciones estándar como Laragon) y que el archivo
     * .sqlite exista en resources/dictionaries/. Si cualquiera de las dos
     * condiciones falla, la segmentación se salta sin romper el resto del
     * sistema — el mismo criterio de "degradar sin tronar" que ya se usaba
     * cuando solo faltaba el archivo.
     */
    private function diccionarioDisponible(): bool
    {
        return extension_loaded('pdo_sqlite')
            && file_exists(resource_path(self::RUTA_DICCIONARIO));
    }

    /**
     * Devuelve la sentencia preparada para verificar si una palabra existe,
     * abriendo la conexión SQLite la primera vez que se necesita y
     * reutilizándola en las siguientes llamadas dentro del mismo request.
     *
     * @return \PDOStatement|null  null si el diccionario no está disponible.
     */
    private function obtenerSentenciaExiste(): ?\PDOStatement
    {
        if ($this->sentenciaExiste !== null) {
            return $this->sentenciaExiste;
        }

        if (!$this->diccionarioDisponible()) {
            return null;
        }

        try {
            $ruta = resource_path(self::RUTA_DICCIONARIO);
            $this->conexion = new \PDO('sqlite:' . $ruta);
            $this->conexion->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->sentenciaExiste = $this->conexion->prepare(
                'SELECT 1 FROM palabras WHERE palabra = ? LIMIT 1'
            );

            return $this->sentenciaExiste;
        } catch (\Throwable $e) {
            Log::warning('SegmentadorPalabrasService: no se pudo abrir el diccionario SQLite: ' . $e->getMessage());
            return null;
        }
    }
}
