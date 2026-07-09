<?php

namespace App\Observers;

use App\Models\ProcesoAtencion;
use App\Services\CambioPostFirmaService;

/**
 * Observer de ProcesoAtencion — Fase 6 del Digitalizador.
 *
 * Detecta cuando un paso del flujo se crea, modifica o elimina DESPUÉS
 * de que la reingeniería del trámite fue firmada, y dispara las acciones
 * de cambio post-firma (alerta, invalidación de diagramas, notificación).
 *
 * Se registra en AppServiceProvider::boot().
 */
class ProcesoAtencionObserver
{
    /**
     * Después de crear un nuevo paso.
     */
    public function created(ProcesoAtencion $paso): void
    {
        $this->verificarCambio($paso, 'paso agregado');
    }

    /**
     * Después de modificar un paso existente.
     * Solo dispara si cambió algo significativo (no solo timestamps).
     */
    public function updated(ProcesoAtencion $paso): void
    {
        $camposSignificativos = ['accion', 'detalle', 'area', 'tipo_paso', 'actor', 'tipo', 'paso', 'subpaso', 'entrada', 'salida'];

        $cambioReal = collect($paso->getChanges())
            ->keys()
            ->intersect($camposSignificativos)
            ->isNotEmpty();

        if ($cambioReal) {
            $this->verificarCambio($paso, 'paso editado');
        }
    }

    /**
     * Después de eliminar un paso.
     */
    public function deleted(ProcesoAtencion $paso): void
    {
        $this->verificarCambio($paso, 'paso eliminado');
    }

    /**
     * Delega al servicio de cambio post-firma.
     * Envuelto en try-catch para que un error nunca bloquee la operación
     * principal del usuario (editar/crear/eliminar pasos).
     */
    private function verificarCambio(ProcesoAtencion $paso, string $origen): void
    {
        try {
            $tramite = $paso->tramite;
            if (!$tramite) {
                return;
            }

            app(CambioPostFirmaService::class)->verificarYActuar($tramite, $origen);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ProcesoAtencionObserver: error', [
                'error' => $e->getMessage(),
                'paso'  => $paso->id ?? null,
            ]);
        }
    }
}
