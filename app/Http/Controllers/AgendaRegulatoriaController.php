<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\PropuestaRegulatoria;
use App\Models\PropuestaTramiteImpacto;
use App\Models\Dependencia;
use App\Services\CalendarioEventoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaRegulatoriaController extends Controller
{
    public function __construct(private CalendarioEventoService $calendario) {}

    public function index()
    {
        $user = request()->user();

        // Las propuestas son privadas por dependencia. El enlace solo ve
        // las de su área; admin, revisora y jurídico (transversales) ven
        // todas. Se filtra aquí para que la lista coincida con lo que el
        // show() permite abrir.
        // Solo admin y revisora (quien aprueba) ven propuestas de todas las
        // dependencias. El resto —incluido el jurídico, que solo observa— ve
        // únicamente las de su propia dependencia. Coincide con el show()
        // (puedeVerRegistro), evitando listar propuestas que el show bloquearía.
        $puedeVerTodas = $user->isRol(User::ROL_ADMIN)
            || $user->tienePermiso('agenda_regulatoria.aprobar');

        $propuestas = PropuestaRegulatoria::with('dependencia')
            ->when(!$puedeVerTodas, fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
            ->latest()
            ->get();

        $umbral = DB::table('configuracion_sistema')
            ->where('clave', 'umbral_proporcionalidad')
            ->first();

        return view('screens.agenda-regulatoria.index', compact('propuestas', 'umbral'));
    }

    public function create()
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        return view('screens.agenda-regulatoria.create', compact('dependencias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|string|max:500',
            'dependencia_id'  => 'nullable|exists:dependencias,id',
            'fecha_tentativa' => 'nullable|date',
        ]);

        $propuesta = PropuestaRegulatoria::create([
            'nombre'                      => $request->nombre,
            'tipo_regulacion'             => $request->tipo_regulacion,
            'dependencia_id'              => $request->dependencia_id,
            'fecha_tentativa'             => $request->fecha_tentativa ?: null,
            'genera_costos_burocraticos'  => $request->boolean('genera_costos_burocraticos'),
            'impacta_comercio_inversion'  => $request->boolean('impacta_comercio_inversion'),
            'impacta_tramites_existentes' => $request->boolean('impacta_tramites_existentes'),
            'created_by'                  => $request->user()->id,
            'justificacion'               => $this->extraerDetallesComoJson($request),
        ]);

        // Si el enlace eligió "Guardar y enviar", la propuesta entra a
        // revisión (consulta). El cambio de estatus dispara la generación
        // automática del folio en el modelo.
        $enviar = $request->input('accion') === 'enviar';
        if ($enviar) {
            $propuesta->update(['estatus' => PropuestaRegulatoria::ESTATUS_CONSULTA]);
        }

        if ($propuesta->fecha_tentativa) {
            $this->calendario->crear($propuesta, [
                'tipo'           => 'regulatoria',
                'titulo'         => $propuesta->nombre ?: 'Propuesta Regulatoria',
                'fecha'          => $propuesta->fecha_tentativa,
                'responsable'    => $request->responsable_nombre,
                'dependencia_id' => $propuesta->dependencia_id,
            ]);
        }

        $mensaje = $enviar
            ? "Propuesta enviada a revisión. Folio: {$propuesta->fresh()->folio}"
            : 'Propuesta guardada como borrador.';

        return redirect()->route('agenda-regulatoria.index')
            ->with('success', $mensaje);
    }

    public function show(PropuestaRegulatoria $propuesta)
    {
        // Control de acceso por dependencia. Pueden ver la propuesta:
        //   - admin y revisora (transversales, vía puedeVerRegistro)
        //   - quien es de la misma dependencia de la propuesta
        // El jurídico observa SOLO lo de su dependencia, igual que enlace y
        // sujeto: no tiene visión transversal.
        if (!request()->user()->puedeVerRegistro($propuesta, 'agenda_regulatoria')) {
            abort(403, 'No tiene permiso para ver esta propuesta regulatoria.');
        }

        $propuesta->load('dependencia', 'creador', 'sector', 'subsector', 'air.dictaminadoPor', 'exencion.creadaPor', 'observaciones.realizadaPor',
            'impactos.tramite', 'impactos.requisito');
        $detalles = json_decode($propuesta->justificacion ?? '{}', true);

        // #18: datos del modal de observación si el usuario puede observar.
        $puedeObservar = request()->user()->tienePermiso('agenda_regulatoria.observar');
        $revisores = collect();
        if ($puedeObservar) {
            $revisores = \App\Models\User::where('activo', true)
                ->where('dependencia_id', $propuesta->dependencia_id)
                ->orderBy('name')
                ->get(['id', 'name', 'cargo']);
        }

        // Observaciones agrupadas por sección, para el checklist lateral (#18).
        $observacionesPorSeccion = $propuesta->observaciones->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_propuesta');

        return view('screens.agenda-regulatoria.show', compact(
            'propuesta', 'detalles', 'puedeObservar', 'revisores',
            'observacionesPorSeccion', 'camposObservables'
        ));
    }

    public function edit(PropuestaRegulatoria $propuesta)
    {
        $user = request()->user();
        if (!$user->isRol(User::ROL_ADMIN) && !$user->esDeSuDependencia($propuesta)) {
            return redirect()->route('propuestas.show', $propuesta)
                ->with('error', 'Solo puede editar propuestas de su dependencia.');
        }

        $propuesta->load('dependencia', 'creador', 'observaciones.realizadaPor',
            'impactos.tramite', 'impactos.requisito');
        $detalles     = json_decode($propuesta->justificacion ?? '{}', true);
        $dependencias = Dependencia::orderBy('nombre')->get();

        // #18: observaciones agrupadas por sección + mapa de campos.
        $observacionesPorSeccion = $propuesta->observaciones
            ->sortByDesc('created_at')
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_propuesta');

        return view('screens.agenda-regulatoria.edit', compact('propuesta', 'detalles', 'dependencias', 'observacionesPorSeccion', 'camposObservables'));
    }

    public function update(Request $request, PropuestaRegulatoria $propuesta)
    {
        if (!$request->user()->isRol(User::ROL_ADMIN) && !$request->user()->esDeSuDependencia($propuesta)) {
            abort(403, 'No tiene permiso para editar esta propuesta.');
        }

        $request->validate([
            'nombre'         => 'required|string|max:500',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        $propuesta->update([
            'nombre'                      => $request->nombre,
            'tipo_regulacion'             => $request->tipo_regulacion,
            'dependencia_id'              => $request->dependencia_id,
            'fecha_tentativa'             => $request->fecha_tentativa ?: null,
            'genera_costos_burocraticos'  => $request->boolean('genera_costos_burocraticos'),
            'impacta_comercio_inversion'  => $request->boolean('impacta_comercio_inversion'),
            'impacta_tramites_existentes' => $request->boolean('impacta_tramites_existentes'),
            'justificacion'               => $this->extraerDetallesComoJson($request),
        ]);

        $this->calendario->actualizar($propuesta, [
            'fecha'  => $request->fecha_tentativa ?: $propuesta->fecha_tentativa,
            'titulo' => $request->nombre,
        ]);

        return redirect()->route('propuestas.show', $propuesta)
            ->with('success', 'Propuesta actualizada exitosamente.');
    }

    /**
     * #7 Flujo 1: el jurídico agrega una cita de impacto — qué trámite (y
     * opcionalmente qué requisito) modifica esta propuesta, y en qué artículo.
     */
    public function agregarImpacto(Request $request, PropuestaRegulatoria $propuesta)
    {
        if (!request()->user()->isRol(User::ROL_ADMIN) && !request()->user()->esDeSuDependencia($propuesta)) {
            return back()->with('error', 'No tiene permiso para editar esta propuesta.');
        }

        $request->validate([
            'tramite_id'        => 'required|exists:tramites,id',
            'requisito_id'      => 'nullable|exists:requisitos,id',
            'articulo_fraccion' => 'nullable|string|max:200',
            'descripcion'       => 'nullable|string|max:500',
        ]);

        // Evitar duplicados del mismo trámite + requisito en la misma propuesta.
        $existe = $propuesta->impactos()
            ->where('tramite_id',   $request->tramite_id)
            ->where('requisito_id', $request->requisito_id ?: null)
            ->exists();

        if ($existe) {
            return back()->with('error', 'Esa cita ya está registrada en esta propuesta.');
        }

        $propuesta->impactos()->create([
            'tramite_id'        => $request->tramite_id,
            'requisito_id'      => $request->requisito_id ?: null,
            'articulo_fraccion' => $request->articulo_fraccion ?: null,
            'descripcion'       => $request->descripcion ?: null,
        ]);

        return back()->with('success', 'Cita de impacto agregada.');
    }

    /**
     * #7 Flujo 1: quitar una cita de impacto de la propuesta.
     */
    public function quitarImpacto(PropuestaRegulatoria $propuesta, PropuestaTramiteImpacto $impacto)
    {
        if (!request()->user()->isRol(User::ROL_ADMIN) && !request()->user()->esDeSuDependencia($propuesta)) {
            return back()->with('error', 'No tiene permiso para editar esta propuesta.');
        }
        if ($impacto->propuesta_id !== $propuesta->id) {
            return back()->with('error', 'La cita no pertenece a esta propuesta.');
        }

        $impacto->delete();
        return back()->with('success', 'Cita eliminada.');
    }

    public function destroy(PropuestaRegulatoria $propuesta)
    {
        if (!request()->user()->puedeEliminarPropuesta($propuesta)) {
            abort(403, 'Solo se pueden eliminar propuestas en borrador de su propia dependencia.');
        }

        $this->calendario->eliminar($propuesta);
        $propuesta->delete();

        return redirect()->route('agenda-regulatoria.index')
            ->with('success', 'Propuesta eliminada.');
    }

    public function umbral()
    {
        $config = DB::table('configuracion_sistema')
            ->where('clave', 'umbral_proporcionalidad')
            ->first();

        return response()->json($config ? json_decode($config->metadata, true) : ['status' => 'pendiente']);
    }

    public function guardarUmbral(Request $request)
    {
        $request->validate(['valor' => 'required|numeric|min:0']);

        DB::table('configuracion_sistema')->updateOrInsert(
            ['clave' => 'umbral_proporcionalidad'],
            [
                'valor'      => $request->valor,
                'metadata'   => json_encode(['status' => 'publicado']),
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]
        );

        return back()->with('success', 'Umbral guardado.');
    }

    /**
     * Extrae los campos narrativos del request y los serializa como JSON.
     *
     * Solo van aquí los campos de texto libre que no necesitan ser consultados
     * por el sistema. Los campos booleanos consultables (genera_costos_burocraticos,
     * impacta_comercio_inversion, impacta_tramites_existentes) tienen columna propia.
     */
    private function extraerDetallesComoJson(Request $request): string
    {
        $campos = [
            'responsable_nombre', 'responsable_cargo', 'materia', 'sectores_impactados',
            'justificacion', 'problematica', 'alternativas', 'beneficios',
            'costos_burocraticos', 'tramites_impacta', 'acciones_simplificacion',
            'acciones_digitalizacion', 'fundamento_juridico', 'impacto_comercio',
            'observaciones', 'presenta_proyecto',
            'sujeto_obligado_id', 'sujeto_obligado_nombre',
        ];

        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = $request->input($campo);
        }

        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
