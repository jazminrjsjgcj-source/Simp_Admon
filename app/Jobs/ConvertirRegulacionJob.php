<?php

namespace App\Jobs;

use App\Models\Regulacion;
use App\Services\RegulacionConversorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job que dispara la conversión del archivo original (PDF/Word)
 * de una regulación a Markdown. Se ejecuta en background para no
 * bloquear la respuesta al usuario después de subir el archivo.
 */
class ConvertirRegulacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public Regulacion $regulacion) {}

    public function handle(RegulacionConversorService $conversor): void
    {
        $conversor->convertirAMarkdown($this->regulacion);
    }

    public function failed(\Throwable $e): void
    {
        $this->regulacion->update([
            'conversion_estatus' => Regulacion::CONVERSION_ERROR,
            'conversion_error'   => $e->getMessage(),
        ]);
    }
}
