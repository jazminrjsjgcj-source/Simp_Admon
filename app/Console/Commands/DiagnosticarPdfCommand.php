<?php

namespace App\Console\Commands;

use App\Services\PdfConversorService;
use Illuminate\Console\Command;

/**
 * DiagnosticarPdfCommand
 *
 * Verifica que el entorno de generación de PDF esté correctamente
 * configurado. Útil para diagnosticar problemas en producción sin
 * necesidad de revisar el código fuente o los logs.
 *
 * Uso:
 *   php artisan punta:diagnosticar-pdf
 *
 * Qué verifica:
 *   - Si LibreOffice está instalado y accesible.
 *   - La ruta exacta del ejecutable y su versión.
 *   - Si Dompdf está instalado como dependencia de Composer.
 *   - El directorio de caché de PDFs y si es escribible.
 *   - Cuántos PDFs cacheados existen actualmente.
 *   - Las variables de entorno relevantes.
 */
class DiagnosticarPdfCommand extends Command
{
    protected $signature   = 'punta:diagnosticar-pdf';
    protected $description = 'Verifica el entorno de conversión de Word a PDF (LibreOffice, Dompdf, caché).';

    public function handle(PdfConversorService $pdfConversor): int
    {
        $this->newLine();
        $this->components->info('PUNTA — Diagnóstico de conversión PDF');
        $this->newLine();

        // ── LibreOffice ──────────────────────────────────────────────────
        $this->line('  <comment>LibreOffice (conversión de alta fidelidad)</comment>');

        if ($pdfConversor->libreOfficeDisponible()) {
            $ruta    = $pdfConversor->rutaLibreOffice();
            $version = trim((string) @shell_exec("\"{$ruta}\" --version 2>/dev/null"));
            $this->components->twoColumnDetail('  Estado',    '<fg=green>✓ Disponible</>');
            $this->components->twoColumnDetail('  Ruta',      $ruta ?? '—');
            $this->components->twoColumnDetail('  Versión',   $version ?: 'No se pudo leer');
        } else {
            $this->components->twoColumnDetail('  Estado', '<fg=yellow>✗ No encontrado</>');
            $this->line('  <fg=gray>  → Instale LibreOffice o configure LIBREOFFICE_PATH en .env</>');
            $this->line('  <fg=gray>  → Linux:   sudo apt-get install libreoffice-writer-nogui</>');
            $this->line('  <fg=gray>  → Windows: https://www.libreoffice.org/download/</>');
        }

        $this->newLine();

        // ── Dompdf ───────────────────────────────────────────────────────
        $this->line('  <comment>Dompdf (fallback — solo texto, sin tablas ni imágenes)</comment>');

        if ($pdfConversor->dompdfDisponible()) {
            $this->components->twoColumnDetail('  Estado', '<fg=green>✓ Disponible</>');
        } else {
            $this->components->twoColumnDetail('  Estado', '<fg=yellow>✗ No instalado</>');
            $this->line('  <fg=gray>  → Instale con: composer require barryvdh/laravel-dompdf</>');
        }

        $this->newLine();

        // ── Directorio de caché ──────────────────────────────────────────
        $this->line('  <comment>Caché de PDFs</comment>');

        $dirRelativo = config('services.punta.pdf.cache_dir', 'regulaciones/pdf');
        $dirAbsoluto = storage_path('app/' . $dirRelativo);

        $this->components->twoColumnDetail('  Directorio', $dirAbsoluto);

        if (is_dir($dirAbsoluto)) {
            $escribible = is_writable($dirAbsoluto);
            $this->components->twoColumnDetail(
                '  Permisos',
                $escribible ? '<fg=green>✓ Escribible</>' : '<fg=red>✗ Sin permisos de escritura</>'
            );

            $pdfs = glob($dirAbsoluto . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
            $this->components->twoColumnDetail('  PDFs cacheados', count($pdfs) . ' archivos');
        } else {
            $this->components->twoColumnDetail('  Existe', '<fg=yellow>✗ El directorio no existe (se crea al primer uso)</>');
        }

        $this->newLine();

        // ── Variables de entorno ─────────────────────────────────────────
        $this->line('  <comment>Variables de entorno</comment>');
        $this->components->twoColumnDetail('  LIBREOFFICE_PATH',  env('LIBREOFFICE_PATH', '<fg=gray>(vacío — autodetección)</>'));
        $this->components->twoColumnDetail('  PDF_CACHE_DIR',     env('PDF_CACHE_DIR', '<fg=gray>(usando valor por defecto: regulaciones/pdf)</>'));
        $this->components->twoColumnDetail('  PDF_TIMEOUT',       env('PDF_TIMEOUT', '<fg=gray>(usando valor por defecto: 90s)</>'));
        $this->components->twoColumnDetail('  PDF_USAR_COLA',     env('PDF_USAR_COLA', '<fg=gray>(usando valor por defecto: false)</>'));
        $this->components->twoColumnDetail('  QUEUE_CONNECTION',  env('QUEUE_CONNECTION', '—'));

        $this->newLine();

        // ── Resumen ──────────────────────────────────────────────────────
        $this->line('  <comment>Resumen</comment>');

        if ($pdfConversor->libreOfficeDisponible()) {
            $this->components->twoColumnDetail('  Motor activo',    '<fg=green>LibreOffice (alta fidelidad)</>');
        } elseif ($pdfConversor->dompdfDisponible()) {
            $this->components->twoColumnDetail('  Motor activo',    '<fg=yellow>Dompdf (calidad básica — solo texto)</>');
        } else {
            $this->components->twoColumnDetail('  Motor activo',    '<fg=red>Ninguno — la descarga de PDF fallará</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
