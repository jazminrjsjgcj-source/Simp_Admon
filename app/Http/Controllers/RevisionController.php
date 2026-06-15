<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Observacion;
use App\Services\RevisionService;
use App\Services\NotificadorService;
use Illuminate\Http\Request;

/**
 * Controlador del módulo de Revisión.
 *
 * Permite a los revisores ver pendientes, observar secciones específicas,
 * marcar observaciones como atendidas y aprobar/regresar registros.
 *
 * El listado incluye trámites, acciones de agenda y propuestas
 * regulatorias en estatus 'en_revision' o 'observado'.
 */
class RevisionController extends Controller
{
    public function __construct(
        private RevisionService $revision,
        private NotificadorService $notificador,
    ) {}

    public function observar(Request $request, string $tipo, int $id)
    {
        $request->validate([
            'seccion'          => 'required|string|max:100',
            'campo'            => 'nullable|string|max:100',
            'texto'            => 'required|string|min:10|max:2000',
            'destinatario_id'  => 'required|exists:users,id',
        ], [
            'destinatario_id.required' => 'Seleccione a quién va dirigida la observación.',
        ]);

        if (!$request->user()->tienePermiso($this->revision->permisoObservar($tipo))) {
            abort(403, 'No tiene permiso para observar este registro.');
        }

        $registro = $this->revision->resolverRegistro($tipo, $id);

        // Jurídico solo puede observar registros de su dependencia
        $user = $request->user();
        if ($user->isRol(User::ROL_JURIDICO) && !$user->esDeSuDependencia($registro)) {
            return back()->with('error', 'Solo puede observar registros de su propia dependencia.');
        }

        $this->revision->registrarObservacion(
            $registro,
            $request->user(),
            $request->seccion,
            $request->texto,
            $request->destinatario_id,
            $request->campo
        );

        // Avisar a los participantes del flujo (creador, enlace/sujeto, jurídico).
        $this->notificador->observado($registro, $request->user());

        // El trámite permanece en_observacion mientras el periodo está abierto.
        // El enlace lo pasa manualmente a en_correccion cuando decide atender
        // las observaciones (botón "Atender observaciones" en el show).

        return back()->with('success', 'Observación registrada.');
    }

    public function marcarAtendida(Observacion $observacion)
    {
        $this->revision->marcarObservacionAtendida($observacion);

        return back()->with('success', 'Observación marcada como atendida.');
    }

    public function aprobar(Request $request, string $tipo, int $id)
    {
        if (!$request->user()->tienePermiso($this->revision->permisoAprobar($tipo))) {
            abort(403, 'No tiene permiso para aprobar este registro.');
        }

        $registro = $this->revision->resolverRegistro($tipo, $id);
        $aprobado = $this->revision->aprobar($registro);

        if (!$aprobado) {
            return back()->with('error', 'No se puede aprobar: hay observaciones pendientes por atender.');
        }

        // Avisar a los participantes: el registro pasó a firma.
        $this->notificador->aprobado($registro, $request->user());

        // Tras aprobar, volver al detalle del registro (donde se ve el nuevo
        // estado). El módulo "Revisión" ya no existe como pantalla aparte.
        $rutaDetalle = match ($tipo) {
            'tramite'              => route('tramites.show', $id),
            'agenda'               => route('agenda.show', $id),
            'propuesta_regulatoria' => route('propuestas.show', $id),
            default                => route('dashboard'),
        };

        return redirect($rutaDetalle)->with('success', 'Registro aprobado.');
    }
}
