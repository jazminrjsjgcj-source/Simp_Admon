<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Tramite;
use App\Services\CalendarioEventoService;
use App\Services\AgendaExportService;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __construct(
        private CalendarioEventoService $calendario,
        private \App\Services\AgendaService $agendaService,
        private \App\Services\HitoAgendaService $hitoService,
        private \App\Services\BitacoraService $bitacora,
        private AgendaExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $acciones = AccionAgenda::with(['dependencia', 'unidad', 'tramite'])
            // Visibilidad por rol: admin y revisora ven todas las dependencias;
            // jurídico, enlace y sujeto solo ven las acciones de su propia
            // dependencia. Coincide con el show() (puedeVerRegistro), evitando
            // listar acciones que el show bloquearía con 403.
            ->when(!$user->veTodoElModulo('agenda'),
                fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
            ->when($request->tipo,          fn ($q, $v) => $q->where('tipo', $v))
            ->when($request->estatus,       fn ($q, $v) => $q->where('estatus', $v))
            ->latest()
            ->paginate(20);

        return view('screens.agenda.index', compact('acciones'));
    }

    public function create()
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        $tramites     = Tramite::orderBy('nombre_oficial')->select('id', 'nombre_oficial', 'homoclave')->get();

        // Catálogos para el camino B (crear trámite nuevo dentro del wizard).
        $tiposTramite = \App\Models\TipoTramite::orderBy('nombre')->get();
        $sectores     = \App\Models\SectorScian::orderBy('nombre')->get();
        // Subsectores agrupados por sector, para el select dependiente en el wizard.
        $subsectoresPorSector = \App\Models\SubsectorScian::orderBy('nombre')
            ->get(['id', 'sector_id', 'nombre'])
            ->groupBy('sector_id');
        $misUnidades  = \App\Models\UnidadAdministrativa::where('dependencia_id', auth()->user()->dependencia_id)
            ->orderBy('nombre')->get();

        return view('screens.agenda.create', compact('dependencias', 'tramites', 'tiposTramite', 'sectores', 'subsectoresPorSector', 'misUnidades'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'alcance'          => 'nullable|in:simplificacion,digitalizacion,ambas',
            'tipo'             => 'nullable|in:simplificacion,digitalizacion,ambas',
            'descripcion'      => 'required|string|min:10',
            'dependencia_id'   => 'nullable|exists:dependencias,id',
            'fecha_inicio'     => 'nullable|date',
            'fecha_compromiso' => 'nullable|date|after_or_equal:fecha_inicio',
            'tramite_id'       => 'nullable|exists:tramites,id',
            'nivel_actual'     => 'nullable|integer|min:0|max:5',
            'nivel_meta'       => 'nullable|integer|min:0|max:5',
        ]);

        $esEnvio = $request->input('accion') === 'enviar';
        $autorId = $request->user()->id;

        // El wizard indica el camino con 'modo_tramite':
        //   'nuevo'  → camino B: crear el trámite desde cero + la acción ligada.
        //   default  → camino A: acción con trámite existente (o sin trámite).
        if ($request->input('modo_tramite') === 'nuevo') {
            $accion = $this->crearAccionConTramiteNuevo($request, $autorId, $esEnvio);
        } else {
            $accion = $this->agendaService->crearAccion(
                datos:   $this->datosAccionDesde($request),
                autorId: $autorId,
                esEnvio: $esEnvio,
            );
        }

        $mensaje = $esEnvio
            ? "Acción enviada a revisión. Folio: {$accion->folio}"
            : 'Acción de agenda guardada como borrador.';

        return redirect()->route('agenda.index')
            ->with('success', $mensaje);
    }

    /**
     * Camino B del wizard: valida los campos del trámite nuevo, los extrae
     * del request y delega en AgendaService::crearConTramiteNuevo, que crea
     * el trámite y la acción ligada en una sola transacción.
     */
    private function crearAccionConTramiteNuevo(Request $request, int $autorId, bool $esEnvio): AccionAgenda
    {
        // Validación del trámite: mínima en borrador, completa al enviar.
        $request->validate([
            'tramite_nombre_oficial' => 'required|string|max:500',
            'tramite_dependencia_id' => 'required|exists:dependencias,id',
        ]);

        // Campos del trámite (el wizard los envía con prefijo tramite_).
        $datosTramite = [
            'nombre_oficial'            => $request->input('tramite_nombre_oficial'),
            'tipo_tramite_id'           => $request->input('tramite_tipo_tramite_id') ?: null,
            'dependencia_id'            => $request->input('tramite_dependencia_id'),
            'unidad_id'                 => $request->input('tramite_unidad_id') ?: null,
            'sector_id'                 => $request->input('tramite_sector_id') ?: null,
            'objetivo'                  => $request->input('tramite_objetivo'),
            'normativa_nombre'          => $request->input('tramite_fundamento'),
            'citas'                     => $request->input('citas', []),
            'dirigido_a'                => $request->input('tramite_dirigido_a') ?: 'ambas',
            'servidor_publico'          => $request->input('tramite_servidor_publico'),
            'volumen_anual'             => $request->input('tramite_volumen_anual'),
            'plazo_resolucion_cantidad' => $request->input('tramite_plazo_resolucion_cantidad'),
            'plazo_resolucion_unidad'   => $request->input('tramite_plazo_resolucion_unidad'),
            'nivel_digitalizacion'      => $request->input('tramite_nivel_digitalizacion'),
            // Identificación adicional
            'tiene_homoclave'           => true,
            'homoclave'                 => $request->input('tramite_homoclave'),
            'subsector_id'              => $request->input('tramite_subsector_id') ?: null,
            'frecuencia'                => $request->input('tramite_frecuencia'),
            // Costos (alimentan el cálculo del costo burocrático)
            'copias_cantidad'           => $request->input('tramite_copias_cantidad') ?: 0,
            'copias_precio'             => $request->input('tramite_copias_precio') ?: 0,
            // Operación y costos
            'visitas_requeridas'        => $request->input('tramite_visitas_requeridas'),
            'num_areas'                 => $request->input('tramite_num_areas'),
            'tiempo_traslado_horas'     => $request->input('tramite_tiempo_traslado_horas'),
            'tiempo_traslado_min'       => $request->input('tramite_tiempo_traslado_min'),
            'tiempo_espera_horas'       => $request->input('tramite_tiempo_espera_horas'),
            'tiempo_espera_min'         => $request->input('tramite_tiempo_espera_min'),
            'tiempo_atencion_horas'     => $request->input('tramite_tiempo_atencion_horas'),
            'tiempo_atencion_min'       => $request->input('tramite_tiempo_atencion_min'),
            // Preguntas de diagnóstico (columnas de la migración 000400)
            'grupo_prioritario'         => $request->boolean('tramite_grupo_prioritario'),
            'grupo_prioritario_detalle' => $request->input('tramite_grupo_prioritario_detalle'),
            'tiene_relacionados'        => $request->boolean('tramite_tiene_relacionados'),
            'tipo_relacion'             => $request->input('tramite_tipo_relacion'),
            'relacionados_detalle'      => $request->input('tramite_relacionados_detalle'),
            'tiene_redundantes'         => $request->boolean('tramite_tiene_redundantes'),
            'redundantes_detalle'       => $request->input('tramite_redundantes_detalle'),
            'requiere_interop'          => $request->boolean('tramite_requiere_interop'),
            'interop_detalle'           => $request->input('tramite_interop_detalle'),
        ];

        // Derechos, requisitos y ficha portal (mismos nombres que el form de trámite).
        $derechos    = $this->leerDerechosWizard($request);
        $requisitos  = $request->input('requisitos', []);
        $fichaPortal = []; // En el wizard la ficha portal se completa luego al editar.

        return $this->agendaService->crearConTramiteNuevo(
            datosTramite: $datosTramite,
            derechos:     $derechos,
            requisitos:   $requisitos,
            fichaPortal:  $fichaPortal,
            datosAccion:  $this->datosAccionDesde($request),
            autorId:      $autorId,
            esEnvio:      $esEnvio,
        );
    }

    /** Arma el arreglo de datos de la acción desde el request. */
    private function datosAccionDesde(Request $request): array
    {
        return [
            // El alcance (simplificacion/digitalizacion/ambas) viaja en la columna
            // tipo. Si el wizard manda 'alcance', tiene prioridad; si no, usa 'tipo'.
            'tipo'             => $request->input('alcance') ?: $request->input('tipo'),
            'descripcion'      => $request->descripcion,
            'meta'             => $request->meta,
            'fecha_inicio'     => $request->fecha_inicio,
            'fecha_compromiso' => $request->fecha_compromiso,
            'responsable'      => $request->responsable,
            'dependencia_id'   => $request->dependencia_id,
            'indicador'        => $request->indicador,
            'indicador_avance' => $request->indicador_avance,
            'tramite_id'       => $request->tramite_id ?: null,
            // Paquete 3: catálogos oficiales con explicación por acción. El form
            // envía las explicaciones en acciones_*[acción] (solo las marcadas, por
            // el disabled). Se filtran vacíos por si llega alguno sin texto.
            'acciones_simplificacion' => array_filter($request->input('acciones_simplificacion', []), fn ($v) => $v !== null && $v !== ''),
            'acciones_digitalizacion' => array_filter($request->input('acciones_digitalizacion', []), fn ($v) => $v !== null && $v !== ''),
            // #10/Paquete 3: el nivel actual se llena solo desde el trámite. Si la
            // precarga dejó el select deshabilitado (no se envía), lo tomamos del
            // trámite vinculado para que siempre quede registrado en la acción.
            'nivel_actual'            => $request->input('nivel_actual') ?? optional(
                $request->tramite_id ? \App\Models\Tramite::find($request->tramite_id) : null
            )->nivel_digitalizacion,
            'nivel_meta'              => $request->input('nivel_meta'),
        ];
    }

    /** Lee la lista de derechos enviada por el wizard (JSON). */
    private function leerDerechosWizard(Request $request): array
    {
        return \App\Models\TramiteDerecho::parsearJson($request->input('derechos_json'));
    }

    public function show(AccionAgenda $agenda)
    {
        // Control de acceso por dependencia (cierra fuga: antes cualquiera con
        // el ID en la URL podía ver una acción de otra dependencia). Pueden ver:
        //   - admin y revisora (transversales, vía puedeVerRegistro)
        //   - quien es de la misma dependencia de la acción
        // El jurídico observa SOLO lo de su dependencia, igual que enlace y sujeto.
        if (!request()->user()->puedeVerRegistro($agenda, 'agenda')) {
            abort(403, 'No tiene permiso para ver esta acción de agenda.');
        }

        $agenda->load(['dependencia', 'tramite.requisitos', 'tramite.procesosAtencion' => function ($q) {
            $q->orderBy('paso')->orderBy('subpaso');
        }, 'observaciones.realizadaPor', 'creador']);

        // #18: datos del modal de observación si el usuario puede observar.
        $puedeObservar = request()->user()->tienePermiso('agenda.observar');
        $revisores = collect();
        if ($puedeObservar) {
            $revisores = \App\Models\User::where('activo', true)
                ->where('dependencia_id', $agenda->dependencia_id)
                ->orderBy('name')
                ->get(['id', 'name', 'cargo']);
        }

        // Observaciones agrupadas por sección, para el checklist lateral (#18).
        $observacionesPorSeccion = $agenda->observaciones->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_agenda');

        // Hitos de avance: lista ordenada, % y cuál es el siguiente marcable.
        $hitos       = $agenda->hitos()->with('completadoPor')->get();
        $porcentaje  = $this->hitoService->calcularPorcentaje($agenda);
        $siguiente   = $this->hitoService->siguientePendiente($agenda);
        $siguienteId = $siguiente?->id;
        $ayudas      = $this->mapaAyudasHitos($hitos);

        // Solo el creador/enlace de la dependencia (o admin) puede marcar hitos.
        $user = request()->user();
        $puedeMarcarHitos = $user->isRol(User::ROL_ADMIN) || $user->esDeSuDependencia($agenda);
        // Grupo 3: la revisora (permiso agenda.aprobar) da el visto bueno.
        $puedeAprobarHitos = $user->tienePermiso('agenda.aprobar');

        return view('screens.agenda.show', compact(
            'agenda', 'puedeObservar', 'revisores',
            'observacionesPorSeccion', 'camposObservables',
            'hitos', 'porcentaje', 'siguienteId', 'ayudas', 'puedeMarcarHitos', 'puedeAprobarHitos'
        ));
    }

    /**
     * #9: el enlace (creador/dependencia o admin) sube la evidencia de un hito.
     * El hito queda "pendiente de visto bueno". Flexible: cualquier hito, sin orden.
     */
    public function subirEvidenciaHito(Request $request, AccionAgenda $agenda, \App\Models\HitoAgenda $hito)
    {
        $user = request()->user();

        if (!$user->isRol(User::ROL_ADMIN) && !$user->esDeSuDependencia($agenda)) {
            return back()->with('error', 'Solo puede actualizar acciones de su dependencia.');
        }
        if ($hito->accion_agenda_id !== $agenda->id) {
            return back()->with('error', 'El hito no corresponde a esta acción.');
        }

        $request->validate([
            'evidencia' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
        ], [
            'evidencia.required' => 'Debe adjuntar un archivo de evidencia.',
            'evidencia.mimes'    => 'El archivo debe ser PDF, imagen, Word o Excel.',
            'evidencia.max'      => 'El archivo no debe superar 10 MB.',
        ]);

        $archivo = $request->file('evidencia');
        $nombreOriginal = $archivo->getClientOriginalName();
        $nombreGuardado = 'hito_' . $hito->id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs('evidencias-hitos', $nombreGuardado, 'local');

        $this->hitoService->subirEvidencia($hito, $ruta, $nombreOriginal, $user->id);
        $this->bitacora->registrar($agenda, 'agenda', 'hito', 'Evidencia subida: ' . $hito->nombre, null, $user->id);

        return back()->with('success', 'Evidencia enviada. Queda pendiente del visto bueno de la revisora.');
    }

    /**
     * #5: la revisora (permiso agenda.aprobar) aprueba un hito con evidencia.
     */
    public function aprobarHito(AccionAgenda $agenda, \App\Models\HitoAgenda $hito)
    {
        $user = request()->user();

        if (!$user->tienePermiso('agenda.aprobar')) {
            return back()->with('error', 'No tiene permiso para dar visto bueno.');
        }
        if ($hito->accion_agenda_id !== $agenda->id) {
            return back()->with('error', 'El hito no corresponde a esta acción.');
        }

        if (!$this->hitoService->aprobarHito($hito, $user->id)) {
            return back()->with('error', 'El hito no está pendiente de visto bueno.');
        }

        $this->bitacora->registrar($agenda, 'agenda', 'hito',
            'Hito aprobado: ' . $hito->nombre,
            'Avance: ' . $this->hitoService->calcularPorcentaje($agenda) . '%', $user->id);

        return back()->with('success', 'Hito aprobado.');
    }

    /**
     * #5: la revisora rechaza un hito con un motivo escrito. Vuelve al enlace.
     */
    public function rechazarHito(Request $request, AccionAgenda $agenda, \App\Models\HitoAgenda $hito)
    {
        $user = request()->user();

        if (!$user->tienePermiso('agenda.aprobar')) {
            return back()->with('error', 'No tiene permiso para dar visto bueno.');
        }
        if ($hito->accion_agenda_id !== $agenda->id) {
            return back()->with('error', 'El hito no corresponde a esta acción.');
        }

        $request->validate([
            'motivo_rechazo' => 'required|string|min:5|max:500',
        ], [
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
            'motivo_rechazo.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        if (!$this->hitoService->rechazarHito($hito, $request->input('motivo_rechazo'), $user->id)) {
            return back()->with('error', 'El hito no está pendiente de visto bueno.');
        }

        $this->bitacora->registrar($agenda, 'agenda', 'hito', 'Hito rechazado: ' . $hito->nombre,
            'Motivo: ' . $request->input('motivo_rechazo'), $user->id);

        return back()->with('success', 'Hito rechazado. El enlace deberá corregir la evidencia.');
    }

    /**
     * #9: descarga la evidencia de un hito (enlace de su dependencia, admin o
     * revisora con permiso de aprobar).
     */
    public function descargarEvidenciaHito(AccionAgenda $agenda, \App\Models\HitoAgenda $hito)
    {
        $user = request()->user();
        $puede = $user->isRol(User::ROL_ADMIN)
            || $user->esDeSuDependencia($agenda)
            || $user->tienePermiso('agenda.aprobar');

        if (!$puede) {
            return back()->with('error', 'No tiene permiso para ver esta evidencia.');
        }
        if ($hito->accion_agenda_id !== $agenda->id || empty($hito->evidencia_archivo)) {
            return back()->with('error', 'No hay evidencia para este hito.');
        }
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($hito->evidencia_archivo)) {
            return back()->with('error', 'El archivo de evidencia no se encuentra.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')
            ->download($hito->evidencia_archivo, $hito->evidencia_nombre);
    }

    /**
     * Arma el mapa [clave => ayuda] de los hitos de una acción, leyendo el
     * texto de ayuda de config/hitos.php. Cubre tanto el Diagnóstico (común)
     * como los hitos específicos del tipo de acción.
     */
    private function mapaAyudasHitos($hitos): array
    {
        $config = config('hitos');
        $mapa   = [$config['diagnostico']['clave'] => $config['diagnostico']['ayuda']];

        // Recorrer todas las listas (simplificacion, digitalizacion, generico)
        // y registrar la ayuda de cada clave que aparezca en los hitos.
        $clavesUsadas = $hitos->pluck('clave')->all();

        foreach (['simplificacion', 'digitalizacion'] as $grupo) {
            foreach ($config[$grupo] ?? [] as $lista) {
                foreach ($lista as $h) {
                    if (in_array($h['clave'], $clavesUsadas)) {
                        $mapa[$h['clave']] = $h['ayuda'];
                    }
                }
            }
        }
        foreach ($config['generico'] ?? [] as $h) {
            if (in_array($h['clave'], $clavesUsadas)) {
                $mapa[$h['clave']] = $h['ayuda'];
            }
        }

        return $mapa;
    }

    public function edit(AccionAgenda $agenda)
    {
        $user = request()->user();
        if (!$user->isRol(User::ROL_ADMIN) && !$user->esDeSuDependencia($agenda)) {
            return redirect()->route('agenda.show', $agenda)
                ->with('error', 'Solo puede editar acciones de su dependencia.');
        }

        $dependencias = Dependencia::activas()->orderBy('nombre')->get();

        // #18: observaciones agrupadas por sección + mapa de campos, para el
        // aviso por sección y el checklist lateral.
        $agenda->load(['observaciones.realizadaPor']);
        $observacionesPorSeccion = $agenda->observaciones
            ->sortByDesc('created_at')
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_agenda');

        return view('screens.agenda.edit', compact('agenda', 'dependencias', 'observacionesPorSeccion', 'camposObservables'));
    }

    public function update(Request $request, AccionAgenda $agenda)
    {
        if (!$request->user()->isRol(User::ROL_ADMIN) && !$request->user()->esDeSuDependencia($agenda)) {
            abort(403, 'No tiene permiso para editar esta acción.');
        }

        $data = $request->only(['descripcion', 'tipo', 'meta', 'fecha_inicio', 'fecha_compromiso', 'responsable', 'indicador', 'indicador_avance']);

        // Paquete 3: el alcance (si el wizard lo manda) tiene prioridad sobre tipo.
        if ($request->filled('alcance')) {
            $data['tipo'] = $request->input('alcance');
        }
        // Paquete 3: catálogos oficiales con explicación por acción (filtra vacíos).
        $data['acciones_simplificacion'] = array_filter($request->input('acciones_simplificacion', []), fn ($v) => $v !== null && $v !== '');
        $data['acciones_digitalizacion'] = array_filter($request->input('acciones_digitalizacion', []), fn ($v) => $v !== null && $v !== '');
        $data['nivel_actual']            = $request->input('nivel_actual');
        $data['nivel_meta']              = $request->input('nivel_meta');

        if ($agenda->estatus === AccionAgenda::ESTATUS_EN_CORRECCION) {
            $data['estatus'] = AccionAgenda::ESTATUS_EN_CORRECCION;
        }

        $agenda->update($data);

        if (!empty($data['fecha_compromiso'])) {
            $this->calendario->actualizar($agenda, [
                'fecha'       => $data['fecha_compromiso'],
                'titulo'      => $data['descripcion'] ?? $agenda->descripcion,
                'responsable' => $data['responsable'] ?? $agenda->responsable,
            ]);
        }

        return redirect()->route('agenda.show', $agenda)
            ->with('success', 'Acción actualizada.');
    }

    public function destroy(AccionAgenda $agenda)
    {
        if (!request()->user()->puedeEliminarAgenda($agenda)) {
            abort(403, 'Solo se pueden eliminar acciones en borrador de su propia dependencia.');
        }

        $this->calendario->eliminar($agenda);
        $agenda->delete();

        return redirect()->route('agenda.index')
            ->with('success', 'Acción eliminada.');
    }

    public function actualizarEstatus(Request $request, AccionAgenda $agenda)
    {
        $request->validate(['estatus' => 'required|in:borrador,en_observacion,en_firma']);
        $agenda->update(['estatus' => $request->estatus]);

        return redirect()->route('agenda.show', $agenda)
            ->with('success', 'Estatus actualizado.');
    }

    /**
     * Exporta a Excel todas las acciones de SIMPLIFICACIÓN (Art. 23 LNETB).
     * Incluye las acciones de tipo 'simplificacion' y 'ambas'.
     * Solo accesible a revisora y admin (la ruta lo controla por middleware).
     */
    public function exportarSimp()
    {
        $acciones = AccionAgenda::with(['dependencia', 'tramite', 'hitos', 'periodo', 'creador'])
            ->whereIn('tipo', ['simplificacion', 'ambas'])
            ->latest()
            ->get();

        return $this->exportService->exportarSimp($acciones);
    }

    /**
     * Exporta a Excel todas las acciones de DIGITALIZACIÓN (Art. 24 LNETB).
     * Incluye las acciones de tipo 'digitalizacion' y 'ambas'.
     */
    public function exportarDig()
    {
        $acciones = AccionAgenda::with(['dependencia', 'tramite', 'hitos', 'periodo', 'creador'])
            ->whereIn('tipo', ['digitalizacion', 'ambas'])
            ->latest()
            ->get();

        return $this->exportService->exportarDig($acciones);
    }
}