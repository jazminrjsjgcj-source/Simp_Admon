<?php

namespace App\Services;

use App\Models\Regulacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Servicio para gestionar la conversión de regulaciones jurídicas
 * (PDF / Word) a archivos Markdown citables desde los wizards.
 *
 * Flujo:
 *   1. guardarOriginal()  — almacena el archivo .pdf/.docx en storage
 *   2. convertirAMarkdown() — extrae el texto y lo guarda como .md
 *
 * En esta primera versión la conversión usa extractores básicos
 * (smalot/pdfparser para PDF, phpoffice/phpword para DOCX). Si las
 * dependencias no están instaladas, el método retorna un error
 * controlado y el registro queda en estatus 'error' con su mensaje.
 */
class RegulacionConversorService
{
    /**
     * ¿El texto se extrajo con una herramienta que separa bien las palabras
     * (pdftotext, LibreOffice)? En ese caso NO hay que segmentar: el segmentador
     * está para reparar el texto de la librería PHP, que pega palabras cuando el
     * PDF no guarda el espacio. Aplicarlo sobre un texto ya correcto es dañino,
     * porque parte términos que no están en el diccionario ("semifijos" acababa
     * como "se mi fijos", ya que "se", "mi" y "fijos" sí existen por separado).
     */
    private bool $textoYaSeparado = false;

    public function __construct(
        private SegmentadorPalabrasService $segmentador,
        // Se reutiliza para localizar LibreOffice: ya sabe dónde buscarlo
        // (variable de entorno, PATH del sistema o rutas conocidas).
        private PdfConversorService $pdfConversor,
    ) {}

    private const DIRECTORIO_ORIGINALES = 'regulaciones/originales';
    private const DIRECTORIO_MARKDOWN   = 'regulaciones/markdown';

    /**
     * Score mínimo para considerar el texto extraído "no basura" y guardarlo
     * en disco como Markdown. Por debajo de este valor la conversión falla
     * con un mensaje claro para que el usuario suba un .docx limpio.
     *
     * Es deliberadamente más bajo que SCORE_ESTRUCTURACION_MINIMO: el texto
     * puede ser parcialmente legible y aún guardarse; estructurarlo ya requiere
     * más calidad porque el parser necesita encontrar encabezados válidos.
     */
    public const SCORE_GUARDADO_MINIMO = 0.25;

    /**
     * Score mínimo para que el controlador intente estructurar el articulado.
     * Se lee desde RegulacionController::estructurar() para dar un mensaje
     * contextual al usuario si el contenido no tiene calidad suficiente.
     *
     * Es más alto que SCORE_GUARDADO_MINIMO para filtrar textos que pasaron
     * la conversión pero siguen siendo demasiado garbleados para parsear.
     */
    public const SCORE_ESTRUCTURACION_MINIMO = 0.30;

    public function guardarOriginal(Regulacion $regulacion, UploadedFile $archivo): void
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (!in_array($extension, Regulacion::EXTENSIONES_PERMITIDAS, true)) {
            throw new InvalidArgumentException(
                Regulacion::ARCHIVO_ERROR_TIPO . ' Extensión recibida: .' . $extension
            );
        }

        $nombreArchivo = $this->generarNombreArchivo($regulacion, $extension);
        $rutaNueva     = self::DIRECTORIO_ORIGINALES . '/' . $nombreArchivo;

        // Si la regulación ya tenía un archivo con ruta distinta (porque el
        // nombre cambió entre reemplazos), borrar el anterior para no dejar
        // un archivo huérfano en disco que nadie referencia.
        $rutaAnterior = $regulacion->archivo_original;
        if ($rutaAnterior && $rutaAnterior !== $rutaNueva && Storage::disk('local')->exists($rutaAnterior)) {
            Storage::disk('local')->delete($rutaAnterior);
        }

        $rutaOriginal = $archivo->storeAs(self::DIRECTORIO_ORIGINALES, $nombreArchivo, 'local');

        $regulacion->update([
            'archivo_original'    => $rutaOriginal,
            'extension_original'  => $extension,
            'conversion_estatus'  => Regulacion::CONVERSION_PENDIENTE,
            'conversion_error'    => null,
        ]);
    }

    public function convertirAMarkdown(Regulacion $regulacion): bool
    {
        if (empty($regulacion->archivo_original)) {
            return false;
        }

        // Bug #21: la conversión de archivos grandes (PDF con muchas páginas o
        // DOCX pesados) puede superar el max_execution_time del php.ini (30s por
        // defecto). Se amplía a 120s solo para esta operación — el límite se
        // restaura solo al terminar el request. Si set_time_limit no está
        // disponible (safe_mode o restricción del hosting), se continúa con
        // el timeout vigente y se confía en el catch de más abajo.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $regulacion->update(['conversion_estatus' => Regulacion::CONVERSION_PROCESANDO]);

        try {
            $rutaAbsoluta = Storage::disk('local')->path($regulacion->archivo_original);
            $textoCrudo   = $this->extraerTexto($rutaAbsoluta, $regulacion->extension_original);
            $markdown     = $this->formatearComoMarkdown($regulacion, $textoCrudo);

            // Verificar que el contenido extraído es legible antes de guardarlo.
            // Si el score es muy bajo (< 0.25), el texto es basura binaria y no
            // tiene sentido guardarlo — solo confundiría al usuario.
            $scoreFinal = $this->scoreLegibilidad($textoCrudo);
            if ($scoreFinal < self::SCORE_GUARDADO_MINIMO) {
                $regulacion->update([
                    'conversion_estatus' => Regulacion::CONVERSION_ERROR,
                    'conversion_error'   => 'El texto extraído no es legible (score: '
                        . round($scoreFinal * 100) . '%). '
                        . 'Sugerencia: abra el archivo en Word y guárdelo como .docx.',
                ]);
                return false;
            }

            $rutaMd = self::DIRECTORIO_MARKDOWN . '/' . $this->generarNombreArchivo($regulacion, 'md');

            // Borrar markdown anterior si la ruta cambió (misma lógica que
            // guardarOriginal: evita archivos huérfanos por cambio de nombre).
            $mdAnterior = $regulacion->archivo_markdown;
            if ($mdAnterior && $mdAnterior !== $rutaMd && Storage::disk('local')->exists($mdAnterior)) {
                Storage::disk('local')->delete($mdAnterior);
            }

            Storage::disk('local')->put($rutaMd, $markdown);

            $indice = $this->extraerIndice($markdown);

            $regulacion->update([
                'archivo_markdown'   => $rutaMd,
                'conversion_estatus' => Regulacion::CONVERSION_LISTO,
                'conversion_error'   => null,
                'indice'             => !empty($indice) ? $indice : null,
            ]);

            return true;
        } catch (Throwable $e) {
            // Bug #21: mensaje amigable si fue un timeout.
            $msg = $e->getMessage();
            if (stripos($msg, 'Maximum execution time') !== false
                || stripos($msg, 'time limit') !== false) {
                $msg = 'El archivo es demasiado grande para convertir en línea '
                     . '(se agotó el tiempo de procesamiento). '
                     . 'Sugerencia: divida el documento en partes o conviértalo '
                     . 'a .docx desde Word antes de subirlo.';
            }
            $regulacion->update([
                'conversion_estatus' => Regulacion::CONVERSION_ERROR,
                'conversion_error'   => $msg,
            ]);
            return false;
        }
    }

    public function obtenerContenidoMarkdown(Regulacion $regulacion): ?string
    {
        if (!$regulacion->conversionListaParaCitar()) {
            return null;
        }

        return Storage::disk('local')->exists($regulacion->archivo_markdown)
            ? Storage::disk('local')->get($regulacion->archivo_markdown)
            : null;
    }

    /**
     * Borra los archivos físicos de una regulación del disco: el archivo
     * original (DOC/DOCX/PDF subido por el usuario) y el Markdown extraído.
     *
     * No borra el PDF cacheado — esa responsabilidad pertenece a
     * PdfConversorService::invalidarCache(), que conoce el directorio
     * configurado en config('services.punta.pdf.cache_dir'). El controlador
     * llama a ambos métodos cuando hace una eliminación permanente.
     */
    public function eliminarArchivos(Regulacion $regulacion): void
    {
        foreach ([$regulacion->archivo_original, $regulacion->archivo_markdown] as $ruta) {
            if ($ruta && Storage::disk('local')->exists($ruta)) {
                Storage::disk('local')->delete($ruta);
            }
        }
    }

    /**
     * Extrae el índice de la regulación a partir de los encabezados Markdown.
     *
     * Recorre líneas que empiecen con # y genera un array estructurado:
     *   [
     *     ['nivel' => 1, 'titulo' => 'TÍTULO PRIMERO', 'linea' => 12],
     *     ['nivel' => 2, 'titulo' => 'Capítulo I',     'linea' => 15],
     *     ['nivel' => 3, 'titulo' => 'Artículo 1',     'linea' => 18],
     *   ]
     *
     * También detecta patrones comunes sin # como "Artículo N",
     * "TÍTULO", "CAPÍTULO", "SECCIÓN", "TRANSITORIO".
     */
    public function extraerIndice(string $markdown): array
    {
        $lineas = explode("\n", $markdown);
        $indice = [];

        foreach ($lineas as $numero => $linea) {
            $lineaTrim = trim($linea);

            // Encabezados Markdown: ## Título
            if (preg_match('/^(#{1,4})\s+(.+)$/', $lineaTrim, $m)) {
                $titulo = trim($m[2]);
                if ($this->esTituloRelevante($titulo)) {
                    $indice[] = [
                        'nivel'  => strlen($m[1]),
                        'titulo' => $titulo,
                        'linea'  => $numero + 1,
                    ];
                }
                continue;
            }

            // Patrones sin # (texto plano extraído de PDF)
            if (preg_match('/^(TÍTULO|CAPÍTULO|SECCIÓN|TRANSITORIO|Artículo\s+\d+)/iu', $lineaTrim, $m)) {
                $nivel = match (true) {
                    str_starts_with(mb_strtoupper($lineaTrim), 'TÍTULO')      => 1,
                    str_starts_with(mb_strtoupper($lineaTrim), 'CAPÍTULO')    => 2,
                    str_starts_with(mb_strtoupper($lineaTrim), 'SECCIÓN')     => 2,
                    str_starts_with(mb_strtoupper($lineaTrim), 'TRANSITORIO') => 2,
                    default                                                    => 3,
                };

                $indice[] = [
                    'nivel'  => $nivel,
                    'titulo' => Str::limit($lineaTrim, 120, '...'),
                    'linea'  => $numero + 1,
                ];
            }
        }

        return $indice;
    }

    /**
     * Filtra títulos irrelevantes como metadatos de la cabecera generada.
     */
    private function esTituloRelevante(string $titulo): bool
    {
        $excluir = ['Regulación generada automáticamente', '**Tipo:**', '**Fecha'];

        foreach ($excluir as $patron) {
            if (str_contains($titulo, $patron)) {
                return false;
            }
        }

        return mb_strlen($titulo) > 2;
    }

    /**
     * Extrae el texto del archivo según su extensión.
     * Usa librerías externas si están instaladas; si no, lanza excepción
     * controlada que queda registrada en `conversion_error`.
     */
    private function extraerTexto(string $rutaAbsoluta, string $extension): string
    {
        return match($extension) {
            'pdf'         => $this->extraerTextoPdf($rutaAbsoluta),
            'docx', 'doc' => $this->extraerTextoWord($rutaAbsoluta, $extension),
            default       => throw new RuntimeException("Extensión no soportada: {$extension}"),
        };
    }

    private function extraerTextoPdf(string $ruta): string
    {
        // Primero pdftotext: conserva los saltos de línea que separan artículos
        // y fracciones (ver extraerPdfConPdftotext para el detalle del modo -raw).
        //
        // La librería PHP (Smalot\PdfParser) devuelve el texto corrido, sin esos
        // saltos, y así el estructurador no puede distinguir dónde termina un
        // artículo y empieza el siguiente. pdftotext (de poppler-utils) es la
        // herramienta estándar para esto y respeta la maquetación original.
        $texto = $this->extraerPdfConPdftotext($ruta);
        if ($texto !== null && trim($texto) !== '') {
            // pdftotext respeta los espacios reales del documento: el texto ya
            // viene con las palabras bien separadas y no hay que segmentarlo.
            $this->textoYaSeparado = true;

            return $texto;
        }

        // Respaldo: si pdftotext no está instalado, se usa la librería PHP.
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException(
                'Librería smalot/pdfparser no instalada. Ejecute: composer require smalot/pdfparser'
            );
        }

        $parser    = new \Smalot\PdfParser\Parser();
        $documento = $parser->parseFile($ruta);

        return $documento->getText();
    }

    /**
     * Extrae el texto de un PDF con pdftotext (poppler-utils), preservando la
     * disposición del documento. Devuelve null si la herramienta no está disponible
     * o si falla, para que el llamador recurra al respaldo.
     */
    private function extraerPdfConPdftotext(string $ruta): ?string
    {
        // ¿Está instalada la herramienta?
        exec('command -v pdftotext 2>/dev/null', $donde, $codigo);
        if ($codigo !== 0 || empty($donde)) {
            return null;
        }

        $salida = tempnam(sys_get_temp_dir(), 'punta_pdf_') . '.txt';

        // -raw → extrae el texto en el orden del flujo del documento.
        //
        //   Se usa -raw y NO -layout a propósito: -layout intenta reproducir la
        //   maquetación y, en documentos con texto justificado o en columnas,
        //   mete espacios DENTRO de las palabras ("se mi fijos" en vez de
        //   "semifijos"), lo que rompe el buscador. Con -raw las palabras quedan
        //   enteras y los artículos y fracciones siguen empezando en su línea,
        //   que es lo que necesita el estructurador.
        //
        // -enc UTF-8 → para no perder los acentos.
        $comando = 'pdftotext -raw -enc UTF-8 '
                 . escapeshellarg($ruta) . ' '
                 . escapeshellarg($salida) . ' 2>&1';

        exec($comando, $mensajes, $codigo);

        if ($codigo !== 0 || ! file_exists($salida)) {
            \Illuminate\Support\Facades\Log::warning(
                'pdftotext no pudo extraer el PDF: ' . implode(' ', (array) $mensajes)
            );
            @unlink($salida);

            return null;
        }

        $texto = (string) file_get_contents($salida);
        @unlink($salida);

        return $texto;
    }

    private function extraerTextoWord(string $ruta, string $extension): string
    {
        // ── Camino principal: LibreOffice → PDF → pdftotext ────────────────
        //
        // PHPWord no extrae la numeración de las listas automáticas de Word: cuando
        // los incisos se escriben como lista (Word dibuja la "a)", pero no es texto
        // del documento), esa letra se pierde y el estructurador ya no reconoce el
        // inciso. En una ley eso significa perder la mayoría de los incisos.
        //
        // LibreOffice sí RENDERIZA esa numeración al generar el PDF —igual que al
        // imprimir—, así que la letra pasa a ser texto real. Desde ahí, pdftotext
        // conserva la disposición y el estructurador la reconoce.
        //
        // Es una conversión más, y por tanto más lenta, pero recupera contenido que
        // de otro modo desaparecía.
        $texto = $this->extraerWordViaPdf($ruta);
        if ($texto !== null && trim($texto) !== '') {
            // Viene de LibreOffice + pdftotext: las palabras ya están separadas.
            $this->textoYaSeparado = true;

            return $texto;
        }

        // ── Respaldo: PHPWord (si LibreOffice no está disponible) ──────────
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new RuntimeException(
                'Librería phpoffice/phpword no instalada. Ejecute: composer require phpoffice/phpword'
            );
        }

        // Para .docx (ZIP/XML), siempre usar Word2007 — no hay ambigüedad.
        if ($extension === 'docx') {
            return $this->extraerConPhpWord($ruta, 'Word2007');
        }

        // ── .doc: primero LibreOffice ──────────────────────────────────────
        // El .doc es el formato binario viejo de Word. Ni PHPWord ni la lectura
        // directa de bytes conservan la estructura del documento: devuelven el
        // texto corrido, sin los saltos de línea que separan artículos y
        // fracciones (y así el estructurador ya no puede distinguirlos).
        //
        // LibreOffice sí entiende el formato de forma nativa. Se usa para pasar
        // el .doc a .docx, que PHPWord lee bien y con los párrafos intactos.
        // Si LibreOffice no está instalado, se sigue con los métodos de abajo.
        $rutaDocx = $this->convertirDocConLibreOffice($ruta);
        if ($rutaDocx !== null) {
            try {
                $texto = $this->extraerConPhpWord($rutaDocx, 'Word2007');
                @unlink($rutaDocx); // archivo temporal, ya no hace falta
                if (trim($texto) !== '') {
                    return $texto;
                }
            } catch (Throwable $e) {
                @unlink($rutaDocx);
                \Illuminate\Support\Facades\Log::warning(
                    'LibreOffice convirtió el .doc pero PHPWord no pudo leerlo: ' . $e->getMessage()
                );
            }
        }

        // Para .doc, detectar el formato REAL leyendo los primeros bytes.
        $reader = $this->detectarReaderDoc($ruta);

        if ($reader === 'HTML') {
            return $this->extraerTextoHtml($ruta);
        }

        // Intentar extracción con PHPWord.
        $textoPhpWord = '';
        try {
            $textoPhpWord = $this->extraerConPhpWord($ruta, $reader);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "PHPWord falló al leer {$ruta} con reader {$reader}: " . $e->getMessage()
            );
        }

        // Medir legibilidad del resultado de PHPWord.
        $scorePhpWord = $this->scoreLegibilidad($textoPhpWord);

        // Si PHPWord produjo texto legible (> 50% caracteres comunes), usarlo.
        if ($scorePhpWord > 0.50) {
            return $textoPhpWord;
        }

        // PHPWord produjo garble. Intentar extracción directa de bytes
        // del archivo: los .doc OLE almacenan texto en UTF-16LE internamente.
        $textoDirecto = $this->extraerTextoDirecto($ruta);
        $scoreDirecto = $this->scoreLegibilidad($textoDirecto);

        // También intentar conversión de encoding del resultado de PHPWord.
        $textoConvertido = '';
        $scoreConvertido = 0;
        if (trim($textoPhpWord) !== '') {
            foreach (['Windows-1252', 'ISO-8859-1', 'UTF-16LE'] as $desde) {
                $intento = @mb_convert_encoding($textoPhpWord, 'UTF-8', $desde);
                $scoreIntento = $this->scoreLegibilidad($intento);
                if ($scoreIntento > $scoreConvertido) {
                    $textoConvertido = $intento;
                    $scoreConvertido = $scoreIntento;
                }
            }
        }

        // Devolver el resultado con mejor score.
        $resultados = [
            [$textoPhpWord,    $scorePhpWord],
            [$textoDirecto,    $scoreDirecto],
            [$textoConvertido, $scoreConvertido],
        ];

        usort($resultados, fn ($a, $b) => $b[1] <=> $a[1]);

        $mejor = $resultados[0];

        if ($mejor[1] < 0.20 || trim($mejor[0]) === '') {
            throw new RuntimeException(
                'No se pudo extraer texto legible del archivo .doc. '
                . 'Scores de legibilidad: PHPWord=' . round($scorePhpWord, 2)
                . ', directo=' . round($scoreDirecto, 2)
                . ', convertido=' . round($scoreConvertido, 2)
                . '. Sugerencia: abra el archivo en Word y guárdelo como .docx.'
            );
        }

        return $mejor[0];
    }

    /**
     * Detecta el formato real de un archivo .doc leyendo sus magic bytes.
     */
    private function detectarReaderDoc(string $ruta): string
    {
        $handle = fopen($ruta, 'rb');
        if (!$handle) {
            return 'MsDoc';
        }
        $bytes = fread($handle, 8);
        fclose($handle);

        if (str_starts_with($bytes, "\xD0\xCF\x11\xE0")) {
            return 'MsDoc';
        }

        if (str_starts_with($bytes, '{\rtf')) {
            return 'RTF';
        }

        $inicio = strtolower(trim(substr($bytes, 0, 5)));
        if (str_starts_with($inicio, '<html') || str_starts_with($inicio, '<!doc') || str_starts_with($inicio, '<head')) {
            return 'HTML';
        }

        if (str_starts_with($bytes, 'MIME')) {
            return 'HTML';
        }

        return 'MsDoc';
    }

    /**
     * Extrae texto de un archivo HTML guardado como .doc.
     */
    private function extraerTextoHtml(string $ruta): string
    {
        $html = file_get_contents($ruta);

        if (preg_match('/charset=(["\']?)([^"\'\s;>]+)/i', $html, $m)) {
            $charset = strtoupper($m[2]);
            if ($charset !== 'UTF-8') {
                $html = @mb_convert_encoding($html, 'UTF-8', $charset) ?: $html;
            }
        }

        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $texto = strip_tags($html);

        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }

    /**
     * Extrae texto usando PHPWord con un reader específico.
     */
    private function extraerConPhpWord(string $ruta, string $reader): string
    {
        $documento = \PhpOffice\PhpWord\IOFactory::load($ruta, $reader);
        $texto     = '';

        foreach ($documento->getSections() as $seccion) {
            foreach ($seccion->getElements() as $elemento) {
                $texto .= $this->extraerTextoDeElemento($elemento) . "\n";
            }
        }

        return $texto;
    }

    /**
     * Extracción directa de texto de un archivo .doc OLE leyendo bytes.
     *
     * Los archivos .doc (OLE Compound Document) almacenan el texto del
     * documento en UTF-16LE internamente. Este método lee los bytes crudos
     * del archivo, los decodifica como UTF-16LE, y extrae secuencias de
     * texto legible. No requiere PHPWord y funciona como fallback cuando
     * el reader MsDoc produce texto garbleado.
     *
     * No es perfecto: puede capturar basura binaria entre los fragmentos
     * de texto, pero para archivos .doc problemáticos produce resultados
     * más legibles que PHPWord con encoding roto.
     */
    private function extraerTextoDirecto(string $ruta): string
    {
        $bytes = @file_get_contents($ruta);
        if (!$bytes || strlen($bytes) < 100) {
            return '';
        }

        $resultados = [];

        // Intento 1: decodificar todo el archivo como UTF-16LE y extraer
        // secuencias de texto legible (4+ caracteres seguidos).
        $utf16 = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
        if ($utf16) {
            preg_match_all('/[\p{L}\p{N}\p{P}\p{Zs}]{4,}/u', $utf16, $matches);
            $resultados[] = implode("\n", $matches[0] ?? []);
        }

        // Intento 2: extraer secuencias ASCII/Latin-1 legibles directamente
        // de los bytes crudos (funciona para .doc con texto en ASCII).
        preg_match_all('/[\x20-\x7E\xA0-\xFF]{6,}/', $bytes, $matchesRaw);
        $textoRaw = implode("\n", $matchesRaw[0] ?? []);
        $textoRaw = @mb_convert_encoding($textoRaw, 'UTF-8', 'Windows-1252') ?: $textoRaw;
        $resultados[] = $textoRaw;

        // Devolver el resultado con más texto legible en español.
        $mejor = '';
        $mejorScore = 0;
        foreach ($resultados as $texto) {
            $score = $this->scoreLegibilidad($texto);
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $texto;
            }
        }

        return $mejor;
    }

    /**
     * Mide qué tan legible es un texto en español (0.0 a 1.0).
     *
     * Cuenta la proporción de caracteres que son "esperados" en texto
     * español: letras (con acentos), dígitos, espacios, puntuación básica.
     * Un score > 0.50 indica texto probablemente legible.
     * Un score < 0.30 indica texto probablemente garbleado.
     *
     * Se usa para comparar resultados de distintos métodos de extracción
     * y elegir el que produzca el texto más legible.
     */
    public function scoreLegibilidad(string $texto): float
    {
        $texto = trim($texto);
        $total = mb_strlen($texto);

        if ($total < 10) {
            return 0.0;
        }

        // Caracteres esperados en texto español: letras (con acentos y ñ),
        // dígitos, espacios, puntuación básica, saltos de línea.
        $legibles = preg_match_all(
            '/[a-záéíóúñüA-ZÁÉÍÓÚÑÜ0-9\s\.\,\;\:\(\)\-\"\'\¿\?\¡\!\/°§]/u',
            $texto
        );

        return $legibles / $total;
    }

    private function extraerTextoDeElemento($elemento): string
    {
        if (method_exists($elemento, 'getText')) {
            return $elemento->getText();
        }

        if (method_exists($elemento, 'getElements')) {
            // El separador depende de QUÉ agrupa el elemento:
            //
            //   - Un TextRun son los trozos de UN MISMO párrafo (por ejemplo, una
            //     palabra en negritas dentro de una frase). Sus hijos van en la
            //     misma línea, así que se unen con un ESPACIO.
            //
            //   - Cualquier otro contenedor (tabla, celda, lista...) agrupa
            //     PÁRRAFOS distintos, así que sus hijos se separan con un SALTO
            //     DE LÍNEA.
            //
            // Antes se unía todo con espacio, y eso fusionaba las fracciones de un
            // artículo en un único párrafo corrido: al perderse los saltos de línea
            // del documento original, el estructurador ya no podía separarlas.
            $esMismoParrafo = $elemento instanceof \PhpOffice\PhpWord\Element\TextRun;
            $separador      = $esMismoParrafo ? ' ' : "\n";

            $partes = [];
            foreach ($elemento->getElements() as $hijo) {
                $partes[] = $this->extraerTextoDeElemento($hijo);
            }

            return implode($separador, $partes);
        }

        return '';
    }

    /**
     * Da formato Markdown básico al texto crudo extraído.
     * Antepone un encabezado con metadatos de la regulación.
     */
    private function formatearComoMarkdown(Regulacion $regulacion, string $textoCrudo): string
    {
        $cabecera = $this->generarCabecera($regulacion);

        // ── Limpiar artefactos binarios de archivos .doc OLE ──────────────
        $cuerpo = $textoCrudo;

        // 1. Bytes 0xFF (padding OLE) — pueden estar como byte crudo (\xFF)
        //    o como carácter Unicode ÿ (U+00FF). Ambos patrones se limpian.
        $cuerpo = preg_replace('/\xFF{2,}/', '', $cuerpo);
        $cuerpo = preg_replace('/\x{00FF}{2,}/u', '', $cuerpo);

        // 2. Firmas internas de Word: "bjbj", "uyuy", "juju" y variantes.
        //    Sin \b porque caracteres como µ (U+00B5) adyacentes impiden
        //    que PCRE detecte el word boundary correctamente.
        $cuerpo = preg_replace('/(bjbj|[µμ]?uyuy|[µμ]?juju)\w{0,4}/i', '', $cuerpo);

        // 3. Caracteres de control (0x00–0x08, 0x0B, 0x0C, 0x0E–0x1F).
        //    Se preservan \t (0x09), \n (0x0A) y \r (0x0D).
        $cuerpo = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $cuerpo);

        // 4. Bytes 0xFE y 0xFD (marcadores BOM y de estructura OLE).
        $cuerpo = preg_replace('/[\xFE\xFD]{2,}/', '', $cuerpo);

        // 5. Eliminar todo lo que precede la primera palabra real del contenido.
        //    Los artefactos OLE (ìYÁ, bytes sueltos) siempre aparecen ANTES
        //    del texto de la regulación. El contenido real empieza con una
        //    secuencia de 3+ letras (ej. "LEY", "ARTÍCULO", "TEXTO").
        $cuerpo = preg_replace('/^[^a-záéíóúñüA-ZÁÉÍÓÚÑÜ\n]*(?=[A-ZÁÉÍÓÚÑÜ][a-záéíóúñüA-ZÁÉÍÓÚÑÜ]{2})/su', '', $cuerpo);

        // 6. Espacio faltante después de puntuación.
        //
        //    Los PDF a veces codifican el espacio entre palabras únicamente
        //    como un desplazamiento visual del cursor de dibujo, sin guardar
        //    un carácter de espacio real en el contenido. smalot/pdfparser
        //    extrae solo los caracteres que existen en los datos, así que
        //    produce texto como "salud,la familia" en vez de "salud, la
        //    familia" — el espacio visual existía en el PDF, pero nunca fue
        //    un carácter real que la librería pudiera copiar.
        //
        //    Esta regla es segura porque en español una coma, punto, punto y
        //    coma o dos puntos SIEMPRE debe ir seguido de espacio antes de la
        //    siguiente letra. No hay ambigüedad: si falta el espacio ahí, es
        //    un error de extracción, nunca una palabra real que se rompería
        //    por accidente (a diferencia de intentar separar palabras
        //    pegadas SIN puntuación entre ellas, que si es ambiguo — ver el
        //    caso de "Lospreceptos"/"conotras" que explico aparte).
        $cuerpo = preg_replace('/([,;:\.])([a-záéíóúñüA-ZÁÉÍÓÚÑÜ])/u', '$1 $2', $cuerpo);

        // 7. Palabras completas pegadas sin ningún separador.
        //
        //    A diferencia del paso anterior, aquí no hay puntuación que
        //    marque dónde falta el espacio — son dos palabras reales
        //    concatenadas ("Los" + "preceptos" = "Lospreceptos") por el
        //    mismo problema de codificación del PDF, pero sin ninguna pista
        //    visible en el texto de dónde cortar.
        //
        //    SegmentadorPalabrasService resuelve esto verificando contra un
        //    diccionario real de español: solo corta una palabra si TODAS
        //    las partes resultantes son palabras reales. Si no encuentra un
        //    corte así de seguro, la deja intacta — nunca parte una palabra
        //    real por accidente (ver la documentación completa de la clase
        //    para el razonamiento detrás de esta regla).
        // Solo se segmenta el texto que viene de la librería PHP de respaldo, que
        // es la que pega palabras. Si lo extrajo pdftotext o LibreOffice, ya está
        // bien separado y segmentarlo solo puede romperlo.
        if (! $this->textoYaSeparado) {
            $cuerpo = $this->segmentador->aplicarATexto($cuerpo);
        }

        // ── Eliminar líneas que son solo basura (< 20% alfanumérico) ──────
        $lineas = explode("\n", $cuerpo);
        $lineas = array_filter($lineas, function ($linea) {
            $linea = trim($linea);
            if ($linea === '') return true;
            $total = mb_strlen($linea);
            if ($total < 3) return true;
            $alfanum = preg_match_all('/[\p{L}\p{N}]/u', $linea);
            return ($alfanum / $total) > 0.20;
        });
        $cuerpo = implode("\n", $lineas);

        $cuerpo = trim(preg_replace("/\n{3,}/", "\n\n", $cuerpo));

        return $cabecera . "\n\n" . $cuerpo . "\n";
    }

    private function generarCabecera(Regulacion $regulacion): string
    {
        $lineas = [
            '# ' . $regulacion->nombre,
            '',
            '> Regulación generada automáticamente desde el archivo original.',
            '',
        ];

        if ($regulacion->tipo) {
            $lineas[] = '**Tipo:** ' . $regulacion->tipo;
        }
        if ($regulacion->fecha_publicacion) {
            $lineas[] = '**Fecha de publicación:** ' . $regulacion->fecha_publicacion->format('d/m/Y');
        }
        if ($regulacion->fecha_vigencia) {
            $lineas[] = '**Fecha de vigencia:** ' . $regulacion->fecha_vigencia->format('d/m/Y');
        }

        return implode("\n", $lineas);
    }

    private function generarNombreArchivo(Regulacion $regulacion, string $extension): string
    {
        $base = Str::slug($regulacion->nombre, '-');
        $base = Str::limit($base, 80, '');
        return $regulacion->id . '-' . $base . '.' . $extension;
    }

    /**
     * Convierte un .doc a .docx usando LibreOffice, y devuelve la ruta del archivo
     * generado (temporal). Devuelve null si LibreOffice no está disponible o si la
     * conversión falla: en ese caso el llamador sigue con los otros métodos.
     *
     * Se hace porque el .doc (formato binario viejo de Word) no se puede leer bien
     * desde PHP: se pierden los párrafos y el texto llega corrido. LibreOffice sí
     * entiende el formato y conserva la estructura.
     */
    private function convertirDocConLibreOffice(string $ruta): ?string
    {
        $soffice = $this->pdfConversor->rutaLibreOffice();
        if ($soffice === null) {
            return null; // no está instalado: se usa la ruta alterna
        }

        // Carpeta temporal propia, para no dejar basura junto al original.
        $dirSalida = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'punta_doc_' . uniqid();
        if (! @mkdir($dirSalida) && ! is_dir($dirSalida)) {
            return null;
        }

        // Perfil de usuario aparte: LibreOffice lo necesita para correr sin
        // interfaz y sin chocar con otra instancia.
        $perfil    = $dirSalida . DIRECTORY_SEPARATOR . 'perfil';
        $perfilUrl = 'file:///' . str_replace('\\', '/', $perfil);

        $comando = escapeshellarg($soffice)
                 . ' --headless'
                 . ' -env:UserInstallation=' . escapeshellarg($perfilUrl)
                 . ' --convert-to docx'
                 . ' --outdir ' . escapeshellarg($dirSalida)
                 . ' ' . escapeshellarg($ruta)
                 . ' 2>&1';

        exec($comando, $salida, $codigo);

        $nombreDocx = pathinfo(basename($ruta), PATHINFO_FILENAME) . '.docx';
        $generado   = $dirSalida . DIRECTORY_SEPARATOR . $nombreDocx;

        if ($codigo !== 0 || ! file_exists($generado)) {
            \Illuminate\Support\Facades\Log::warning(
                'LibreOffice no pudo convertir el .doc: ' . implode(' ', (array) $salida)
            );
            return null;
        }

        return $generado;
    }

    /**
     * Extrae el texto de un documento de Word pasándolo por LibreOffice a PDF y de
     * ahí a texto con pdftotext. Devuelve null si LibreOffice o pdftotext no están
     * disponibles, o si algo falla: en ese caso el llamador usa PHPWord.
     *
     * Se hace así porque LibreOffice renderiza la numeración de las listas (los
     * "a)", "b)" que Word dibuja pero no guarda como texto), y pdftotext
     * conserva la disposición. Es el mismo camino que ya da buen resultado con los
     * PDF originales.
     */
    private function extraerWordViaPdf(string $ruta): ?string
    {
        $rutaPdf = $this->convertirConLibreOffice($ruta, 'pdf');
        if ($rutaPdf === null) {
            return null;
        }

        try {
            $texto = $this->extraerPdfConPdftotext($rutaPdf);
        } finally {
            @unlink($rutaPdf); // el PDF era temporal
        }

        return $texto;
    }

    /**
     * Convierte un documento con LibreOffice al formato indicado (pdf, docx, txt...)
     * y devuelve la ruta del archivo generado (temporal), o null si no se pudo.
     */
    private function convertirConLibreOffice(string $ruta, string $formato): ?string
    {
        $soffice = $this->pdfConversor->rutaLibreOffice();
        if ($soffice === null) {
            return null; // no está instalado
        }

        // Carpeta temporal propia, para no dejar archivos junto al original.
        $dirSalida = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'punta_conv_' . uniqid();
        if (! @mkdir($dirSalida) && ! is_dir($dirSalida)) {
            return null;
        }

        // Perfil de usuario aparte: LibreOffice lo necesita para correr sin interfaz
        // y para no chocar con otra instancia que estuviera abierta.
        $perfil    = $dirSalida . DIRECTORY_SEPARATOR . 'perfil';
        $perfilUrl = 'file:///' . str_replace('\\', '/', $perfil);

        $comando = escapeshellarg($soffice)
                 . ' --headless'
                 . ' -env:UserInstallation=' . escapeshellarg($perfilUrl)
                 . ' --convert-to ' . escapeshellarg($formato)
                 . ' --outdir ' . escapeshellarg($dirSalida)
                 . ' ' . escapeshellarg($ruta)
                 . ' 2>&1';

        exec($comando, $salida, $codigo);

        $nombre   = pathinfo(basename($ruta), PATHINFO_FILENAME) . '.' . $formato;
        $generado = $dirSalida . DIRECTORY_SEPARATOR . $nombre;

        if ($codigo !== 0 || ! file_exists($generado)) {
            \Illuminate\Support\Facades\Log::warning(
                "LibreOffice no pudo convertir a {$formato}: " . implode(' ', (array) $salida)
            );

            return null;
        }

        return $generado;
    }
}
