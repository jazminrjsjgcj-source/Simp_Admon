<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\AccionAgenda;
use App\Models\Dependencia;
use App\Models\Tramite;
use App\Services\CalendarioEventoService;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __construct(
        private CalendarioEventoService $calendario,
        private \App\Services\AgendaService $agendaService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $acciones = AccionAgenda::with(['dependencia', 'tramite'])
            ->when($user->rol === User::ROL_ENLACE, fn ($q) => $q->where('created_by', $user->id))
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
            'tipo'             => 'required|in:simplificacion,digitalizacion',
            'descripcion'      => 'required|string|min:10',
            'dependencia_id'   => 'nullable|exists:dependencias,id',
            'fecha_inicio'     => 'nullable|date',
            'fecha_compromiso' => 'nullable|date|after_or_equal:fecha_inicio',
            'tramite_id'       => 'nullable|exists:tramites,id',
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
            'tipo'             => $request->tipo,
            'descripcion'      => $request->descripcion,
            'meta'             => $request->meta,
            'fecha_inicio'     => $request->fecha_inicio,
            'fecha_compromiso' => $request->fecha_compromiso,
            'responsable'      => $request->responsable,
            'dependencia_id'   => $request->dependencia_id,
            'indicador'        => $request->indicador,
            'tramite_id'       => $request->tramite_id ?: null,
        ];
    }

    /** Lee la lista de derechos enviada por el wizard (JSON). */
    private function leerDerechosWizard(Request $request): array
    {
        $lista = json_decode($request->input('derechos_json', '[]'), true);
        if (!is_array($lista)) {
            return [];
        }
        $derechos = [];
        foreach ($lista as $fila) {
            $concepto = trim($fila['concepto'] ?? '');
            if ($concepto !== '') {
                $derechos[] = ['concepto' => $concepto, 'monto' => floatval($fila['monto'] ?? 0)];
            }
        }
        return $derechos;
    }

    public function show(AccionAgenda $agenda)
    {
        $agenda->load(['dependencia', 'tramite', 'observaciones.realizadaPor', 'creador']);

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

        return view('screens.agenda.show', compact(
            'agenda', 'puedeObservar', 'revisores',
            'observacionesPorSeccion', 'camposObservables'
        ));
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

        $data = $request->only(['descripcion', 'tipo', 'meta', 'fecha_inicio', 'fecha_compromiso', 'responsable', 'indicador']);

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
}
