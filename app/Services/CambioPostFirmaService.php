<?php

namespace App\Services;

use App\Models\Diagrama;
use App\Models\Reingenieria;
use App\Models\Tramite;
use App\Models\User;
use App\Notifications\AvisoPunta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Servicio de detección y manejo de cambios post-firma.
 *
 * Cuando el flujo de un trámite (proceso_atencion) cambia DESPUÉS de que
 * la reingeniería fue firmada, este servicio:
 *
 *   1. Marca el trámite como "requiere revisión por cambio"
 *   2. Invalida los diagramas existentes (estado → requiere_actualizacion)
 *   3. Notifica al digitalizador con un mensaje claro
 *   4. Opcionalmente crea una nueva versión de reingeniería
 *
 * REGLA FUNDAMENTAL: la reingeniería firmada NUNCA se modifica.
 * Si cambia el proceso, se crea una nueva versión y se vuelve a firmar.
 */
class CambioPostFirmaService
{
    /**
     * Verifica si un trámite tiene una reingeniería firmada y, de ser así,
     * dispara las acciones de cambio post-firma.
     *
     * Se llama desde el Observer de ProcesoAtencion (updated/created/deleted)
     * y desde TramiteController cuando se editan datos clave del trámite.
     *
     * @param  Tramite  $tramite  El trámite que fue modificado.
     * @param  string   $origen   Descripción corta del cambio (ej. "paso editado", "requisito agregado").
     * @return bool     true si se detectó cambio post-firma, false si no aplica.
     */
    public function verificarYActuar(Tramite $tramite, string $origen = 'flujo modificado'): bool
    {
        $tramite->loadMissing('reingenieriaActiva');

        $reing = $tramite->reingenieriaActiva;

        // No aplica si no hay reingeniería o no está firmada
        if (!$reing || !$reing->estaFirmada()) {
            return false;
        }

        // Ya estaba marcado — no duplicar
        if ($tramite->digitalizacion_estado === Tramite::DIG_REQUIERE_REVISION) {
            return false;
        }

        Log::info('CambioPostFirma: detectado en trámite #{id} ({origen})', [
            'id'     => $tramite->id,
            'origen' => $origen,
            'reing'  => $reing->id,
        ]);

        // 1. Marcar el trámite
        $tramite->update([
            'digitalizacion_estado' => Tramite::DIG_REQUIERE_REVISION,
        ]);

        // 2. Invalidar diagramas existentes
        $this->invalidarDiagramas($reing);

        // 3. Notificar digitalizadores
        $this->notificar($tramite, $reing, $origen);

        return true;
    }

    /**
     * Crea una nueva versión de reingeniería a partir de la firmada,
     * copiando el flujo TO-BE como punto de partida para la corrección.
     *
     * Se llama manualmente cuando el digitalizador da clic en
     * "Crear nueva versión" desde la alerta de cambio.
     *
     * @param  Tramite  $tramite  El trámite afectado.
     * @return Reingenieria       La nueva versión creada.
     */
    public function crearNuevaVersion(Tramite $tramite): Reingenieria
    {
        $tramite->loadMissing('reingenieriaActiva');
        $anterior = $tramite->reingenieriaActiva;

        $nuevaVersion = ($anterior?->version ?? 0) + 1;

        $nueva = Reingenieria::create([
            'tramite_id'       => $tramite->id,
            'agenda_accion_id' => $anterior?->agenda_accion_id,
            'origen'           => $anterior?->origen ?? 'directa',
            'version'          => $nuevaVersion,
            'estado'           => Reingenieria::ESTADO_EN_REINGENIERIA,
            'flujo_to_be'      => $anterior?->flujo_to_be, // copia como base
            'created_by'       => Auth::id(),
        ]);

        // Limpiar la alerta del trámite (ya se está atendiendo)
        $tramite->update([
            'digitalizacion_estado' => Tramite::DIG_LISTA,
        ]);

        Log::info('CambioPostFirma: nueva versión v{v} creada para trámite #{id}', [
            'v'  => $nuevaVersion,
            'id' => $tramite->id,
        ]);

        return $nueva;
    }

    /**
     * Marca todos los diagramas de la reingeniería como "requiere actualización".
     */
    private function invalidarDiagramas(Reingenieria $reing): void
    {
        $reing->diagramas()
            ->whereNotIn('estado', [Diagrama::ESTADO_SUSTITUIDO])
            ->update(['estado' => Diagrama::ESTADO_REQUIERE_ACTUALIZACION]);
    }

    /**
     * Notifica a los digitalizadores sobre el cambio post-firma.
     */
    private function notificar(Tramite $tramite, Reingenieria $reing, string $origen): void
    {
        try {
            $digitalizadores = User::where('activo', true)
                ->whereHas('roles', fn ($q) => $q->where('codigo', 'digitalizador'))
                ->get();

            if ($digitalizadores->isEmpty()) {
                $digitalizadores = User::where('activo', true)
                    ->where('rol', 'digitalizador')
                    ->get();
            }

            if ($digitalizadores->isNotEmpty()) {
                Notification::send($digitalizadores, new AvisoPunta(
                    icono:   'ti-alert-triangle',
                    titulo:  'Cambio post-firma detectado',
                    mensaje: 'El trámite "' . $tramite->nombre_oficial . '" fue modificado después de firmar '
                        . 'la reingeniería v' . $reing->version . ' (' . $origen . '). '
                        . 'Debe generarse una nueva versión y recabar nuevamente las firmas.',
                    url:     route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria']),
                ));
            }
        } catch (Throwable $e) {
            Log::warning('CambioPostFirma: error al notificar', ['error' => $e->getMessage()]);
        }
    }
}
