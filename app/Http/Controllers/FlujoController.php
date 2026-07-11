<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use Illuminate\Http\Request;

/**
 * Controlador del flujo del proceso (levantamiento AS-IS).
 *
 * Gestiona el ciclo de vida del levantamiento:
 *   sin_flujo → flujo_en_captura → flujo_en_revision → flujo_aprobado
 *                                        ↓
 *                                  flujo_observado → flujo_en_captura
 *
 * El enlace captura y envía a revisión.
 * El revisor aprueba u observa.
 * Solo un flujo aprobado permite iniciar la reingeniería TO-BE.
 */
class FlujoController extends Controller
{
    /**
     * POST /tramites/{tramite}/flujo/iniciar
     *
     * Marca que el enlace comenzó a capturar el flujo.
     * Cambia el estado de sin_flujo a flujo_en_captura.
     */
    public function iniciar(Tramite $tramite)
    {
        if ($tramite->flujo_estado !== Tramite::FLUJO_SIN_FLUJO) {
            return back()->with('error', 'El flujo ya fue iniciado anteriormente.');
        }

        $tramite->update(['flujo_estado' => Tramite::FLUJO_EN_CAPTURA]);

        return back()->with('success', 'Levantamiento del flujo iniciado. Capture los pasos del proceso.');
    }

    /**
     * POST /tramites/{tramite}/flujo/enviar-revision
     *
     * El enlace envía el levantamiento a revisión.
     * Requiere que haya al menos un paso capturado.
     */
    public function enviarRevision(Tramite $tramite)
    {
        if (!in_array($tramite->flujo_estado, [Tramite::FLUJO_EN_CAPTURA, Tramite::FLUJO_OBSERVADO])) {
            return back()->with('error', 'El flujo no está en estado de captura.');
        }

        if ($tramite->procesosAtencion()->count() === 0) {
            return back()->with('error', 'Debe capturar al menos un paso del proceso antes de enviar a revisión.');
        }

        $tramite->update([
            'flujo_estado'     => Tramite::FLUJO_EN_REVISION,
            'flujo_enviado_en' => now(),
        ]);

        return back()->with('success', 'Flujo enviado a revisión. El revisor debe aprobarlo para que pueda iniciar la reingeniería.');
    }

    /**
     * POST /tramites/{tramite}/flujo/aprobar
     *
     * El revisor aprueba el levantamiento del flujo.
     * Solo revisores y admin pueden aprobar.
     */
    public function aprobar(Request $request, Tramite $tramite)
    {
        $user = $request->user();

        if (!$user->tienePermiso('tramites.aprobar')) {
            return back()->with('error', 'No tiene permiso para aprobar flujos.');
        }

        if ($tramite->flujo_estado !== Tramite::FLUJO_EN_REVISION) {
            return back()->with('error', 'El flujo no está en revisión.');
        }

        $tramite->update([
            'flujo_estado'      => Tramite::FLUJO_APROBADO,
            'flujo_aprobado_en' => now(),
            'flujo_aprobado_por'=> $user->id,
        ]);

        return back()->with('success', 'Flujo aprobado. El digitalizador puede iniciar la reingeniería TO-BE.');
    }

    /**
     * POST /tramites/{tramite}/flujo/observar
     *
     * El revisor observa el levantamiento (requiere correcciones).
     * Lo regresa a captura para que el enlace lo corrija.
     */
    public function observar(Request $request, Tramite $tramite)
    {
        $user = $request->user();

        if (!$user->tienePermiso('tramites.observar')) {
            return back()->with('error', 'No tiene permiso para observar flujos.');
        }

        if ($tramite->flujo_estado !== Tramite::FLUJO_EN_REVISION) {
            return back()->with('error', 'El flujo no está en revisión.');
        }

        $request->validate([
            'observacion_flujo' => 'required|string|max:1000',
        ]);

        $tramite->update(['flujo_estado' => Tramite::FLUJO_OBSERVADO]);

        // Registrar la observación en el sistema de observaciones existente
        $tramite->observaciones()->create([
            'seccion'     => 'Flujo del proceso',
            'texto'       => $request->observacion_flujo,
            'usuario_id'  => $user->id,
            'estatus'     => 'pendiente',
        ]);

        return back()->with('success', 'Flujo observado. El enlace debe corregir y reenviar.');
    }
}
