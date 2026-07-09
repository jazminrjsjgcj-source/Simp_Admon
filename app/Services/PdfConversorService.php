<?php

namespace App\Services;

use App\Models\Regulacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PdfConversorService
 *
 * Encapsula toda la lógica de conversión y generación de archivos PDF
 * para regulaciones. Separa esta responsabilidad del controlador y del
 * conversor de Markdown, siguiendo el principio de responsabilidad única
 * (Clean Code §10: clases pequeñas con una responsabilidad principal).
 *
 * Estrategia de generación (en orden de prioridad):
 *   1. Si el archivo original ya es PDF → devolver la ruta original.
 *   2. Si existe un PDF cacheado válido → devolverlo sin regenerar.
 *   3. Si LibreOffice está disponible → convertir el Word original.
 *   4. Si Dompdf está instalado → generar desde el Markdown extraído.
 *   5. Si nada está disponible → lanzar RuntimeException con instrucciones.
 *
 * El caché vive en storage/app/{PDF_CACHE_DIR}/ y se invalida
 * automáticamente cuando se llama a invalidarCache() (al reconvertir
 * o reemplazar el archivo original).
 *
 * Configurable desde .env:
 *   LIBREOFFICE_PATH   → ruta al ejecutable soffice
 *   PDF_CACHE_DIR      → carpeta del caché (relativa a storage/app/)
 *   PDF_TIMEOUT        → segundos máximos para LibreOffice
 */
class PdfConversorService
{
    // ── Constante de estilos para el PDF generado por Dompdf ────────────
    // Usa DejaVu Sans porque es la única fuente de Dompdf que incluye
    // caracteres Unicode completos para español (á, é, í, ó, ú, ñ, ü).
    private const ESTILOS_DOMPDF = <<<'CSS'
        body {
            font-family: DejaVu Sans, sans-serif;
            max-width: 100%;
            margin: 0;
            padding: 24px 32px;
            line-height: 1.6;
            color: #1a1a1a;
            font-size: 11px;
        }
        h1 { font-size: 16px; border-bottom: 2px solid #ccc; padding-bottom: 6px; margin-top: 20px; }
        h2 { font-size: 14px; margin-top: 16px; }
        h3 { font-size: 12px; }
        h4 { font-size: 11px; }
        p  { margin: 4px 0; }
    CSS;

    // ── Rutas donde buscar LibreOffice si no está en PATH ───────────────
    private const RUTAS_LIBREOFFICE_WINDOWS = [
        'C:\Program Files\LibreOffice\program\soffice.exe',
        'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
    ];

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Devuelve la ruta absoluta al PDF de la regulación, generándolo
     * si aún no existe. Es el punto de entrada principal del servicio.
     *
     * @throws \RuntimeException Si no hay ningún motor disponible para generar el PDF.
     * @return string Ruta absoluta al archivo PDF.
     */
    public function obtenerOGenerarPdf(Regulacion $regulacion): string
    {
        // Estrategia 1: el archivo original ya es PDF — nada que convertir.
        if ($regulacion->extension_original === 'pdf') {
            return $this->rutaAbsolutaOriginal($regulacion);
        }

        // Estrategia 2: existe un PDF cacheado de una conversión anterior.
        $cache = $this->rutaCacheAbsoluta($regulacion);
        if ($cache !== null && file_exists($cache)) {
            return $cache;
        }

        // Estrategia 3: convertir con LibreOffice (fidelidad completa).
        if ($this->libreOfficeDisponible()) {
            return $this->convertirConLibreOffice($regulacion);
        }

        // Estrategia 4: generar con Dompdf desde el Markdown extraído.
        if ($this->dompdfDisponible()) {
            return $this->generarConDompdf($regulacion);
        }

        throw new \RuntimeException(
            'No hay motor de PDF disponible. '
            . 'Instale LibreOffice (recomendado, alta fidelidad) o ejecute: composer require barryvdh/laravel-dompdf'
        );
    }

    /**
     * Borra los archivos cacheados de la regulación (PDF y DOCX), si existen.
     * Se llama cuando se reconvierte o se reemplaza el archivo original,
     * para que la siguiente descarga genere archivos actualizados.
     */
    public function invalidarCache(Regulacion $regulacion): void
    {
        $rutaPdf = $this->rutaCacheAbsoluta($regulacion);
        if ($rutaPdf !== null && file_exists($rutaPdf)) {
            unlink($rutaPdf);
            Log::info("PDF cacheado eliminado para regulación #{$regulacion->id}: {$rutaPdf}");
        }

        $rutaDocx = $this->rutaCacheDocxAbsoluta($regulacion);
        if ($rutaDocx !== null && file_exists($rutaDocx)) {
            unlink($rutaDocx);
            Log::info("DOCX cacheado eliminado para regulación #{$regulacion->id}: {$rutaDocx}");
        }
    }

    /**
     * Devuelve la ruta absoluta al DOCX de la regulación, generándolo
     * si aún no existe. Análogo a obtenerOGenerarPdf() pero para Word.
     *
     * Estrategia:
     *   1. Si el archivo original ya es DOC/DOCX → devolverlo directamente.
     *   2. Si existe un DOCX cacheado → devolverlo sin regenerar.
     *   3. Si LibreOffice está disponible → convertir el PDF original.
     *
     * @throws \RuntimeException Si el archivo es PDF y LibreOffice no está disponible.
     * @return string Ruta absoluta al archivo DOCX.
     */
    public function obtenerOGenerarDocx(Regulacion $regulacion): string
    {
        // Estrategia 1: el archivo original ya es Word — nada que convertir.
        if (in_array($regulacion->extension_original, ['doc', 'docx'], true)) {
            return $this->rutaAbsolutaOriginal($regulacion);
        }

        // Estrategia 2: existe un DOCX cacheado de una conversión anterior.
        $cache = $this->rutaCacheDocxAbsoluta($regulacion);
        if ($cache !== null && file_exists($cache)) {
            return $cache;
        }

        // Estrategia 3: convertir el PDF a DOCX con LibreOffice.
        if (!$this->libreOfficeDisponible()) {
            throw new \RuntimeException(
                'La descarga como Word requiere LibreOffice instalado en el servidor. '
                . 'Instale LibreOffice y configure LIBREOFFICE_PATH en el .env.'
            );
        }

        return $this->convertirConLibreOfficeADocx($regulacion);
    }

    /**
     * Indica si LibreOffice está instalado y accesible en este servidor.
     * Se usa para mostrar información de diagnóstico al admin.
     */
    public function libreOfficeDisponible(): bool
    {
        return $this->detectarRutaLibreOffice() !== null;
    }

    /**
     * Indica si Dompdf está instalado como dependencia de Composer.
     */
    public function dompdfDisponible(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
    }

    /**
     * Devuelve la ruta al ejecutable de LibreOffice, o null si no está
     * disponible. Útil para el comando de diagnóstico.
     */
    public function rutaLibreOffice(): ?string
    {
        return $this->detectarRutaLibreOffice();
    }

    // ── Métodos privados ─────────────────────────────────────────────────

    /**
     * Devuelve la ruta absoluta al archivo original de la regulación.
     * Solo se usa cuando el original ya es PDF (estrategia 1).
     *
     * @throws \RuntimeException Si el archivo no existe en disco.
     */
    private function rutaAbsolutaOriginal(Regulacion $regulacion): string
    {
        $ruta = Storage::disk('local')->path($regulacion->archivo_original);
        if (!file_exists($ruta)) {
            throw new \RuntimeException(
                "El archivo original no existe en disco: {$regulacion->archivo_original}"
            );
        }
        return $ruta;
    }

    /**
     * Calcula la ruta absoluta donde se cachearía el PDF generado para
     * esta regulación. Devuelve null si no hay archivo original.
     *
     * El nombre del PDF cacheado es el mismo nombre base del archivo original
     * con extensión .pdf. Si el archivo original es "82-ley-hacienda.doc",
     * el caché será "82-ley-hacienda.pdf" en el directorio configurado.
     */
    private function rutaCacheAbsoluta(Regulacion $regulacion): ?string
    {
        if (empty($regulacion->archivo_original)) {
            return null;
        }

        $nombreBase = pathinfo(basename($regulacion->archivo_original), PATHINFO_FILENAME);
        $dir        = storage_path('app/' . config('services.punta.pdf.cache_dir', 'regulaciones/pdf'));

        return $dir . DIRECTORY_SEPARATOR . $nombreBase . '.pdf';
    }

    /**
     * Calcula la ruta absoluta donde se cachearía el DOCX generado para
     * esta regulación. Análogo a rutaCacheAbsoluta() pero con extensión .docx.
     * Solo aplica cuando el original es PDF y se convierte vía LibreOffice.
     */
    private function rutaCacheDocxAbsoluta(Regulacion $regulacion): ?string
    {
        if (empty($regulacion->archivo_original)) {
            return null;
        }

        $nombreBase = pathinfo(basename($regulacion->archivo_original), PATHINFO_FILENAME);
        $dir        = storage_path('app/' . config('services.punta.pdf.cache_dir', 'regulaciones/pdf'));

        return $dir . DIRECTORY_SEPARATOR . $nombreBase . '.docx';
    }

    /**
     * Convierte el PDF original a DOCX usando LibreOffice.
     *
     * LibreOffice puede hacer la conversión inversa (PDF → DOCX) con el mismo
     * comando que usa para Word → PDF, cambiando el formato de salida.
     * La fidelidad es mayor que la de un conversor online, aunque no perfecta:
     * el PDF pierde parte de la información de layout que sí tenía el Word original.
     *
     * El resultado se cachea en el mismo directorio que los PDFs. Se invalida
     * automáticamente cuando se llama a invalidarCache() (al reconvertir o al
     * reemplazar el archivo original).
     *
     * @throws \RuntimeException Si LibreOffice devuelve código de error != 0.
     */
    /**
     * Convierte el PDF original a DOCX usando LibreOffice.
     *
     * Tres diferencias clave respecto a convertirConLibreOffice() (Word→PDF):
     *
     * 1. --infilter=writer_pdf_import
     *    LibreOffice no sabe qué filtro usar para abrir un .pdf a menos que se
     *    lo indiques. Sin este flag, la detección automática falla y el proceso
     *    termina con código 1 sin hacer nada. Para DOCX/DOC esto no es necesario
     *    porque LibreOffice los abre nativamente.
     *
     * 2. 2>&1 en Windows
     *    La versión Windows del comando original no redirigía stderr, así que los
     *    mensajes de error de LibreOffice se perdían y el log quedaba vacío. Con
     *    2>&1 el error queda registrado en laravel.log y se puede diagnosticar.
     *
     * 3. -env:UserInstallation con directorio único (guión simple, nombre correcto)
     *    LibreOffice bloquea su directorio de perfil de usuario mientras corre.
     *    Si dos conversiones se ejecutan en paralelo (dos usuarios descargando al
     *    mismo tiempo), la segunda ve el perfil bloqueado y falla con código 1.
     *    Cada llamada genera su propio directorio temporal con uniqid().
     *
     * @throws \RuntimeException Si LibreOffice devuelve código de error != 0.
     */
    private function convertirConLibreOfficeADocx(Regulacion $regulacion): string
    {
        $rutaOriginal = Storage::disk('local')->path($regulacion->archivo_original);
        $dirSalida    = storage_path('app/' . config('services.punta.pdf.cache_dir', 'regulaciones/pdf'));
        $timeout      = (int) config('services.punta.pdf.timeout_segundos', 90);

        if (!is_dir($dirSalida)) {
            mkdir($dirSalida, 0755, true);
        }

        $soffice   = $this->detectarRutaLibreOffice();
        $perfilUrl = $this->perfilTemporalLibreOffice();

        if (PHP_OS_FAMILY === 'Windows') {
            $comando = "\"{$soffice}\""
                     . " --headless"
                     . " -env:UserInstallation={$perfilUrl}"
                     . " --infilter=writer_pdf_import"
                     . " --convert-to docx"
                     . " --outdir \"{$dirSalida}\""
                     . " \"{$rutaOriginal}\""
                     . " 2>&1";
        } else {
            $comando = "timeout {$timeout} \"{$soffice}\""
                     . " --headless"
                     . " -env:UserInstallation={$perfilUrl}"
                     . " --infilter=writer_pdf_import"
                     . " --convert-to docx"
                     . " --outdir \"{$dirSalida}\""
                     . " \"{$rutaOriginal}\" 2>&1";
        }

        exec($comando, $salida, $codigoSalida);

        $nombreDocx   = pathinfo(basename($rutaOriginal), PATHINFO_FILENAME) . '.docx';
        $rutaGenerada = $dirSalida . DIRECTORY_SEPARATOR . $nombreDocx;

        if ($codigoSalida !== 0 || !file_exists($rutaGenerada)) {
            $salidaTexto = implode("\n", $salida);
            Log::error(
                "LibreOffice falló al convertir PDF→DOCX para regulación #{$regulacion->id}. "
                . "Código: {$codigoSalida}. Salida: {$salidaTexto}"
            );
            throw new \RuntimeException(
                "LibreOffice no pudo convertir el PDF a Word (código: {$codigoSalida}). "
                . "La causa más frecuente es que el módulo 'PDF Import' de LibreOffice no "
                . "está instalado. Reinstale LibreOffice con todos los componentes activados. "
                . "El detalle completo del error está en storage/logs/laravel.log."
            );
        }

        Log::info("DOCX generado con LibreOffice para regulación #{$regulacion->id}: {$rutaGenerada}");

        return $rutaGenerada;
    }

    /**
     * Genera una URL de perfil temporal y único para una invocación de LibreOffice.
     *
     * LibreOffice bloquea su directorio de perfil (~/.config/libreoffice o
     * AppData\Roaming\LibreOffice en Windows) durante la ejecución. Cuando dos
     * procesos LibreOffice corren al mismo tiempo, el segundo ve el perfil bloqueado
     * y falla con código 1.
     *
     * La solución es darle a cada invocación su propio directorio de perfil vacío
     * via -env:UserInstallation (flag de LibreOffice con guión simple). El directorio
     * se crea bajo el directorio de temporales del SO y LibreOffice lo borra al
     * terminar (o queda huérfano si falla, lo cual es inofensivo — el SO limpia
     * el directorio de temporales periódicamente).
     */
    private function perfilTemporalLibreOffice(): string
    {
        $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lo-punta-' . uniqid('', true);

        // 1. Normalizar backslashes de Windows a forward slashes para la URL.
        //    sys_get_temp_dir() en Windows devuelve algo como C:\Users\JG\AppData\Local\Temp
        //    y LibreOffice espera file:///C:/Users/JG/AppData/Local/Temp/...
        $ruta = str_replace('\\', '/', $ruta);
        $ruta = ltrim($ruta, '/');

        // 2. Codificar espacios en el path.
        //    Si el nombre de usuario de Windows tiene espacios (ej. "Juan Garcia"),
        //    la ruta contendría "C:/Users/Juan Garcia/..." con un espacio literal
        //    que no es válido en una URL file://. Se codifica como %20.
        $ruta = str_replace(' ', '%20', $ruta);

        return 'file:///' . $ruta;
    }

    /**
     * Convierte el archivo Word original a PDF usando LibreOffice.
     * LibreOffice preserva tablas, imágenes, tipografías y formato.
     *
     * Guarda el resultado en el directorio de caché para no regenerar
     * en cada descarga. Si LibreOffice falla (timeout, formato corrupto,
     * permisos), lanza una RuntimeException con el código de error.
     *
     * @throws \RuntimeException Si LibreOffice devuelve código de error != 0.
     */
    private function convertirConLibreOffice(Regulacion $regulacion): string
    {
        $rutaOriginal = Storage::disk('local')->path($regulacion->archivo_original);
        $dirSalida    = storage_path('app/' . config('services.punta.pdf.cache_dir', 'regulaciones/pdf'));
        $timeout      = (int) config('services.punta.pdf.timeout_segundos', 90);

        if (!is_dir($dirSalida)) {
            mkdir($dirSalida, 0755, true);
        }

        $soffice = $this->detectarRutaLibreOffice();

        // En Windows, las rutas con espacios requieren comillas dobles.
        // timeout es específico de Linux/macOS; en Windows se omite porque
        // exec() no soporta el comando timeout de la misma forma.
        if (PHP_OS_FAMILY === 'Windows') {
            $comando = "\"{$soffice}\" --headless --convert-to pdf"
                     . " --outdir \"{$dirSalida}\""
                     . " \"{$rutaOriginal}\"";
        } else {
            $comando = "timeout {$timeout} \"{$soffice}\" --headless --convert-to pdf"
                     . " --outdir \"{$dirSalida}\""
                     . " \"{$rutaOriginal}\" 2>&1";
        }

        exec($comando, $salida, $codigoSalida);

        $nombrePdf   = pathinfo(basename($rutaOriginal), PATHINFO_FILENAME) . '.pdf';
        $rutaGenerada = $dirSalida . DIRECTORY_SEPARATOR . $nombrePdf;

        if ($codigoSalida !== 0 || !file_exists($rutaGenerada)) {
            $salidaTexto = implode("\n", $salida);
            Log::error("LibreOffice falló para regulación #{$regulacion->id}. Código: {$codigoSalida}. Salida: {$salidaTexto}");
            throw new \RuntimeException(
                "LibreOffice falló al convertir el archivo (código de error: {$codigoSalida}). "
                . "Revise storage/logs/laravel.log para más detalles."
            );
        }

        Log::info("PDF generado con LibreOffice para regulación #{$regulacion->id}: {$rutaGenerada}");

        return $rutaGenerada;
    }

    /**
     * Genera un PDF básico desde el Markdown de la regulación usando Dompdf.
     * Solo conserva texto y encabezados; pierde tablas, imágenes y formato.
     * Se usa como fallback cuando LibreOffice no está disponible.
     *
     * El PDF se genera en memoria y se guarda en un archivo temporal en el
     * directorio de caché. No se cachea permanentemente (Dompdf es rápido
     * y regenerar es barato en comparación con LibreOffice).
     *
     * @throws \RuntimeException Si la regulación no tiene Markdown o está vacío.
     */
    private function generarConDompdf(Regulacion $regulacion): string
    {
        if (empty($regulacion->archivo_markdown) || !Storage::disk('local')->exists($regulacion->archivo_markdown)) {
            throw new \RuntimeException(
                'No hay contenido Markdown para generar el PDF. '
                . 'Use "Reintentar conversión" primero.'
            );
        }

        $markdown = Storage::disk('local')->get($regulacion->archivo_markdown);

        if (trim($markdown) === '') {
            throw new \RuntimeException(
                'El contenido Markdown está vacío. No se puede generar el PDF. '
                . 'Intente reconvertir el archivo.'
            );
        }

        $html   = \Illuminate\Support\Str::markdown($markdown);
        $pagina = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
                . '<style>' . self::ESTILOS_DOMPDF . '</style>'
                . '</head><body>' . $html . '</body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($pagina)
            ->setPaper('letter', 'portrait');

        // Guardar en archivo temporal para poder servirlo con response()->download().
        $dir      = storage_path('app/' . config('services.punta.pdf.cache_dir', 'regulaciones/pdf'));
        $nombrePdf = pathinfo(basename($regulacion->archivo_markdown ?? 'tmp'), PATHINFO_FILENAME) . '.pdf';
        $rutaSalida = $dir . DIRECTORY_SEPARATOR . $nombrePdf;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($rutaSalida, $pdf->output());

        Log::info("PDF generado con Dompdf para regulación #{$regulacion->id}: {$rutaSalida}");

        return $rutaSalida;
    }

    /**
     * Detecta la ruta al ejecutable de LibreOffice en el sistema actual.
     *
     * Orden de prioridad:
     *   1. Variable de entorno LIBREOFFICE_PATH (configurada en .env).
     *   2. Detección automática en el PATH del sistema operativo.
     *   3. Rutas de instalación comunes en Windows.
     *
     * Devuelve null si LibreOffice no se encuentra por ninguna vía.
     */
    private function detectarRutaLibreOffice(): ?string
    {
        // 1. Ruta explícita en .env — máxima prioridad.
        $configurada = config('services.libreoffice.path', '');
        if ($configurada && file_exists($configurada)) {
            return $configurada;
        }

        // 2. Detección en PATH del sistema.
        // @shell_exec suprimido con @ porque puede estar desactivado en producción
        // con disable_functions en php.ini. Si falla, devuelve null o cadena vacía.
        $comando = PHP_OS_FAMILY === 'Windows' ? 'where soffice 2>NUL' : 'which soffice 2>/dev/null';
        $enPath  = trim((string) @shell_exec($comando));
        if ($enPath !== '') {
            return $enPath;
        }

        // 3. Rutas comunes en Windows.
        foreach (self::RUTAS_LIBREOFFICE_WINDOWS as $ruta) {
            if (file_exists($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
