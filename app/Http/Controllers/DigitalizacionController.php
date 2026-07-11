<?php

namespace App\Http\Controllers;

use App\Models\DescargaDiagrama;
use App\Models\Diagrama;
use App\Models\Reingenieria;
use App\Models\Tramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Controlador de la Biblioteca de Digitalización.
 *
 * Gestiona:
 *   - Biblioteca (listado filtrado de trámites)
 *   - Detalle con pestañas (resumen, flujo, reingeniería, diagrama, descargas)
 *   - CRUD de reingenierías TO-BE
 *   - Generación de diagramas Mermaid
 *   - Descargas con bitácora
 */
class DigitalizacionController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // Biblioteca
    // ═══════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.ver')) { abort(403, 'No tiene permiso para acceder a la Biblioteca de Digitalización.'); }

        $filtro = $request->input('filtro', 'todos');

        $query = Tramite::with(['dependencia', 'tipoTramite', 'reingenieriaActiva'])
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at');

        match ($filtro) {
            'sin_flujo'           => $query->where('flujo_estado', 'sin_flujo'),
            'flujo_en_captura'    => $query->where('flujo_estado', 'flujo_en_captura'),
            'flujo_aprobado'      => $query->where('flujo_estado', 'flujo_aprobado'),
            'en_agenda'           => $query->where('digitalizacion_origen', 'agenda'),
            'directa'             => $query->where('digitalizacion_origen', 'directa'),
            'pendiente_firmas'    => $query->whereHas('reingenieriaActiva', fn ($q) => $q->where('estado', 'pendiente_firmas')),
            'firmada'             => $query->whereHas('reingenieriaActiva', fn ($q) => $q->where('estado', 'reingenieria_firmada')),
            'en_digitalizacion'   => $query->where('digitalizacion_estado', 'en_digitalizacion'),
            'digitalizado'        => $query->where('digitalizacion_estado', 'digitalizado'),
            'con_cambios'         => $query->where('digitalizacion_estado', 'requiere_revision_por_cambio'),
            default               => null,
        };

        $tramites = $query->paginate(25)->appends($request->only('filtro'));

        $contadores = [
            'todos'             => Tramite::whereNull('deleted_at')->count(),
            'sin_flujo'         => Tramite::whereNull('deleted_at')->where('flujo_estado', 'sin_flujo')->count(),
            'flujo_aprobado'    => Tramite::whereNull('deleted_at')->where('flujo_estado', 'flujo_aprobado')->count(),
            'en_digitalizacion' => Tramite::whereNull('deleted_at')->where('digitalizacion_estado', 'en_digitalizacion')->count(),
            'digitalizado'      => Tramite::whereNull('deleted_at')->where('digitalizacion_estado', 'digitalizado')->count(),
            'con_cambios'       => Tramite::whereNull('deleted_at')->where('digitalizacion_estado', 'requiere_revision_por_cambio')->count(),
        ];

        return view('screens.digitalizacion.index', compact('tramites', 'filtro', 'contadores'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // Detalle
    // ═══════════════════════════════════════════════════════════════════

    public function show(Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.ver')) { abort(403, 'No tiene permiso para acceder a la Biblioteca de Digitalización.'); }

        $tramite->load([
            'dependencia',
            'unidad',
            'tipoTramite',
            'procesosAtencion',
            'reingenieriaActiva.firmas.firmante',
            'reingenieriaActiva.accionAgenda',
            'reingenieriaActiva.diagramas.descargas.usuario',
        ]);

        return view('screens.digitalizacion.show', compact('tramite'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // Reingeniería — CRUD
    // ═══════════════════════════════════════════════════════════════════

    public function crearReingenieria(Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if (!$tramite->tieneFlujoAprobado()) {
            return back()->with('error', 'El trámite necesita un flujo aprobado antes de crear una reingeniería.');
        }

        $reingenieria = new Reingenieria();
        return view('screens.digitalizacion.reingenieria-form', compact('tramite', 'reingenieria'));
    }

    public function guardarReingenieria(Request $request, Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if (!$tramite->tieneFlujoAprobado()) {
            return back()->with('error', 'Flujo no aprobado.');
        }

        $validated = $request->validate([
            'origen'           => 'required|in:agenda,directa',
            'motivo_directa'   => 'required_if:origen,directa|nullable|in:' . implode(',', Reingenieria::MOTIVOS_DIRECTA),
            'justificacion'    => 'required_if:origen,directa|nullable|string|max:2000',
            'area_solicitante' => 'nullable|string|max:200',
            'fecha_limite'     => 'nullable|date',
            'pasos'            => 'nullable|array',
            'pasos.*.accion'   => 'required|string|max:500',
            'pasos.*.detalle'  => 'nullable|string|max:1000',
            'pasos.*.tipo'     => 'required|string|max:30',
        ]);

        if ($validated['origen'] === 'directa') {
            // Reingeniería directa: usa el servicio para crear + notificar
            $agendaService = app(\App\Services\AgendaDigitalizacionService::class);
            $reingenieria = $agendaService->solicitarDirecta(
                $tramite,
                $validated,
                $request->user()
            );

            // Agregar los pasos TO-BE si se capturaron
            if (!empty($validated['pasos'])) {
                $reingenieria->update(['flujo_to_be' => $validated['pasos']]);
            }
        } else {
            // Reingeniería desde agenda: creación directa
            $ultimaVersion = $tramite->reingenierias()->max('version') ?? 0;

            $reingenieria = Reingenieria::create([
                'tramite_id'       => $tramite->id,
                'origen'           => $validated['origen'],
                'version'          => $ultimaVersion + 1,
                'estado'           => Reingenieria::ESTADO_EN_REINGENIERIA,
                'flujo_to_be'      => $validated['pasos'] ?? null,
                'created_by'       => Auth::id(),
            ]);

            $tramite->update(['digitalizacion_origen' => 'agenda']);
        }

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria'])
            ->with('success', 'Reingeniería v' . $reingenieria->version . ' creada.');
    }

    public function editarReingenieria(Tramite $tramite, Reingenieria $reingenieria)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if ($reingenieria->estaFirmada()) {
            return back()->with('error', 'La reingeniería ya está firmada y no se puede editar. Cree una nueva versión.');
        }

        return view('screens.digitalizacion.reingenieria-form', compact('tramite', 'reingenieria'));
    }

    public function actualizarReingenieria(Request $request, Tramite $tramite, Reingenieria $reingenieria)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if ($reingenieria->estaFirmada()) {
            return back()->with('error', 'No se puede editar una reingeniería firmada.');
        }

        $validated = $request->validate([
            'origen'           => 'required|in:agenda,directa',
            'motivo_directa'   => 'required_if:origen,directa|nullable|in:' . implode(',', Reingenieria::MOTIVOS_DIRECTA),
            'justificacion'    => 'required_if:origen,directa|nullable|string|max:2000',
            'area_solicitante' => 'nullable|string|max:200',
            'fecha_limite'     => 'nullable|date',
            'pasos'            => 'nullable|array',
            'pasos.*.accion'   => 'required|string|max:500',
            'pasos.*.detalle'  => 'nullable|string|max:1000',
            'pasos.*.tipo'     => 'required|string|max:30',
        ]);

        $reingenieria->update([
            'origen'           => $validated['origen'],
            'motivo_directa'   => $validated['motivo_directa'] ?? null,
            'justificacion'    => $validated['justificacion'] ?? null,
            'area_solicitante' => $validated['area_solicitante'] ?? null,
            'fecha_limite'     => $validated['fecha_limite'] ?? null,
            'flujo_to_be'      => $validated['pasos'] ?? null,
            'updated_by'       => Auth::id(),
        ]);

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria'])
            ->with('success', 'Reingeniería actualizada.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Reingeniería — Enviar a firma
    // ═══════════════════════════════════════════════════════════════════

    public function enviarAFirma(Tramite $tramite, Reingenieria $reingenieria)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if ($reingenieria->estaFirmada()) {
            return back()->with('error', 'La reingeniería ya está firmada.');
        }

        if (empty($reingenieria->flujo_to_be)) {
            return back()->with('error', 'La reingeniería no tiene pasos TO-BE. Edítela primero.');
        }

        $reingenieria->update([
            'estado'     => Reingenieria::ESTADO_PENDIENTE_FIRMAS,
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria'])
            ->with('success', 'Reingeniería enviada a firma. El Enlace y el Sujeto Obligado deben firmar.');
    }

    /**
     * POST /digitalizacion/{tramite}/reingenieria/nueva-version
     *
     * Crea una nueva versión de reingeniería cuando hubo un cambio
     * post-firma. Copia el flujo TO-BE anterior como punto de partida
     * y limpia la alerta del trámite.
     */
    public function crearNuevaVersion(Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.reingenieria')) { abort(403, 'No tiene permiso para gestionar reingenierías.'); }

        if ($tramite->digitalizacion_estado !== Tramite::DIG_REQUIERE_REVISION) {
            return back()->with('error', 'Este trámite no tiene una alerta de cambio post-firma activa.');
        }

        $nueva = app(\App\Services\CambioPostFirmaService::class)
            ->crearNuevaVersion($tramite);

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'reingenieria'])
            ->with('success', 'Nueva versión v' . $nueva->version . ' creada. Edite el flujo TO-BE y envíe a firma.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Digitalización operativa
    // ═══════════════════════════════════════════════════════════════════

    /**
     * POST /digitalizacion/{tramite}/iniciar
     *
     * Inicia la digitalización del trámite. Valida el checklist completo:
     * flujo aprobado + reingeniería firmada + diagrama generado.
     */
    public function iniciarDigitalizacion(Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.digitalizar')) { abort(403, 'No tiene permiso para digitalizar trámites.'); }

        $tramite->loadMissing(['reingenieriaActiva.diagramas']);

        // Checklist de validación
        $errores = [];

        if (!$tramite->tieneFlujoAprobado()) {
            $errores[] = 'El trámite no tiene un flujo aprobado.';
        }

        if (!$tramite->reingenieriaActiva || !$tramite->reingenieriaActiva->estaFirmada()) {
            $errores[] = 'La reingeniería TO-BE no está firmada por Enlace y Sujeto Obligado.';
        }

        $diagrama = $tramite->reingenieriaActiva?->diagramas?->first();
        if (!$diagrama || !$diagrama->estaListo()) {
            $errores[] = 'No existe un diagrama generado para la reingeniería firmada.';
        }

        if ($tramite->digitalizacion_estado === Tramite::DIG_EN_DIGITALIZACION) {
            $errores[] = 'El trámite ya está en digitalización.';
        }

        if ($tramite->digitalizacion_estado === Tramite::DIG_DIGITALIZADO) {
            $errores[] = 'El trámite ya fue digitalizado.';
        }

        if (!empty($errores)) {
            return back()->with('error', implode(' ', $errores));
        }

        $tramite->update([
            'digitalizacion_estado' => Tramite::DIG_EN_DIGITALIZACION,
        ]);

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'resumen'])
            ->with('success', 'Digitalización iniciada. El trámite está ahora en proceso de digitalización.');
    }

    /**
     * POST /digitalizacion/{tramite}/completar
     *
     * Marca el trámite como digitalizado.
     */
    public function completarDigitalizacion(Request $request, Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.digitalizar')) { abort(403, 'No tiene permiso para digitalizar trámites.'); }

        if ($tramite->digitalizacion_estado !== Tramite::DIG_EN_DIGITALIZACION) {
            return back()->with('error', 'El trámite no está en digitalización.');
        }

        $request->validate([
            'notas_cierre' => 'nullable|string|max:1000',
        ]);

        $tramite->update([
            'digitalizacion_estado' => Tramite::DIG_DIGITALIZADO,
        ]);

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'resumen'])
            ->with('success', 'Trámite marcado como digitalizado.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Dashboard
    // ═══════════════════════════════════════════════════════════════════

    /**
     * GET /digitalizacion/dashboard
     */
    public function dashboard()
    {
        if (!auth()->user()->tienePermiso('digitalizacion.ver')) { abort(403, 'No tiene permiso para acceder a la Biblioteca de Digitalización.'); }

        $base = Tramite::whereNull('deleted_at');

        $metricas = [
            'total'              => (clone $base)->count(),
            'sin_flujo'          => (clone $base)->where('flujo_estado', 'sin_flujo')->count(),
            'flujo_aprobado'     => (clone $base)->where('flujo_estado', 'flujo_aprobado')->count(),
            'con_reingenieria'   => (clone $base)->whereHas('reingenieriaActiva')->count(),
            'firmadas'           => (clone $base)->whereHas('reingenieriaActiva', fn ($q) => $q->where('estado', 'reingenieria_firmada'))->count(),
            'en_digitalizacion'  => (clone $base)->where('digitalizacion_estado', 'en_digitalizacion')->count(),
            'digitalizados'      => (clone $base)->where('digitalizacion_estado', 'digitalizado')->count(),
            'con_cambios'        => (clone $base)->where('digitalizacion_estado', 'requiere_revision_por_cambio')->count(),
            'desde_agenda'       => (clone $base)->where('digitalizacion_origen', 'agenda')->count(),
            'directas'           => (clone $base)->where('digitalizacion_origen', 'directa')->count(),
        ];

        // Últimos 5 trámites con actividad reciente
        $recientes = Tramite::with(['dependencia', 'reingenieriaActiva'])
            ->whereNull('deleted_at')
            ->where('digitalizacion_estado', '!=', 'no_iniciada')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        // Trámites con alerta de cambio
        $conCambios = Tramite::with('dependencia')
            ->whereNull('deleted_at')
            ->where('digitalizacion_estado', 'requiere_revision_por_cambio')
            ->orderByDesc('updated_at')
            ->get();

        return view('screens.digitalizacion.dashboard', compact('metricas', 'recientes', 'conCambios'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // Diagrama — Generación Mermaid
    // ═══════════════════════════════════════════════════════════════════

    public function generarDiagrama(Request $request, Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.diagrama')) { abort(403, 'No tiene permiso para gestionar diagramas.'); }

        $reing = $tramite->reingenieriaActiva;

        if (!$reing || !$reing->estaFirmada()) {
            return back()->with('error', 'Se requiere una reingeniería firmada para generar el diagrama.');
        }

        if (!$reing->flujo_to_be || empty($reing->flujo_to_be)) {
            return back()->with('error', 'La reingeniería no tiene pasos TO-BE definidos.');
        }

        // Generar Mermaid desde el flujo estructurado
        $mermaid = $this->generarMermaidDesdeFlujoBe($reing->flujo_to_be);
        $hash = hash('sha256', $mermaid);

        // Crear o actualizar diagrama
        $diagrama = Diagrama::updateOrCreate(
            ['reingenieria_id' => $reing->id, 'tipo_diagrama' => 'to_be'],
            [
                'tramite_id'       => $tramite->id,
                'contenido_mermaid' => $mermaid,
                'hash_diagrama'    => $hash,
                'estado'           => Diagrama::ESTADO_GENERADO,
                'created_by'       => Auth::id(),
                'updated_by'       => Auth::id(),
            ]
        );

        return redirect()
            ->route('digitalizacion.show', [$tramite, 'tab' => 'diagrama'])
            ->with('success', 'Diagrama Mermaid generado.');
    }

    /**
     * Convierte el array de pasos TO-BE a código Mermaid flowchart.
     */
    private function generarMermaidDesdeFlujoBe(array $pasos): string
    {
        $lines = ['flowchart TD'];
        $lines[] = '    INICIO([Inicio])';

        $prevId = 'INICIO';
        foreach ($pasos as $idx => $paso) {
            $id = 'P' . $idx;
            $accion = str_replace('"', "'", $paso['accion'] ?? 'Paso ' . ($idx + 1));
            $tipo = $paso['tipo'] ?? 'paso';

            // Forma según tipo
            $shape = match ($tipo) {
                'decision'   => $id . '{{"' . $accion . '"}}',
                'pago'       => $id . '[/"' . $accion . '"/]',
                'resolutivo' => $id . '(["' . $accion . '"])',
                default      => $id . '["' . $accion . '"]',
            };

            $lines[] = '    ' . $shape;
            $lines[] = '    ' . $prevId . ' --> ' . $id;
            $prevId = $id;
        }

        $lines[] = '    ' . $prevId . ' --> FIN([Fin])';

        return implode("\n", $lines);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Descarga con bitácora
    // ═══════════════════════════════════════════════════════════════════

    public function descargarDiagrama(Request $request, Diagrama $diagrama)
    {
        if (!auth()->user()->tienePermiso('digitalizacion.diagrama')) { abort(403, 'No tiene permiso para gestionar diagramas.'); }

        $formato = $request->input('formato', 'png');

        if (!in_array($formato, DescargaDiagrama::FORMATOS)) {
            abort(400, 'Formato no soportado.');
        }

        // PDF oficial requiere firmas completas
        if ($formato === 'pdf') {
            $reing = $diagrama->reingenieria;
            if (!$reing || !$reing->firmasCompletas()) {
                return back()->with('error', 'El PDF oficial requiere que la reingeniería esté firmada por Enlace y Sujeto Obligado.');
            }

            // Generar PDF con plantilla institucional
            try {
                $pdfService = app(\App\Services\PdfDiagramaService::class);
                $path = $pdfService->generar($diagrama);
            } catch (Throwable $e) {
                return back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
            }

            // Registrar en bitácora
            $hashPdf = hash_file('sha256', $path);
            DescargaDiagrama::create([
                'diagrama_id'           => $diagrama->id,
                'reingenieria_id'       => $diagrama->reingenieria_id,
                'usuario_id'            => Auth::id(),
                'formato'               => 'pdf',
                'hash_archivo_generado' => $hashPdf,
                'ip'                    => $request->ip(),
                'user_agent'            => $request->userAgent(),
            ]);

            return response()->download($path)->deleteFileAfterSend(true);
        }

        // Registrar en bitácora (formatos no-PDF)
        DescargaDiagrama::create([
            'diagrama_id'           => $diagrama->id,
            'reingenieria_id'       => $diagrama->reingenieria_id,
            'usuario_id'            => Auth::id(),
            'formato'               => $formato,
            'hash_archivo_generado' => $diagrama->hash_diagrama,
            'ip'                    => $request->ip(),
            'user_agent'            => $request->userAgent(),
        ]);

        $contenido = $diagrama->contenido_mermaid ?? '';
        $nombre = 'diagrama-' . $diagrama->tramite_id . '-v' . ($diagrama->reingenieria->version ?? 1);

        return match ($formato) {
            'drawio' => response($diagrama->contenido_drawio_xml ?? $contenido)
                          ->header('Content-Type', 'application/xml')
                          ->header('Content-Disposition', "attachment; filename=\"{$nombre}.drawio\""),
            default  => response($contenido)->header('Content-Type', 'text/plain')
                          ->header('Content-Disposition', "attachment; filename=\"{$nombre}.mmd\""),
        };
    }
}
