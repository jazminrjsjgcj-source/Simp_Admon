<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\AnalisisImpactoRegulatorio;
use App\Models\ExencionAir;
use App\Models\PropuestaRegulatoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fase E — Análisis de Impacto Regulatorio (AIR).
 *
 * Gestiona la creación, actualización y dictamen del AIR
 * asociado a una propuesta regulatoria, más el flujo de exención.
 *
 * Flujo del AIR:
 *   borrador → enviado → en_dictamen → dictaminado
 *
 * Flujo de exención:
 *   solicitada → aprobada | rechazada
 */
class AirController extends Controller
{
    // ─── Formulario AIR ───────────────────────────────────────────

    /**
     * Muestra el formulario de creación/edición del AIR.
     * Si ya existe un AIR, carga sus datos para edición.
     */
    public function formulario(PropuestaRegulatoria $propuesta)
    {
        $user = request()->user();
        $this->autorizarAccesoAir($propuesta, $user);

        $air = $propuesta->air;
        return view('screens.agenda-regulatoria.air-formulario', compact('propuesta', 'air'));
    }

    /**
     * Crea o actualiza el AIR de la propuesta.
     * Si la acción es 'enviar', cambia el estatus a 'enviado'.
     */
    public function guardar(Request $request, PropuestaRegulatoria $propuesta)
    {
        $user = $request->user();
        $this->autorizarAccesoAir($propuesta, $user);

        $validated = $request->validate([
            'problematica'         => 'nullable|string|max:5000',
            'objetivos'            => 'nullable|string|max:5000',
            'alternativas'         => 'nullable|string|max:5000',
            'costos_implementacion'=> 'nullable|string|max:5000',
            'beneficios'           => 'nullable|string|max:5000',
            'impacto_estimado'     => 'nullable|string|max:5000',
            'impacta_tramites'     => 'nullable|boolean',
            'sector_scian'         => 'nullable|string|max:100',
            'subsector_scian'      => 'nullable|string|max:100',
            'poblacion_volumen'    => 'nullable|string|max:255',
            'ambito_aplicacion'    => 'nullable|string|max:100',
            'consulta_publica'     => 'nullable|string|max:5000',
            'acciones_derivadas'   => 'nullable|string|max:5000',
            'anexos'               => 'nullable|string|max:2000',
        ]);

        $esEnvio = $request->input('accion') === 'enviar';

        DB::transaction(function () use ($propuesta, $validated, $esEnvio, $user) {
            $datos = array_merge($validated, [
                'created_by' => $propuesta->air ? $propuesta->air->created_by : $user->id,
            ]);

            if ($esEnvio) {
                $datos['estatus'] = 'enviado';
            } else {
                $datos['estatus'] = $propuesta->air?->estatus ?? 'borrador';
            }

            $air = $propuesta->air()->updateOrCreate(
                ['propuesta_id' => $propuesta->id],
                $datos
            );

            // Genera el folio (LPZ-AIR-SIGLAS-AÑO-NNN) la primera vez que
            // se envía, si aún no tiene uno.
            if ($esEnvio && empty($air->folio)) {
                $air->load('propuesta.dependencia');
                $air->folio = $air->generarFolio();
                $air->save();
            }

            // Si se envía, actualizar la propuesta a 'determinada'
            if ($esEnvio) {
                $propuesta->update([
                    'estatus'          => PropuestaRegulatoria::ESTATUS_DETERMINADA,
                    'determinacion_air' => PropuestaRegulatoria::AIR_REQUIERE_AIR,
                ]);
            }
        });

        $mensaje = $esEnvio
            ? 'AIR enviado para dictamen.'
            : 'AIR guardado como borrador.';

        return redirect()->route('propuestas.show', $propuesta)->with('success', $mensaje);
    }

    // ─── Dictamen ────────────────────────────────────────────────

    /**
     * Emite el dictamen del AIR (favorable / no_favorable).
     * Solo la revisora puede dictaminar.
     */
    public function dictaminar(Request $request, PropuestaRegulatoria $propuesta)
    {
        if (!$request->user()->tienePermiso('agenda_regulatoria.aprobar')) {
            abort(403, 'No tiene permiso para dictaminar.');
        }

        $air = $propuesta->air;
        if (!$air) {
            return back()->with('error', 'Esta propuesta no tiene AIR registrado.');
        }

        $validated = $request->validate([
            'dictamen'               => 'required|in:favorable,no_favorable',
            'dictamen_observaciones' => 'nullable|string|max:3000',
        ]);

        DB::transaction(function () use ($air, $propuesta, $validated, $request) {
            $air->update([
                'estatus'                => 'dictaminado',
                'dictamen'               => $validated['dictamen'],
                'dictamen_observaciones' => $validated['dictamen_observaciones'] ?? null,
                'dictamen_fecha'         => now()->toDateString(),
                'dictaminado_por'        => $request->user()->id,
            ]);

            $propuesta->update(['estatus' => PropuestaRegulatoria::ESTATUS_DICTAMINADA]);
        });

        return redirect()->route('propuestas.show', $propuesta)
            ->with('success', 'Dictamen emitido: ' . ucfirst(str_replace('_', ' ', $validated['dictamen'])) . '.');
    }

    // ─── Exención ────────────────────────────────────────────────

    /**
     * Muestra el formulario de solicitud de exención (Art. 36 LNETB).
     */
    public function formularioExencion(PropuestaRegulatoria $propuesta)
    {
        $user = request()->user();
        $this->autorizarAccesoAir($propuesta, $user);

        $exencion = $propuesta->exencion;
        return view('screens.agenda-regulatoria.exencion-formulario', compact('propuesta', 'exencion'));
    }

    /**
     * Guarda la solicitud de exención del AIR.
     */
    public function guardarExencion(Request $request, PropuestaRegulatoria $propuesta)
    {
        $user = $request->user();
        $this->autorizarAccesoAir($propuesta, $user);

        $validated = $request->validate([
            'fracciones'       => 'required|array|min:1',
            'fracciones.*'     => 'integer|between:1,8',
            'justificacion'    => 'required|string|min:30|max:3000',
            'costos_estimados' => 'nullable|numeric|min:0',
        ], [
            'fracciones.required' => 'Seleccione al menos una fracción del Art. 36.',
            'fracciones.min'      => 'Seleccione al menos una fracción.',
            'justificacion.min'   => 'La justificación debe tener al menos 30 caracteres.',
        ]);

        DB::transaction(function () use ($propuesta, $validated, $user) {
            $supuesto = implode(', ', array_map(
                fn ($f) => "Fracc. {$f}",
                $validated['fracciones']
            ));

            $propuesta->exencion()->updateOrCreate(
                ['propuesta_id' => $propuesta->id],
                [
                    'supuesto'         => $supuesto,
                    'fracciones'       => $validated['fracciones'],
                    'justificacion'    => $validated['justificacion'],
                    'costos_estimados' => $validated['costos_estimados'] ?? null,
                    'estatus'          => 'solicitada',
                    'created_by'       => $user->id,
                ]
            );

            $propuesta->update([
                'determinacion_air' => PropuestaRegulatoria::AIR_EXENTO,
            ]);
        });

        return redirect()->route('propuestas.show', $propuesta)
            ->with('success', 'Solicitud de exención registrada. La propuesta queda marcada como exenta pendiente de validación.');
    }

    /**
     * Resuelve la exención (aprobar / rechazar).
     * Solo la revisora puede resolver.
     */
    public function resolverExencion(Request $request, PropuestaRegulatoria $propuesta)
    {
        if (!$request->user()->tienePermiso('agenda_regulatoria.aprobar')) {
            abort(403, 'No tiene permiso para resolver la exención.');
        }

        $exencion = $propuesta->exencion;
        if (!$exencion) {
            return back()->with('error', 'No existe solicitud de exención para esta propuesta.');
        }

        $validated = $request->validate([
            'resolucion' => 'required|in:aprobada,rechazada',
        ]);

        $exencion->update(['estatus' => $validated['resolucion']]);

        // Si se rechaza la exención, la propuesta vuelve a estado 'consulta'
        if ($validated['resolucion'] === 'rechazada') {
            $propuesta->update([
                'determinacion_air' => PropuestaRegulatoria::AIR_REQUIERE_AIR,
                'estatus'           => PropuestaRegulatoria::ESTATUS_CONSULTA,
            ]);
        }

        $msg = $validated['resolucion'] === 'aprobada'
            ? 'Exención aprobada.'
            : 'Exención rechazada. La propuesta debe presentar AIR completo.';

        return redirect()->route('propuestas.show', $propuesta)->with('success', $msg);
    }

    // ─── Helpers privados ────────────────────────────────────────

    private function autorizarAccesoAir(PropuestaRegulatoria $propuesta, $user): void
    {
        if (!$user->isRol(User::ROL_ADMIN) && !$user->esDeSuDependencia($propuesta)) {
            abort(403, 'Solo puede gestionar el AIR de su propia dependencia.');
        }
    }

}
