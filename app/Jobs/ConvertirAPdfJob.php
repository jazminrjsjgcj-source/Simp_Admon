<?php

namespace App\Jobs;

use App\Models\Regulacion;
use App\Services\PdfConversorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ConvertirAPdfJob
 *
 * Procesa la conversión de un archivo Word a PDF en background,
 * sin bloquear la request del usuario. Se activa cuando la configuración
 * PDF_USAR_COLA=true en .env (para producción con queue worker corriendo).
 *
 * Para desarrollo local (PDF_USAR_COLA=false o QUEUE_CONNECTION=sync),
 * el controlador llama directamente a PdfConversorService sin pasar
 * por este Job.
 *
 * Uso:
 *   ConvertirAPdfJob::dispatch($regulacion);
 *
 * Reintentos: 3 intentos con 60 segundos entre cada uno.
 * Timeout: 300 segundos (5 minutos) para documentos muy grandes.
 */
class ConvertirAPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número de veces que se reintenta si falla. */
    public int $tries = 3;

    /** Tiempo máximo de ejecución en segundos antes de declarar timeout. */
    public int $timeout = 300;

    /** Segundos entre reintentos (60s = 1 minuto). */
    public int $backoff = 60;

    // ─────────────────────────────────────────────────────────────────────

    public function __construct(
        private readonly Regulacion $regulacion
    ) {}

    /**
     * Ejecuta la conversión a PDF en el worker de cola.
     *
     * Si la conversión falla (LibreOffice no disponible, archivo dañado,
     * timeout), el Job reintenta hasta $tries veces. Si agota los intentos,
     * el fallo se registra en el log y el Job pasa a la tabla failed_jobs.
     */
    public function handle(PdfConversorService $pdfConversor): void
    {
        Log::info("ConvertirAPdfJob: iniciando conversión para regulación #{$this->regulacion->id}");

        try {
            $rutaPdf = $pdfConversor->obtenerOGenerarPdf($this->regulacion);
            Log::info("ConvertirAPdfJob: PDF generado correctamente → {$rutaPdf}");
        } catch (Throwable $e) {
            Log::error(
                "ConvertirAPdfJob: falló para regulación #{$this->regulacion->id}: "
                . $e->getMessage()
            );
            // Re-lanzar para que Laravel marque el Job como fallido y reintente.
            throw $e;
        }
    }

    /**
     * Se llama cuando el Job agotó todos los reintentos y se declara fallido.
     * Registra el error final en el log para que el admin lo investigue.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical(
            "ConvertirAPdfJob: agotó {$this->tries} intentos para regulación "
            . "#{$this->regulacion->id}. Error final: " . $exception->getMessage()
        );
    }
}
