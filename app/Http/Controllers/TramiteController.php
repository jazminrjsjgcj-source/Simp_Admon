<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\Tramite;
use App\Models\Dependencia;
use App\Models\FichaPortal;
use App\Models\TipoTramite;
use App\Models\Requisito;
use Illuminate\Http\Request;

class TramiteController extends Controller
{
    public function __construct(
        private \App\Services\CostoBurocraticoService $costoService,
        private \App\Services\NotificadorService $notificador,
        private \App\Services\TramiteService $tramiteService,
    ) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Tramite::with('dependencia')
            // Todos los roles ven todos los trámites; la restricción es en edición, no en consulta
            ->when($request->estatus,      fn ($q, $v) => $q->where('estatus', $v))
            ->when($request->dependencia,  fn ($q, $v) => $q->where('dependencia_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('nombre_oficial', 'like', '%' . $v . '%')
                    ->orWhere('homoclave', 'like', '%' . $v . '%');
            }))
            // Alias: ?costo= se mapea a costo_unitario para compatibilidad con URLs viejas
            ->when($request->filled('costo') && !$request->filled('costo_unitario'),
                fn ($q) => $request->merge(['costo_unitario' => $request->costo]) && $q)
            // Filtro por costo unitario (CBU): cuánto cuesta hacerlo una vez
            ->when($request->costo_unitario === 'bajo',  fn ($q) => $q->where('cbu_unitario', '<',  Tramite::CBU_UMBRAL_BAJO))
            ->when($request->costo_unitario === 'medio', fn ($q) => $q->whereBetween('cbu_unitario', [Tramite::CBU_UMBRAL_BAJO, Tramite::CBU_UMBRAL_ALTO]))
            ->when($request->costo_unitario === 'alto',  fn ($q) => $q->where('cbu_unitario', '>',  Tramite::CBU_UMBRAL_ALTO))
            // Filtro por impacto: clasificación contra umbral configurado
            ->when($request->impacto, fn ($q, $v) => $q->where('impacto', $v))
            ->latest();

        $tramites     = $query->paginate(20)->withQueryString();
        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        $estatuses    = Tramite::ESTATUS_TODOS;

        return view('screens.tramites.index', compact('tramites', 'dependencias', 'estatuses'));
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
            'requisitos'                => $tramite->requisitos->map(fn ($r) => [
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

        return view('screens.tramites.create', compact('dependencias', 'tramites', 'misUnidades', 'unidadAutoId'));
    }

    public function store(Request $request)
    {
        $esEnvio = $request->input('accion') === 'enviar';

        // Borrador: validación mínima. Enviar: validación completa.
        $reglas = $esEnvio
            ? $this->reglasValidacionTramite()
            : $this->reglasValidacionBorrador();

        $validated = $request->validate($reglas);

        $validated['tipo_tramite_id'] = $request->input('tipo_tramite_id') ?: null;
        $validated['created_by'] = $request->user()->id;
        $validated['dirigido_a'] = $validated['dirigido_a'] ?? 'ambas';
        $validated['tipo_relacion'] = $request->input('tipo_relacion') ?: null;

        // Fundamento jurídico: varias citas del catálogo y/o captura manual.
        $validated['citas']                = $request->input('citas', []);
        $validated['fundamento_normativa'] = $request->input('fundamento_normativa');
        $validated['fundamento_tipo']      = $request->input('fundamento_tipo');
        $validated['fundamento_resumen']   = $request->input('fundamento_resumen');

        // Fundamento jurídico opcional del costo del trámite.
        $validated['fj_norma']    = $request->input('costo_fj_norma');
        $validated['fj_capitulo'] = $request->input('costo_fj_capitulo');
        $validated['fj_articulo'] = $request->input('costo_fj_articulo');

        // El controlador extrae del request; el servicio recibe datos limpios.
        $tramite = $this->tramiteService->crear(
            datos:       $validated,
            derechos:    $this->leerDerechos($request),
            requisitos:  $request->input('requisitos', []),
            fichaPortal: $this->extraerFichaPortal($request),
            procesos:    $this->extraerProcesos($request),
            esEnvio:     $esEnvio,
        );

        $mensaje = $esEnvio
            ? 'Trámite guardado y enviado a revisión correctamente.'
            : 'Trámite guardado como borrador. Podrá editarlo y enviarlo a revisión posteriormente.';

        return redirect()->route('tramites.index')->with('success', $mensaje);
    }

    public function show(Tramite $tramite)
    {
        $tramite->load(['dependencia', 'unidad', 'requisitos', 'fundamentos', 'fichaPortal', 'observaciones.realizadaPor', 'firmas.firmante', 'derechos']);
        $snapshotCosto = $this->costoService->ultimoSnapshot($tramite);

        // Corrección #18: si el usuario puede observar, preparamos los datos
        // del modal de observación por campo. Los destinatarios son los
        // usuarios activos de la dependencia del trámite (los enlaces que
        // corregirán), igual que en el módulo de revisión.
        $puedeObservar = request()->user()->tienePermiso('tramites.observar');
        $revisores = collect();
        if ($puedeObservar) {
            $revisores = \App\Models\User::where('activo', true)
                ->where('dependencia_id', $tramite->dependencia_id)
                ->orderBy('name')
                ->get(['id', 'name', 'cargo']);
        }

        // Observaciones agrupadas por sección, para el checklist lateral y los
        // avisos por sección (corrección #18). Mismo formato que en edit().
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
        $tramite->load(['requisitos', 'fundamentos', 'observaciones.realizadaPor', 'derechos', 'procesosAtencion' => function ($q) {
            $q->orderBy('paso')->orderBy('subpaso');
        }]);
        $observacionesPorSeccion = $tramite->observaciones
            ->sortByDesc('created_at')
            ->groupBy('seccion');
        $camposObservables = config('punta.campos_observables_tramite');

        // Fase F.1: unidades de la dependencia del trámite para auto-selección.
        $dependencias = Dependencia::activas()->with('unidades')->orderBy('nombre')->get();
        $unidadesDependencia = \App\Models\UnidadAdministrativa::where('dependencia_id', $tramite->dependencia_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return view('screens.tramites.edit', compact(
            'tramite', 'dependencias', 'observacionesPorSeccion', 'unidadesDependencia', 'camposObservables'
        ));
    }

    public function update(Request $request, Tramite $tramite)
    {
        if (!$request->user()->puedeEditarTramite($tramite)) {
            abort(403, 'No tiene permiso para editar este trámite.');
        }

        if (!$tramite->puedeSerEditado()) {
            return redirect()->route('tramites.show', $tramite)
                ->with('error', 'Este trámite no se puede editar en su estado actual.');
        }

        $request->validate($this->reglasValidacionTramite());

        $data = $request->only([
            'nombre_oficial', 'tipo_tramite_id', 'dependencia_id', 'unidad_id',
            'sector_id', 'subsector_id',
            'servidor_publico', 'homoclave', 'sujeto_obligado_id', 'enlace_id',
            'objetivo', 'dirigido_a', 'frecuencia', 'volumen_anual', 'plazo_resolucion_cantidad',
            'plazo_resolucion_unidad', 'num_areas', 'areas_participantes', 'visitas_requeridas',
            'copias_cantidad', 'copias_precio', 'salario_hora_w', 'nivel_digitalizacion',
        ]);

        // Opción B: la lista de derechos es la fuente única del monto_derechos.
        // Convierte los derechos en UMA a pesos antes de sumar.
        $derechos = $this->leerDerechos($request);
        $data['monto_derechos'] = \App\Models\TramiteDerecho::totalEnPesos($derechos);

        // Si el enlace edita un trámite que está en periodo de observación,
        // al guardar pasa a corrección para indicar que ya empezó a atender.
        if ($tramite->estatus === Tramite::ESTATUS_EN_OBSERVACION) {
            $data['estatus'] = Tramite::ESTATUS_EN_CORRECCION;
        }

        $data = array_merge($data, Tramite::calcularCostoDesde(array_merge($tramite->toArray(), $data)));
        $tramite->update($data);

        // Guardar los conceptos de derechos (reemplazo total).
        $this->sincronizarDerechos($tramite, $derechos);

        // Regenerar homoclave si está vacía y hay dependencia y unidad
        if (empty($tramite->fresh()->homoclave) && $tramite->dependencia_id && $tramite->unidad_id) {
            $tramite->update(['homoclave' => $tramite->generarHomoclave()]);
        }

        $this->sincronizarRequisitos($tramite, $request->input('requisitos', []));
        $this->sincronizarFichaPortal($tramite, $request);
        $this->tramiteService->sincronizarProcesos($tramite, $this->extraerProcesos($request));

        // Sincronizar fundamento jurídico (citas del catálogo + captura manual).
        // Estaba ausente en update — solo existía en store. Ahora se guarda en ambos.
        $this->tramiteService->sincronizarFundamentoPublico($tramite, [
            'citas'                => $request->input('citas', []),
            'fundamento_normativa' => $request->input('fundamento_normativa'),
            'fundamento_tipo'      => $request->input('fundamento_tipo'),
            'fundamento_resumen'   => $request->input('fundamento_resumen'),
        ]);

        $this->costoService->recalcularYGuardar($tramite->fresh('requisitos'));

        return redirect()->route('tramites.show', $tramite)
            ->with('success', 'Trámite actualizado exitosamente.');
    }

    public function destroy(Tramite $tramite)
    {
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

    public function actualizarEstatus(Request $request, Tramite $tramite)
    {
        $accion = $request->input('accion');
        $user = $request->user();

        switch ($accion) {
            case 'publicar':
                if (!$tramite->puedeSerPublicado()) {
                    return back()->with('error', 'Solo se puede publicar un trámite en borrador.');
                }
                if (!$user->puedeEditarTramite($tramite)) {
                    return back()->with('error', 'No tiene permiso para publicar este trámite.');
                }
                $tramite->update(['estatus' => Tramite::ESTATUS_EN_OBSERVACION]);
                return back()->with('success', 'Trámite publicado. Inicia el periodo de observaciones.');

            case 'republicar':
                if (!$tramite->puedeSerRepublicado()) {
                    return back()->with('error', 'Solo se puede republicar un trámite en corrección.');
                }
                if (!$user->puedeEditarTramite($tramite)) {
                    return back()->with('error', 'No tiene permiso.');
                }
                $tramite->update(['estatus' => Tramite::ESTATUS_EN_OBSERVACION]);
                $this->notificador->reenviado($tramite, $user);
                return back()->with('success', 'Trámite republicado para nueva revisión.');

            case 'atender_observaciones':
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
                return back()->with('success', 'Periodo de observaciones cerrado. Atienda las observaciones y republique cuando esté listo.');

            case 'enviar_firma':
                if (!$tramite->puedeAvanzarAFirma()) {
                    return back()->with('error', 'No se puede enviar a firma: hay observaciones pendientes o el trámite no está en observación.');
                }
                if (!$user->isAnyRol([User::ROL_ADMIN, User::ROL_REVISORA])) {
                    return back()->with('error', 'Solo la revisora o el admin pueden enviar a firma.');
                }
                $tramite->update(['estatus' => Tramite::ESTATUS_EN_FIRMA]);
                return back()->with('success', 'Trámite enviado a firma.');

            case 'completar':
                if ($tramite->estatus !== Tramite::ESTATUS_EN_FIRMA) {
                    return back()->with('error', 'El trámite debe estar en firma para completarse.');
                }
                $tramite->update(['estatus' => Tramite::ESTATUS_COMPLETADO]);
                return back()->with('success', 'Trámite completado. Acuse final generado.');

            default:
                return back()->with('error', 'Acción no válida.');
        }
    }

    /**
     * Lee la lista de derechos enviada como JSON desde el formulario.
     * Devuelve un arreglo de ['concepto' => ..., 'monto' => ...], ya
     * filtrado: descarta filas sin concepto.
     */
    private function leerDerechos($request): array
    {
        return \App\Models\TramiteDerecho::parsearJson($request->input('derechos_json'));
    }

    /**
     * Guarda los conceptos de derechos de un trámite. Borra los anteriores
     * y crea los nuevos (estrategia simple de reemplazo total).
     */
    private function sincronizarDerechos(Tramite $tramite, array $derechos): void
    {
        $tramite->derechos()->delete();
        foreach ($derechos as $d) {
            $tramite->derechos()->create($d);
        }
    }

    /**
     * Fase F.4 — Sincroniza los datos de la ficha portal (incluyendo horarios JSON).
     *
     * Usa updateOrCreate para no duplicar el registro y mantener integridad.
     * Los campos portal_* vienen del wizard paso 6 (ficha ciudadana).
     */
    /**
     * Extrae los datos de la ficha portal del request a un array limpio,
     * para pasarlos al TramiteService. Misma lógica que sincronizarFichaPortal
     * pero devuelve los datos en vez de guardarlos.
     */
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

    private function extraerFichaPortal(Request $request): array
    {
        $camposPortal = [
            'nombre_ciudadano', 'tipo', 'descripcion', 'casos_realizarse',
            'modalidad', 'canal_principal', 'costo_publico', 'forma_pago',
            'resultado', 'doc_resultado', 'medio_entrega', 'vigencia',
            'oficina', 'telefono', 'correo', 'enlace_cita',
            'direccion', 'url',
        ];

        $datos = [];
        foreach ($camposPortal as $campo) {
            $valor = $request->input('portal_' . $campo) ?? $request->input($campo);
            if ($valor !== null) {
                $datos[$campo] = $valor;
            }
        }

        if ($request->filled('portal_horario')) {
            $datos['horario'] = $request->input('portal_horario');
        }

        if ($request->filled('horarios_json')) {
            $decoded = json_decode($request->input('horarios_json'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datos['horarios_json'] = $decoded;
            }
        }

        $datos['requiere_cita'] = $request->boolean('requiere_cita');

        return $datos;
    }

    private function sincronizarFichaPortal(Tramite $tramite, $request): void
    {
        $camposPortal = [
            'nombre_ciudadano', 'tipo', 'descripcion', 'casos_realizarse',
            'modalidad', 'canal_principal', 'costo_publico', 'forma_pago',
            'resultado', 'doc_resultado', 'medio_entrega', 'vigencia',
            'oficina', 'telefono', 'correo', 'enlace_cita',
            'direccion', 'url',
        ];

        $datos = [];
        foreach ($camposPortal as $campo) {
            $valor = $request->input('portal_' . $campo) ?? $request->input($campo);
            if ($valor !== null) {
                $datos[$campo] = $valor;
            }
        }

        // Horario legible (texto)
        if ($request->filled('portal_horario')) {
            $datos['horario'] = $request->input('portal_horario');
        }

        // Fase F.4: guardar estructura JSON de horarios
        if ($request->filled('horarios_json')) {
            $raw = $request->input('horarios_json');
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datos['horarios_json'] = $decoded;
            }
        }

        $datos['requiere_cita'] = $request->boolean('requiere_cita');

        if (!empty($datos)) {
            $tramite->fichaPortal()->updateOrCreate(
                ['tramite_id' => $tramite->id],
                $datos
            );
        }
    }

    /**
     * Sincroniza los requisitos enviados desde el formulario con los del trámite.
     * Crea los nuevos, actualiza los existentes y elimina los que ya no están.
     */
    private function sincronizarRequisitos(Tramite $tramite, array $enviados): void
    {
        $existentes   = $tramite->requisitos->pluck('id')->toArray();
        $actualizados = [];

        foreach ($enviados as $i => $req) {
            if (empty($req['nombre'])) {
                continue;
            }

            $datos = $this->extraerDatosRequisito($req, $i);

            if (!empty($req['id'])) {
                Requisito::where('id', $req['id'])->update($datos);
                $actualizados[] = (int) $req['id'];
            } else {
                $nuevo = $tramite->requisitos()->create($datos);
                $actualizados[] = $nuevo->id;
            }
        }

        $eliminar = array_diff($existentes, $actualizados);
        if ($eliminar) {
            Requisito::whereIn('id', $eliminar)->delete();
        }
    }

    /**
     * Normaliza los datos de un requisito recibido del formulario.
     */
    private function extraerDatosRequisito(array $req, int $orden): array
    {
        return [
            'orden'           => $orden + 1,
            'nombre'          => $req['nombre'],
            'original'        => !empty($req['original']),
            'copia'           => !empty($req['copia']),
            'dias_estimados'  => $req['dias']  ?? 0,
            'horas_estimadas' => $req['horas'] ?? 0,
            'observaciones'   => $req['observaciones'] ?? null,
        ];
    }

    /**
     * Reglas de validación para la creación y edición de un trámite.
     * Centralizadas para reutilizar y aplicar consistentemente los patrones
     * definidos en config/validation_patterns.php.
     */
    private function reglasValidacionTramite(): array
    {
        $patronTexto = config('validation_patterns.solo_texto.regex_php');

        return [
            'nombre_oficial'            => 'required|string|max:500',
            'tipo_tramite_id'           => 'nullable|exists:tipos_tramite,id',
            'homoclave'                 => 'nullable|string|max:50',
            'dependencia_id'            => 'required|exists:dependencias,id',
            'unidad_id'                 => 'nullable|exists:unidades_administrativas,id',
            'sector_id'                 => 'nullable|exists:sectores_scian,id',
            'subsector_id'              => 'nullable|exists:subsectores_scian,id',
            'servidor_publico'          => 'nullable|string|max:255' . ($patronTexto ? '|regex:' . $patronTexto : ''),
            'objetivo'                  => 'nullable|string',
            'dirigido_a'                => 'nullable|in:fisica,moral,ambas',
            'volumen_anual'             => 'nullable|integer|min:0',
            'monto_derechos'            => 'nullable|numeric|min:0',
            'plazo_resolucion_cantidad' => 'nullable|integer|min:0',
            'plazo_resolucion_unidad'   => 'nullable|in:habiles,naturales,meses',
            'salario_hora_w'            => 'nullable|numeric|min:0',
            'copias_cantidad'           => 'nullable|integer|min:0',
            'copias_precio'             => 'nullable|numeric|min:0',
            'nivel_digitalizacion'      => 'nullable|integer|min:1|max:5',
            'visitas_requeridas'        => 'nullable|integer|min:0',
            'num_areas'                 => 'nullable|integer|min:0',
            'areas_participantes'       => 'nullable|string|max:500',
            'tiempo_traslado_horas'     => 'nullable|integer|min:0',
            'tiempo_traslado_min'       => 'nullable|integer|min:0|max:59',
            'tiempo_espera_horas'       => 'nullable|integer|min:0',
            'tiempo_espera_min'         => 'nullable|integer|min:0|max:59',
            'tiempo_atencion_horas'     => 'nullable|integer|min:0',
            'tiempo_atencion_min'       => 'nullable|integer|min:0|max:59',
        ];
    }

    /**
     * Reglas mínimas para guardar como borrador.
     * Solo exige nombre y dependencia para identificar el registro.
     */
    private function reglasValidacionBorrador(): array
    {
        return [
            'nombre_oficial'            => 'required|string|max:500',
            'dependencia_id'            => 'required|exists:dependencias,id',
            'homoclave'                 => 'nullable|string|max:50',
            'unidad_id'                 => 'nullable|exists:unidades_administrativas,id',
            'sector_id'                 => 'nullable|exists:sectores_scian,id',
            'subsector_id'              => 'nullable|exists:subsectores_scian,id',
            'servidor_publico'          => 'nullable|string|max:255',
            'objetivo'                  => 'nullable|string',
            'dirigido_a'                => 'nullable|in:fisica,moral,ambas',
            'volumen_anual'             => 'nullable|integer|min:0',
            'monto_derechos'            => 'nullable|numeric|min:0',
            'plazo_resolucion_cantidad' => 'nullable|integer|min:0',
            'plazo_resolucion_unidad'   => 'nullable|in:habiles,naturales,meses',
            'salario_hora_w'            => 'nullable|numeric|min:0',
            'copias_cantidad'           => 'nullable|integer|min:0',
            'copias_precio'             => 'nullable|numeric|min:0',
            'nivel_digitalizacion'      => 'nullable|integer|min:1|max:5',
            'visitas_requeridas'        => 'nullable|integer|min:0',
            'num_areas'                 => 'nullable|integer|min:0',
            'areas_participantes'       => 'nullable|string|max:500',
            'tiempo_traslado_horas'     => 'nullable|integer|min:0',
            'tiempo_traslado_min'       => 'nullable|integer|min:0|max:59',
            'tiempo_espera_horas'       => 'nullable|integer|min:0',
            'tiempo_espera_min'         => 'nullable|integer|min:0|max:59',
            'tiempo_atencion_horas'     => 'nullable|integer|min:0',
            'tiempo_atencion_min'       => 'nullable|integer|min:0|max:59',
        ];
    }
}
