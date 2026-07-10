<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\Tramite;
use App\Models\Dependencia;
use App\Models\FichaPortal;
use App\Models\TipoTramite;
use App\Models\SujetoObligado;
use Illuminate\Http\Request;
use App\Http\Requests\TramiteRequest;
use App\Http\Controllers\Concerns\ExtraeFichaPortal;

class TramiteController extends Controller
{
    use ExtraeFichaPortal;

    public function __construct(
        private \App\Services\CostoBurocraticoService $costoService,
        private \App\Services\NotificadorService $notificador,
        private \App\Services\TramiteService $tramiteService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Alias de URL vieja: ?costo= equivale a ?costo_unitario=.
        if ($request->filled('costo') && !$request->filled('costo_unitario')) {
            $request->merge(['costo_unitario' => $request->input('costo')]);
        }

        $query = Tramite::with('dependencia');
        $this->aplicaVisibilidad($query, $user);
        $this->aplicaFiltros($query, $request);
        $this->aplicaOrden($query, $request->input('orden'));
        $this->contarObservacionesPorAtender($query);

        $tramites     = $query->paginate(20)->withQueryString();
        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        $estatuses    = Tramite::ESTATUS_TODOS;

        return view('screens.tramites.index', compact('tramites', 'dependencias', 'estatuses'));
    }

    /**
     * Visibilidad del listado según el rol: admin y revisora ven todas las
     * dependencias; los demás, solo la suya; y un borrador solo lo ve su
     * creador o el admin.
     */
    private function aplicaVisibilidad(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        $query
            ->when(!$user->veTodoElModulo('tramites'),
                fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
            ->when(!$user->isRol(User::ROL_ADMIN), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('estatus', '!=', Tramite::ESTATUS_BORRADOR)
                        ->orWhere('created_by', $user->id);
                });
            });
    }

    /** Filtros que el usuario elige en la barra del listado. */
    private function aplicaFiltros(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $query
            ->when($request->estatus,     fn ($q, $v) => $q->where('estatus', $v))
            ->when($request->dependencia, fn ($q, $v) => $q->where('dependencia_id', $v))
            ->when($request->naturaleza,  fn ($q, $v) => $q->where('naturaleza', $v))
            ->when($request->q, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('nombre_oficial', 'like', '%' . $v . '%')
                    ->orWhere('homoclave', 'like', '%' . $v . '%');
            }))
            ->when($request->costo_unitario === 'bajo',  fn ($q) => $q->where('cbu_unitario', '<',  Tramite::CBU_UMBRAL_BAJO))
            ->when($request->costo_unitario === 'medio', fn ($q) => $q->whereBetween('cbu_unitario', [Tramite::CBU_UMBRAL_BAJO, Tramite::CBU_UMBRAL_ALTO]))
            ->when($request->costo_unitario === 'alto',  fn ($q) => $q->where('cbu_unitario', '>',  Tramite::CBU_UMBRAL_ALTO))
            ->when($request->impacto, fn ($q, $v) => $q->where('impacto', $v));
    }

    /**
     * Cuenta las observaciones por atender (pendientes o reabiertas) de cada
     * trámite en la misma consulta, para evitar una consulta por fila (N+1).
     * Va después de aplicaOrden para no chocar con su select('tramites.*').
     */
    private function contarObservacionesPorAtender(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->withCount(['observaciones as observaciones_por_atender_count' => function ($q) {
            $q->whereIn('estatus', [
                \App\Models\Observacion::ESTATUS_PENDIENTE,
                \App\Models\Observacion::ESTATUS_REABIERTA,
            ]);
        }]);
    }

    /**
     * Aplica el orden elegido por el usuario al listado de trámites.
     *
     * Si no se elige nada (o llega un valor desconocido) se conserva el
     * comportamiento por defecto: más recientes primero. La opción "tipo"
     * ordena por el NOMBRE del tipo de trámite, que vive en la tabla
     * tipos_tramite; por eso se une con leftJoin y se reseleccionan solo las
     * columnas de tramites, para no contaminar los datos que se hidratan.
     *
     * @param  string|null  $orden  reciente | antiguo | az | tipo | dependencia
     */
    private function aplicaOrden(\Illuminate\Database\Eloquent\Builder $query, ?string $orden): void
    {
        match ($orden) {
            'antiguo'      => $query->oldest(),
            'az'           => $query->orderBy('nombre_oficial'),
            'tipo'         => $query
                ->leftJoin('tipos_tramite', 'tramites.tipo_tramite_id', '=', 'tipos_tramite.id')
                ->orderBy('tipos_tramite.nombre')
                ->select('tramites.*'),
            'dependencia'  => $query
                ->leftJoin('dependencias', 'tramites.dependencia_id', '=', 'dependencias.id')
                ->orderBy('dependencias.nombre')
                ->select('tramites.*'),
            default        => $query->latest(),
        };
    }

    /**
     * Búsqueda de trámites en formato JSON, para el wizard de agenda.
     * Devuelve hasta 10 coincidencias por nombre oficial o homoclave.
     * Lo consume el camino A del wizard (buscar y precargar un trámite existente).
     */
    public function buscarJson(Request $request)
    {
        $termino = trim($request->query('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json(['resultados' => []]);
        }

        $tramites = Tramite::query()
            ->where(function ($q) use ($termino) {
                $q->where('nombre_oficial', 'like', '%' . $termino . '%')
                  ->orWhere('homoclave', 'like', '%' . $termino . '%');
            })
            ->with('dependencia:id,nombre')
            ->orderBy('nombre_oficial')
            ->take(10)
            ->get(['id', 'nombre_oficial', 'homoclave', 'dependencia_id', 'estatus']);

        $resultados = $tramites->map(fn ($t) => [
            'id'          => $t->id,
            'nombre'      => $t->nombre_oficial,
            'homoclave'   => $t->homoclave,
            'dependencia' => $t->dependencia->nombre ?? '—',
            'estatus'     => $t->estatus,
        ]);

        return response()->json(['resultados' => $resultados]);
    }

    /**
     * Devuelve los datos completos de un trámite por id, para precargarlos
     * en solo-lectura en el wizard de agenda (camino A: trámite existente).
     */
    public function detalleJson(Tramite $tramite)
    {
        $tramite->load('dependencia:id,nombre', 'unidad:id,nombre', 'sector:id,nombre', 'requisitos:id,tramite_id,nombre,tipo_presentacion,orden');

        return response()->json([
            'id'                        => $tramite->id,
            'nombre_oficial'            => $tramite->nombre_oficial,
            'homoclave'                 => $tramite->homoclave,
            'dependencia'               => $tramite->dependencia->nombre ?? '',
            'unidad'                    => $tramite->unidad->nombre ?? '',
            'sector'                    => $tramite->sector->nombre ?? '',
            'servidor_publico'          => $tramite->servidor_publico,
            'objetivo'                  => $tramite->objetivo,
            'dirigido_a'                => $tramite->dirigido_a,
            'volumen_anual'             => $tramite->volumen_anual,
            'plazo_resolucion_cantidad' => $tramite->plazo_resolucion_cantidad,
            'plazo_resolucion_unidad'   => $tramite->plazo_resolucion_unidad,
            'nivel_digitalizacion'      => $tramite->nivel_digitalizacion,
            'visitas_requeridas'        => $tramite->visitas_requeridas,
            'normativa_nombre'          => $tramite->normativa_nombre,
            'num_areas'                 => $tramite->num_areas,
            'areas_participantes'       => $tramite->areas_participantes,
            'tiempo_traslado_horas'     => $tramite->tiempo_traslado_horas,
            'tiempo_traslado_min'       => $tramite->tiempo_traslado_min,
            'tiempo_espera_horas'       => $tramite->tiempo_espera_horas,
            'tiempo_espera_min'         => $tramite->tiempo_espera_min,
            'tiempo_atencion_horas'     => $tramite->tiempo_atencion_horas,
            'tiempo_atencion_min'       => $tramite->tiempo_atencion_min,
            'copias_cantidad'           => $tramite->copias_cantidad,
            'copias_precio'             => $tramite->copias_precio,
            'monto_derechos'            => $tramite->monto_derechos,
            // Grupos de atención prioritaria del trámite (Art. 19 fracc. III LNETB).
            // La agenda los lee para precargarlos automáticamente al vincular el
            // trámite (Art. 27 fracc. II y Art. 29 fracc. V).
            'grupos_atencion'           => $tramite->grupos_atencion ?? [],
            'requisitos'                => $tramite->requisitos->map(fn ($r) => [
                'id'                => $r->id,
                'nombre'            => $r->nombre,
                'tipo_presentacion' => $r->tipo_presentacion,
            ])->values(),
            'costo' => [
                'cbd_directo'   => $tramite->cbd_directo,
                'cbi_indirecto' => $tramite->cbi_indirecto,
                'cbu_unitario'  => $tramite->cbu_unitario,
                'cbt_total'     => $tramite->cbt_total,
                'categoria'     => $tramite->categoriaPorCostoUnitario(),
                'calculado'     => $tramite->cbu_unitario !== null && (float) $tramite->cbu_unitario > 0,
            ],
        ]);
    }

    public function create()
    {
        if (!auth()->user()->tienePermiso('tramites.crear')) {
            abort(403, 'No tiene permiso para crear trámites.');
        }

        // Solo dependencias activas (en la práctica, la unificada del Ayuntamiento)
        $dependencias = Dependencia::activas()->with('unidades')->orderBy('nombre')->get();
        $tramites     = Tramite::orderBy('nombre_oficial')->select('id', 'nombre_oficial', 'homoclave')->get();

        // Unidades de la dependencia del usuario, para el select del paso 1.
        // Si solo hay una, se auto-selecciona (unidadAutoId).
        $misUnidades = \App\Models\UnidadAdministrativa::where('dependencia_id', auth()->user()->dependencia_id)
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('unidades_administrativas', 'activo'),
                fn ($q) => $q->where('activo', true)
            )
            ->orderBy('nombre')
            ->get();

        $unidadAutoId = $misUnidades->count() === 1 ? $misUnidades->first()->id : null;

        $tiposServicio = config('punta.tipos_servicio', []);

        return view('screens.tramites.create', compact('dependencias', 'tramites', 'misUnidades', 'unidadAutoId', 'tiposServicio'));
    }

    public function store(TramiteRequest $request)
    {
        if (!$request->user()->tienePermiso('tramites.crear')) {
            abort(403, 'No tiene permiso para crear trámites.');
        }

        $esEnvio   = $request->input('accion') === 'enviar';

        // Si el trámite se crea desde el flujo de Agenda SyD, siempre va
        // directo a revisión (en_observacion). La agenda es la que se queda
        // en borrador hasta que el enlace la complete por separado.
        if ($request->input('retorno') === 'agenda') {
            $esEnvio = true;
        }
        $validated = $request->validated();

        // Campos comunes con update(), más los exclusivos del alta.
        $validated = array_merge($validated, $this->camposComunesDesde($request));
        $validated['created_by'] = $request->user()->id;
        $validated['dirigido_a'] = $validated['dirigido_a'] ?? 'ambas';

        // El controlador extrae del request; el servicio recibe datos limpios.
        $tramite = $this->tramiteService->crear(
            datos:       $validated,
            derechos:    $this->leerDerechos($request),
            requisitos:  $request->input('requisitos', []),
            fichaPortal: $this->extraerFichaPortal($request),
            procesos:    $this->extraerProcesos($request),
            esEnvio:     $esEnvio,
        );

        $this->sincronizarRelacionados($tramite, $request);

        $mensaje = $esEnvio
            ? 'Trámite guardado y enviado a revisión correctamente.'
            : 'Trámite guardado como borrador. Podrá editarlo y enviarlo a revisión posteriormente.';

        // Si el usuario llegó desde el wizard de Agenda SyD (retorno=agenda),
        // volver a la agenda con el trámite recién creado pre-seleccionado.
        if ($request->input('retorno') === 'agenda') {
            return redirect()
                ->route('agenda.create', ['tramite_id' => $tramite->id])
                ->with('success', $mensaje . ' Continúe con la agenda.');
        }

        return redirect()->route('tramites.index')->with('success', $mensaje);
    }

    public function show(Tramite $tramite)
    {
        // Control de acceso por dependencia (cierra fuga: antes cualquiera con
        // el ID en la URL podía ver un trámite de otra dependencia). Pueden ver:
        //   - admin y revisora (transversales, vía puedeVerRegistro)
        //   - quien es de la misma dependencia del trámite
        // El jurídico observa SOLO lo de su dependencia, igual que enlace y sujeto.
        if (!request()->user()->puedeVerRegistro($tramite, 'tramites')) {
            abort(403, 'No tiene permiso para ver este trámite.');
        }

        $tramite->load(['dependencia', 'unidad', 'requisitos', 'fundamentos', 'fichaPortal', 'observaciones.realizadaPor', 'firmas.firmante', 'derechos', 'relacionados.dependencia', 'acciones']);
        $snapshotCosto = $this->costoService->ultimoSnapshot($tramite);

        // Si el usuario puede observar, preparamos los datos
        // del modal de observación por campo. Los destinatarios son los
        // usuarios activos de la dependencia del trámite (los enlaces que
        // corregirán), igual que en el módulo de revisión.
        $puedeObservar = request()->user()->tienePermiso('tramites.observar')
            && $tramite->estaEnObservacion();
        $revisores = collect();
        if ($puedeObservar) {
            // Excluir al propio usuario — no puede dirigirse
            // observaciones a sí mismo (ej. jurídico observándose).
            $revisores = \App\Models\User::where('activo', true)
                ->where('dependencia_id', $tramite->dependencia_id)
                ->where('id', '!=', auth()->id())
                ->orderBy('name')
                ->get(['id', 'name', 'cargo', 'rol']);
        }

        // Observaciones agrupadas por sección, para el checklist lateral y los
        // avisos por sección. Mismo formato que en edit().
        $observacionesPorSeccion = $tramite->observaciones
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_tramite');

        return view('screens.tramites.show', compact(
            'tramite', 'snapshotCosto', 'puedeObservar', 'revisores',
            'observacionesPorSeccion', 'camposObservables'
        ));
    }

    public function edit(Tramite $tramite)
    {
        if (!request()->user()->puedeEditarTramite($tramite)) {
            return redirect()->route('tramites.show', $tramite)
                ->with('error', 'No tiene permiso para editar este trámite.');
        }

        if (!$tramite->puedeSerEditado()) {
            return redirect()->route('tramites.show', $tramite)
                ->with('error', 'Este trámite no se puede editar en su estado actual (' . str_replace('_', ' ', $tramite->estatus) . ').');
        }

        // Observaciones agrupadas por sección para mostrarlas en el formulario
        // (aviso por sección) y en el checklist lateral. Se cargan TODAS (no
        // solo pendientes) para que el checklist muestre el progreso. Las
        // secciones coinciden con las que usa el modal de observación.
        $tramite->load(['requisitos', 'fundamentos', 'fichaPortal', 'observaciones.realizadaPor', 'derechos', 'relacionados.dependencia', 'procesosAtencion' => function ($q) {
            $q->orderBy('paso')->orderBy('subpaso');
        }]);
        $observacionesPorSeccion = $tramite->observaciones
            ->sortByDesc('created_at')
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_tramite');

        // Unidades de la dependencia del trámite, para la auto-selección.
        $dependencias = Dependencia::activas()->with('unidades')->orderBy('nombre')->get();
        $unidadesDependencia = \App\Models\UnidadAdministrativa::where('dependencia_id', $tramite->dependencia_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        // Datos del sujeto obligado y enlace. Antes se calculaban dentro
        // de la vista con queries directas (::find, ::vigenteDe, ::activos),
        // violando la separación de capas. Ahora viven aquí en el controlador.
        $sujetoActual = $tramite->sujeto_obligado_id
            ? SujetoObligado::find($tramite->sujeto_obligado_id)
            : SujetoObligado::vigenteDe($tramite->dependencia_id);
        $sujetosDisponibles = SujetoObligado::activos()
            ->where('dependencia_id', $tramite->dependencia_id)
            ->orderBy('nombre')
            ->get();
        $enlaceTramite = $tramite->enlace_id ? User::find($tramite->enlace_id) : null;
        $tiposServicio = config('punta.tipos_servicio', []);
        $ficha = $tramite->fichaPortal;

        return view('screens.tramites.edit', compact(
            'tramite', 'dependencias', 'observacionesPorSeccion', 'unidadesDependencia',
            'camposObservables', 'sujetoActual', 'sujetosDisponibles', 'enlaceTramite', 'tiposServicio',
            'ficha'
        ));
    }

    public function update(TramiteRequest $request, Tramite $tramite)
    {
        if (!$request->user()->puedeEditarTramite($tramite)) {
            abort(403, 'No tiene permiso para editar este trámite.');
        }

        if (!$tramite->puedeSerEditado()) {
            return redirect()->route('tramites.show', $tramite)
                ->with('error', 'Este trámite no se puede editar en su estado actual.');
        }

        // La validación se ejecutó automáticamente en TramiteRequest antes de
        // entrar al método. El controlador solo extrae lo que necesita con only().
        $data = $request->only([
            'nombre_oficial', 'naturaleza', 'tipo_tramite_id', 'tipo_servicio', 'dependencia_id', 'unidad_id',
            'sector_id', 'subsector_id',
            'servidor_publico', 'homoclave', 'sujeto_obligado_id', 'enlace_id',
            'objetivo', 'dirigido_a', 'frecuencia', 'volumen_anual', 'plazo_resolucion_cantidad',
            'plazo_resolucion_unidad', 'num_areas', 'areas_participantes', 'visitas_requeridas',
            'tiempo_traslado_horas', 'tiempo_traslado_min',
            'tiempo_espera_horas', 'tiempo_espera_min',
            'tiempo_atencion_horas', 'tiempo_atencion_min',
            'copias_cantidad', 'copias_precio', 'salario_hora_w', 'nivel_digitalizacion',
        ]);

        // Campos comunes con store() (fundamento, fj_*, catálogos, derechos var.).
        $data = array_merge($data, $this->camposComunesDesde($request));

        $this->tramiteService->actualizar(
            tramite:     $tramite,
            datos:       $data,
            derechos:    $this->leerDerechos($request),
            requisitos:  $request->input('requisitos', []),
            fichaPortal: $this->extraerFichaPortal($request),
            procesos:    $this->extraerProcesos($request),
        );

        $this->sincronizarRelacionados($tramite, $request);

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'Trámite actualizado exitosamente.');
    }

    public function destroy(Tramite $tramite)
    {
        if (!auth()->user()->tienePermiso('tramites.eliminar')) {
            abort(403, 'No tiene permiso para eliminar trámites.');
        }

        if (!request()->user()->puedeEliminarTramite($tramite)) {
            return back()->with('error', 'No tiene permiso para eliminar este trámite.');
        }

        $tramite->delete();
        return redirect()->route('tramites.index')
            ->with('success', 'Trámite eliminado correctamente.');
    }

    public function acuse(Tramite $tramite)
    {
        $tramite->load(['dependencia', 'requisitos', 'firmas.firmante']);
        return view('screens.tramites.acuse', compact('tramite'));
    }

    /**
     * Despacha el cambio de estatus según la acción recibida. Cada acción vive
     * en su propio método (una transición por método), con sus guardas y su
     * mensaje. Así se lee de un vistazo qué acciones existen y a dónde llevan.
     */
    public function actualizarEstatus(Request $request, Tramite $tramite)
    {
        $user = $request->user();

        return match ($request->input('accion')) {
            'publicar'              => $this->publicarTramite($tramite, $user),
            'republicar'            => $this->republicarTramite($tramite, $user),
            'atender_observaciones' => $this->atenderObservaciones($tramite, $user),
            'enviar_firma'          => $this->enviarAFirma($tramite, $user),
            'completar'             => $this->completarTramite($tramite),
            default                 => back()->with('error', 'Acción no válida.'),
        };
    }

    /** Publicar: borrador → en observación. */
    private function publicarTramite(Tramite $tramite, User $user)
    {
        if (!$tramite->puedeSerPublicado()) {
            return back()->with('error', 'Solo se puede publicar un trámite en borrador.');
        }
        if (!$user->puedeEditarTramite($tramite)) {
            return back()->with('error', 'No tiene permiso para publicar este trámite.');
        }
        $tramite->update(['estatus' => Tramite::ESTATUS_EN_OBSERVACION]);
        return back()->with('success', 'Trámite publicado. Inicia el periodo de observaciones.');
    }

    /** Republicar: corrección → en observación (cierra observaciones ya corregidas). */
    private function republicarTramite(Tramite $tramite, User $user)
    {
        if (!$tramite->puedeSerRepublicado()) {
            return back()->with('error', 'Solo se puede republicar un trámite en corrección.');
        }
        if (!$user->puedeEditarTramite($tramite)) {
            return back()->with('error', 'No tiene permiso.');
        }
        // Al republicar, las observaciones que el enlace ya corrigió
        // pasan a 'atendida'. Si la revisora quiere reabrir alguna, lo hace en
        // el nuevo ciclo de observaciones.
        $tramite->observaciones()
            ->whereIn('estatus', ['pendiente', 'en_atencion', 'reabierta'])
            ->update(['estatus' => 'atendida']);
        $tramite->update(['estatus' => Tramite::ESTATUS_EN_OBSERVACION]);
        $this->notificador->reenviado($tramite, $user);
        return back()->with('success', 'Trámite republicado para nueva revisión.');
    }

    /** Atender observaciones: en observación → en corrección. */
    private function atenderObservaciones(Tramite $tramite, User $user)
    {
        if ($tramite->estatus !== Tramite::ESTATUS_EN_OBSERVACION) {
            return back()->with('error', 'Solo se puede atender observaciones de un trámite en periodo de observación.');
        }
        if (!$user->puedeEditarTramite($tramite)) {
            return back()->with('error', 'No tiene permiso.');
        }
        if (!$tramite->tieneObservacionesPendientes()) {
            return back()->with('error', 'No hay observaciones pendientes que atender.');
        }
        $tramite->update(['estatus' => Tramite::ESTATUS_EN_CORRECCION]);
        return redirect()->route('tramites.edit', $tramite)
            ->with('success', 'Periodo de observaciones cerrado. Corrija lo señalado y republique cuando esté listo.');
    }

    /** Enviar a firma: en observación → en firma (solo revisora o admin). */
    private function enviarAFirma(Tramite $tramite, User $user)
    {
        if (!$tramite->puedeAvanzarAFirma()) {
            return back()->with('error', 'No se puede enviar a firma: hay observaciones pendientes o el trámite no está en observación.');
        }
        if (!$user->isAnyRol([User::ROL_ADMIN, User::ROL_REVISORA])) {
            return back()->with('error', 'Solo la revisora o el admin pueden enviar a firma.');
        }
        $tramite->update(['estatus' => Tramite::ESTATUS_EN_FIRMA]);
        return back()->with('success', 'Trámite enviado a firma.');
    }

    /** Completar: en firma → completado. */
    private function completarTramite(Tramite $tramite)
    {
        if ($tramite->estatus !== Tramite::ESTATUS_EN_FIRMA) {
            return back()->with('error', 'El trámite debe estar en firma para completarse.');
        }
        $tramite->update(['estatus' => Tramite::ESTATUS_COMPLETADO]);
        return back()->with('success', 'Trámite completado. Acuse final generado.');
    }

    /**
     * Campos del formulario de trámite que store() y update() arman igual:
     * fundamento jurídico, fundamento del costo (fj_*), catálogos oficiales,
     * derechos variables y datos de relación. Se centraliza aquí para no
     * duplicar la extracción en ambos métodos. Los campos exclusivos de alta
     * (created_by, dirigido_a) los añade store() por su cuenta.
     */
    private function camposComunesDesde(TramiteRequest $request): array
    {
        return [
            'tipo_tramite_id'      => $request->input('tipo_tramite_id') ?: null,
            'tipo_relacion'        => $request->input('tipo_relacion') ?: null,
            'relacionados_detalle' => $request->input('relacionados_detalle') ?: null,

            // Pago de derechos variable (ej. predial). El monto capturado se
            // usa como estimación; la referencia explica la base de cálculo.
            'monto_derechos_variable'   => $request->boolean('monto_derechos_variable'),
            'monto_derechos_referencia' => $request->input('monto_derechos_referencia') ?: null,

            // Catálogos oficiales (selección múltiple + etapa de operación).
            'acciones_simplificacion' => $request->input('acciones_simplificacion', []),
            'grupos_atencion'         => $request->input('grupos_atencion', []),
            'etapa_operacion'         => $request->input('etapa_operacion') ?: null,

            // Sujeto obligado y enlace: el formulario los manda como ocultos
            // derivados (sujeto obligado de la dependencia; enlace = usuario que
            // crea). Faltaba capturarlos en el alta — solo el update los tomaba.
            'sujeto_obligado_id'   => $request->input('sujeto_obligado_id') ?: null,
            'enlace_id'            => $request->input('enlace_id') ?: null,
            'poblacion_objetivo'   => $request->input('poblacion_objetivo'),

            // Fundamento jurídico: varias citas del catálogo y/o captura manual.
            'fundamento_modo'      => $request->input('fundamento_modo', 'catalogo'),
            'citas'                => $request->input('citas', []),
            'fundamento_normativa' => $request->input('fundamento_normativa'),
            'fundamento_tipo'      => $request->input('fundamento_tipo'),
            'fundamento_articulo'  => $request->input('fundamento_articulo'),
            'fundamento_resumen'   => $request->input('fundamento_resumen'),

            // Fundamento jurídico opcional del costo del trámite (columnas fj_*).
            'fj_norma'    => $request->input('costo_fj_norma'),
            'fj_capitulo' => $request->input('costo_fj_capitulo'),
            'fj_articulo' => $request->input('costo_fj_articulo'),
        ];
    }

    /**
     * Sincroniza los trámites relacionados (rubro 10.2). El componente
     * x-citar-tramite envía relacionados[i][id]; extraemos los IDs válidos,
     * descartamos el propio trámite (nunca se relaciona consigo mismo) y
     * sincronizamos la tabla pivot tramite_relacionados: sync() elimina los
     * que ya no están y agrega los nuevos, sin duplicar los existentes.
     */
    private function sincronizarRelacionados(Tramite $tramite, TramiteRequest $request): void
    {
        $idsRelacionados = collect($request->input('relacionados', []))
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === $tramite->id)
            ->values()
            ->toArray();

        $tramite->relacionados()->sync($idsRelacionados);
    }

    /**
     * Lee la lista de derechos enviada como JSON desde el formulario.
     * Devuelve un arreglo de ['concepto' => ..., 'monto' => ...], ya
     * filtrado: descarta filas sin concepto.
     */
    private function leerDerechos(Request $request): array
    {
        return \App\Models\TramiteDerecho::parsearJson($request->input('derechos_json'));
    }

    /**
     * Convierte el JSON de "pasos para realizar el trámite" al formato que
     * espera el servicio. Todos los pasos van como tipo 'atencion' (proceso
     * único). Conserva el orden y la marca de subpaso para numerar 1.1, 1.2.
     */
    private function extraerProcesos(Request $request): array
    {
        $pasos = [];
        $json  = $request->input('pasos_json');

        if ($json) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $principal = 0;
                $sub       = 0;
                foreach ($decoded as $p) {
                    $esSub = !empty($p['es_subpaso']);
                    if ($esSub) {
                        $sub++;
                    } else {
                        $principal++;
                        $sub = 0;
                    }
                    $pasos[] = [
                        'paso'    => $principal,
                        'subpaso' => $esSub ? $sub : 0,
                        'accion'  => $p['accion'] ?? null,
                        'area'    => $p['area'] ?? null,
                    ];
                }
            }
        }

        return ['atencion' => $pasos, 'resolucion' => []];
    }
}