<?php

namespace App\Http\Controllers;

use App\Models\Dependencia;

use App\Http\Requests\PropuestaRegulatoriaRequest;
use App\Models\PropuestaRegulatoria;
use App\Models\PropuestaTramiteImpacto;
use App\Models\User;
use App\Services\CalendarioEventoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaRegulatoriaController extends Controller
{
    public function __construct(private CalendarioEventoService $calendario) {}

    public function index(Request $request)
    {
        $user = request()->user();

        // Solo admin y revisora (quien aprueba) ven propuestas de todas las
        // dependencias. El resto —incluido el jurídico, que solo observa— ve
        // únicamente las de su propia dependencia.
        $puedeVerTodas = $user->isRol(User::ROL_ADMIN)
            || $user->tienePermiso('agenda_regulatoria.aprobar');

        $propuestas = PropuestaRegulatoria::with('dependencia')
            ->when(!$puedeVerTodas, fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
            // Visibilidad de BORRADORES (#32): un borrador es trabajo en proceso
            // del enlace que lo creó. Solo él (y el admin) lo ve en el listado;
            // ni la revisora ni otros enlaces, hasta que se envíe a revisión.
            ->when(!$user->isRol(User::ROL_ADMIN), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('estatus', '!=', PropuestaRegulatoria::ESTATUS_BORRADOR)
                        ->orWhere('created_by', $user->id);
                });
            })
            // Filtros de la URL (misma convención que trámites y regulaciones).
            ->when($request->q, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('nombre', 'ILIKE', "%{$v}%")
                    ->orWhere('folio', 'ILIKE', "%{$v}%");
            }))
            ->when($request->estatus, fn ($q, $v) => $q->where('estatus', $v))
            ->when($request->determinacion, fn ($q, $v) => $q->where('determinacion_air', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $umbral = DB::table('configuracion_sistema')
            ->where('clave', 'umbral_proporcionalidad')
            ->first();

        $estatuses = [
            PropuestaRegulatoria::ESTATUS_BORRADOR,
            PropuestaRegulatoria::ESTATUS_CONSULTA,
            PropuestaRegulatoria::ESTATUS_DETERMINADA,
            PropuestaRegulatoria::ESTATUS_DICTAMINADA,
            PropuestaRegulatoria::ESTATUS_PUBLICADA,
        ];

        return view('screens.agenda-regulatoria.index', compact('propuestas', 'umbral', 'estatuses'));
    }

    public function create()
    {
        if (!auth()->user()->tienePermiso('agenda_regulatoria.crear')) {
            abort(403, 'No tiene permiso para crear propuestas regulatorias.');
        }

        $dependencias = Dependencia::orderBy('nombre')->get();
        return view('screens.agenda-regulatoria.create', array_merge(
            compact('dependencias'),
            $this->catalogosWizard()
        ));
    }

    /**
     * B18 — Catálogos que alimentan los selects y multi-selects del wizard de
     * propuesta regulatoria: sectores SCIAN (rubro 5) y las acciones de Agenda
     * SyD disponibles para vincular (rubros 13/14). Los tipos de regulación y
     * materias se ofrecen como opciones fijas + "otro" directamente en la vista,
     * porque el anexo no exige un catálogo cerrado.
     */
    private function catalogosWizard(): array
    {
        $sectores = \App\Models\SectorScian::orderBy('nombre')->get();

        // Acciones SyD existentes para vincular como simplificación/digitalización.
        // Se ofrecen las acciones de agenda con folio (ya registradas).
        $accionesSyd = \App\Models\AccionAgenda::orderByDesc('created_at')
            ->get(['id', 'folio', 'descripcion', 'tipo']);

        return compact('sectores', 'accionesSyd');
    }

    public function store(PropuestaRegulatoriaRequest $request)
    {
        if (!$request->user()->tienePermiso('agenda_regulatoria.crear')) {
            abort(403, 'No tiene permiso para crear propuestas regulatorias.');
        }

        // La validación vive en PropuestaRegulatoriaRequest: pide lo mínimo para un
        // borrador y exige todo el sustento al enviar a revisión (antes solo se pedía
        // el nombre y se podía enviar una propuesta prácticamente vacía).

        // Cuando el usuario elige "Otro" en el select de tipo,
        // el texto personalizado viaja en tipo_regulacion_otro. Si se guarda
        // el literal "Otro", el dato específico se pierde y al regresar a
        // editar el campo aparece vacío ("Otro" no coincide con ninguna opción
        // predefinida, así que el select cae al default).
        $tipoRegulacion = $request->tipo_regulacion;
        if ($tipoRegulacion === 'Otro' && $request->filled('tipo_regulacion_otro')) {
            $tipoRegulacion = $request->tipo_regulacion_otro;
        }

        $propuesta = PropuestaRegulatoria::create([
            'nombre'                      => $request->nombre,
            'tipo_regulacion'             => $tipoRegulacion,
            'dependencia_id'              => $request->dependencia_id,
            'fecha_tentativa'             => $request->fecha_tentativa ?: null,
            'genera_costos_burocraticos'  => $request->boolean('genera_costos_burocraticos'),
            'impacta_comercio_inversion'  => $request->boolean('impacta_comercio_inversion'),
            'impacta_tramites_existentes' => $request->boolean('impacta_tramites_existentes'),
            'created_by'                  => $request->user()->id,
            'justificacion'               => $this->extraerDetallesComoJson($request),
        ]);

        // B18 — Rubros 12, 13 y 14: guardar los trámites impactados (con su
        // acción crea/modifica/elimina) y las acciones SyD vinculadas que
        // vienen del wizard.
        $this->sincronizarRelaciones($request, $propuesta);

        // Si el enlace eligió "Guardar y enviar", la propuesta entra a
        // revisión (consulta) y se le asigna folio. Se genera aquí de forma
        // explícita (además del evento del modelo) para garantizar que la
        // propuesta enviada siempre tenga folio.
        $enviar = $request->input('accion') === 'enviar';
        if ($enviar) {
            $cambios = ['estatus' => PropuestaRegulatoria::ESTATUS_CONSULTA];
            if (empty($propuesta->folio)) {
                $cambios['folio'] = $propuesta->generarFolio();
            }
            $propuesta->update($cambios);
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
            // Excluir al propio usuario — no puede dirigirse
            // observaciones a sí mismo.
            $revisores = User::where('activo', true)
                ->where('dependencia_id', $propuesta->dependencia_id)
                ->where('id', '!=', request()->user()->id)
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
        // Editar exige el permiso agenda_regulatoria.editar Y ser de la dependencia (o
        // admin). Solo el enlace edita; jurídico, revisora, sujeto y digitalizador
        // revisan/aprueban/observan pero no editan. Antes bastaba con la dependencia, así
        // que esos roles podían editar sin permiso.
        if (!$user->isRol(User::ROL_ADMIN)
            && (!$user->tienePermiso('agenda_regulatoria.editar') || !$user->esDeSuDependencia($propuesta))) {
            return redirect()->route('propuestas.show', $propuesta)
                ->with('error', 'Solo puede editar propuestas de su dependencia.');
        }

        $propuesta->load('dependencia', 'creador', 'observaciones.realizadaPor',
            'impactos.tramite', 'impactos.requisito', 'accionesSyd');
        $detalles     = json_decode($propuesta->justificacion ?? '{}', true);
        $dependencias = Dependencia::orderBy('nombre')->get();

        // #18: observaciones agrupadas por sección + mapa de campos.
        $observacionesPorSeccion = $propuesta->observaciones
            ->sortByDesc('created_at')
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_propuesta');

        return view('screens.agenda-regulatoria.edit', array_merge(
            compact('propuesta', 'detalles', 'dependencias', 'observacionesPorSeccion', 'camposObservables'),
            $this->catalogosWizard()
        ));
    }

    public function update(Request $request, PropuestaRegulatoria $propuesta)
    {
        $user = $request->user();
        // Mismo criterio que edit(): permiso agenda_regulatoria.editar Y dependencia (o admin).
        if (!$user->isRol(User::ROL_ADMIN)
            && (!$user->tienePermiso('agenda_regulatoria.editar') || !$user->esDeSuDependencia($propuesta))) {
            abort(403, 'No tiene permiso para editar esta propuesta.');
        }

        $request->validate([
            'nombre'         => 'required|string|max:500',
            'dependencia_id' => 'nullable|exists:dependencias,id',
        ]);

        // Misma lógica que en store() — si el tipo es "Otro",
        // guardar el texto personalizado en lugar del literal.
        $tipoRegulacion = $request->tipo_regulacion;
        if ($tipoRegulacion === 'Otro' && $request->filled('tipo_regulacion_otro')) {
            $tipoRegulacion = $request->tipo_regulacion_otro;
        }

        $propuesta->update([
            'nombre'                      => $request->nombre,
            'tipo_regulacion'             => $tipoRegulacion,
            'dependencia_id'              => $request->dependencia_id,
            'fecha_tentativa'             => $request->fecha_tentativa ?: null,
            'genera_costos_burocraticos'  => $request->boolean('genera_costos_burocraticos'),
            'impacta_comercio_inversion'  => $request->boolean('impacta_comercio_inversion'),
            'impacta_tramites_existentes' => $request->boolean('impacta_tramites_existentes'),
            'justificacion'               => $this->extraerDetallesComoJson($request),
        ]);

        // B18 — Rubros 12, 13 y 14: re-sincronizar trámites impactados y
        // acciones SyD vinculadas con lo que venga del wizard de edición.
        $this->sincronizarRelaciones($request, $propuesta);

        // Si desde la edición se eligió "Guardar y enviar" y la propuesta aún
        // era borrador, entra a revisión (consulta) y se le asigna folio.
        if ($request->input('accion') === 'enviar'
            && $propuesta->estatus === PropuestaRegulatoria::ESTATUS_BORRADOR) {
            $cambios = ['estatus' => PropuestaRegulatoria::ESTATUS_CONSULTA];
            if (empty($propuesta->folio)) {
                $cambios['folio'] = $propuesta->generarFolio();
            }
            $propuesta->update($cambios);
        }

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
            ->with('success', 'Propuesta movida a papelera.');
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
    /**
     * B18 — Sincroniza las relaciones del wizard (rubros 12, 13, 14):
     *
     *   Rubro 12: trámites impactados. Llega como tramites_impacto[], cada uno
     *   con tramite_id y accion (crea/modifica/elimina). En update se borran
     *   los anteriores y se vuelven a crear con lo que mande el formulario.
     *
     *   Rubros 13/14: acciones de simplificación/digitalización. Llegan como
     *   objetos acciones_simplificacion[tipo] = explicación (igual que la Agenda
     *   SyD). Se guardan como JSON en sus columnas vía extraerDetallesComoJson;
     *   no usan la tabla pivote.
     */
    private function sincronizarRelaciones(Request $request, PropuestaRegulatoria $propuesta): void
    {
        // --- Rubro 12: trámites impactados ---
        // En edición, reemplazamos: borramos los que tenía y recreamos.
        $propuesta->impactos()->delete();

        $tramitesImpacto = $request->input('tramites_impacto', []);
        if (is_array($tramitesImpacto)) {
            foreach ($tramitesImpacto as $fila) {
                // Cada fila debe traer al menos el trámite. La acción es opcional
                // pero esperada (crea/modifica/elimina).
                $tramiteId = $fila['tramite_id'] ?? null;
                if (!$tramiteId) {
                    continue;
                }
                $accion = $fila['accion'] ?? null;
                if (!in_array($accion, ['crea', 'modifica', 'elimina'], true)) {
                    $accion = null;
                }
                $propuesta->impactos()->create([
                    'tramite_id'        => $tramiteId,
                    'accion'            => $accion,
                    'requisito_id'      => $fila['requisito_id'] ?? null,
                    'articulo_fraccion' => $fila['articulo_fraccion'] ?? null,
                    'descripcion'       => $fila['descripcion'] ?? null,
                ]);
            }
        }

        // --- Rubros 13/14: acciones de simplificación/digitalización ---
        // Se guardan como objeto JSON { "tipo de acción": "explicación" } en las
        // columnas acciones_simplificacion / acciones_digitalizacion, igual que en
        // la Agenda SyD. Eso lo hace extraerDetallesComoJson() al construir el
        // payload, así que aquí no se sincroniza ninguna relación.
        // (La tabla pivote propuesta_accion_syd queda disponible para un uso
        // futuro de vinculación a acciones reales, pero el wizard no la usa.)
    }

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

        // El paso 5 incluye <x-citar-regulacion> que envía las citas
        // como citas[0][regulacion_id] y citas[0][articulo_fraccion]. Antes
        // de este fix, esos datos se descartaban silenciosamente porque el
        // controlador no los leía. Se guardan en el mismo JSON de justificacion
        // como 'citas_fundamento' — un array de {regulacion_id, articulo_fraccion}.
        // Solo se incluyen las citas que tienen regulacion_id (las vacías se filtran).
        $citasRaw = $request->input('citas', []);
        $citasLimpias = [];
        if (is_array($citasRaw)) {
            foreach ($citasRaw as $cita) {
                $regId = $cita['regulacion_id'] ?? null;
                if ($regId) {
                    $citasLimpias[] = [
                        'regulacion_id'     => (int) $regId,
                        'articulo_fraccion' => trim($cita['articulo_fraccion'] ?? ''),
                    ];
                }
            }
        }
        $datos['citas_fundamento'] = $citasLimpias;

        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
