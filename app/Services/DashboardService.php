<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\AnalisisImpactoRegulatorio;
use App\Models\Observacion;
use App\Models\PropuestaRegulatoria;
use App\Models\Regulacion;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Concentra toda la lógica de datos del Dashboard:
 *   - Cálculo de KPIs por rol
 *   - Listas de pendientes y firmas
 *   - Panorama del admin
 *   - Filtros AJAX de la tabla inferior
 *
 * El DashboardController queda como coordinador HTTP delgado;
 * este servicio contiene las consultas y la construcción de los datos.
 */
class DashboardService
{
    // ─── Vista principal ──────────────────────────────────────────────────────

    /**
     * Devuelve el array de variables para la vista screens.dashboard.
     * El controlador lo pasa directamente a compact() o a view()->with().
     */
    public function datosVista(User $user, string $rol): array
    {
        // Contadores de categorías de la revisora (leídos de config/flujos.php).
        $cPorRevisar  = $this->contarCategoria('por_revisar');
        $cPorAprobar  = $this->contarCategoria('por_aprobar');
        $cCompletados = $this->contarCategoria('completados');
        $cPendientes  = collect(config('flujos.pendientes_incluye'))
            ->sum(fn ($cat) => $this->contarCategoria($cat));

        // Contadores del sujeto obligado: solo su dependencia.
        $dep          = $user->dependencia_id;
        $cPorCorregir = $this->contarCategoria('por_corregir', $dep);
        $cPorFirmar   = $this->contarCategoria('por_firmar', $dep);
        $cEnTramite   = $this->contarCategoria('en_tramite', $dep);
        $cCerrados    = $this->contarCategoria('cerrados', $dep);

        // Contadores del rol jurídico.
        $cRegRevisar   = $this->contarCategoria('regulaciones_por_revisar', $dep);
        $cRegVigentes  = $this->contarCategoria('regulaciones_vigentes', $dep);
        $cJurPorFirmar = $this->contarCategoria('por_firmar', $dep);
        $cMisObs = Observacion::where('realizada_por', $user->id)
            ->whereIn('estatus', config('flujos.observaciones_vivas'))
            ->count();

        // Panorama del admin (solo se calcula para ese rol).
        $panorama       = [];
        $sistemaTotales = [];
        if ($rol === 'admin') {
            foreach (config('flujos.panorama_admin') as $modulo => $def) {
                $panorama[] = [
                    'modulo'   => $modulo,
                    'etiqueta' => $def['etiqueta'],
                    'cifras'   => [
                        ['label' => 'Totales',            'value' => $this->contarPanorama($modulo, 'total'),   'filtro' => "{$modulo}_total"],
                        ['label' => 'En proceso',         'value' => $this->contarPanorama($modulo, 'proceso'), 'filtro' => "{$modulo}_proceso"],
                        ['label' => $def['cierre_label'], 'value' => $this->contarPanorama($modulo, 'cierre'),  'filtro' => "{$modulo}_cierre"],
                    ],
                ];
            }
            $sistemaTotales = [
                ['label' => 'Usuarios',        'value' => DB::table('users')->count(),                                     'ruta' => 'admin.usuarios.index'],
                ['label' => 'Dependencias',    'value' => DB::table('dependencias')->count(),                               'ruta' => 'admin.catalogos.dependencias'],
                ['label' => 'Movimientos hoy', 'value' => DB::table('bitacora')->whereDate('created_at', today())->count(), 'ruta' => 'admin.bitacora'],
            ];
        }

        // KPIs y rutas por rol.
        $kpis = match ($rol) {
            'enlace' => [
                ['value' => Tramite::where('dependencia_id', $user->dependencia_id)->count(),                                       'label' => 'Trámites de mi dependencia'],
                ['value' => PropuestaRegulatoria::where('dependencia_id', $user->dependencia_id)->count(),                          'label' => 'Propuestas regulatorias'],
                ['value' => AccionAgenda::where('dependencia_id', $user->dependencia_id)->count(),                                  'label' => 'Acciones de agenda'],
                ['value' => Tramite::where('dependencia_id', $user->dependencia_id)->where('estatus', 'en_correccion')->count(),    'label' => 'Observaciones pendientes'],
            ],
            'admin' => [
                ['value' => DB::table('users')->count(),    'label' => 'Usuarios'],
                ['value' => DB::table('periodos')->count(), 'label' => 'Periodos'],
                ['value' => Tramite::count(),               'label' => 'Trámites totales'],
                ['value' => DB::table('bitacora')->whereDate('created_at', today())->count(), 'label' => 'Movimientos hoy'],
            ],
            'revisora' => [
                ['value' => $cPendientes,  'label' => 'Pendientes'],
                ['value' => $cPorRevisar,  'label' => 'Por revisar'],
                ['value' => $cPorAprobar,  'label' => 'Por aprobar'],
                ['value' => $cCompletados, 'label' => 'Completados'],
            ],
            'juridico' => [
                ['value' => $cRegRevisar,   'label' => 'Regulaciones por revisar'],
                ['value' => $cJurPorFirmar, 'label' => 'Por firmar'],
                ['value' => $cMisObs,       'label' => 'Mis observaciones'],
                ['value' => $cRegVigentes,  'label' => 'Regulaciones vigentes'],
            ],
            'sujeto' => [
                ['value' => $cPorCorregir, 'label' => 'Por corregir'],
                ['value' => $cPorFirmar,   'label' => 'Por firmar'],
                ['value' => $cEnTramite,   'label' => 'En trámite'],
                ['value' => $cCerrados,    'label' => 'Completados'],
            ],
            default => [
                ['value' => 0, 'label' => '—'], ['value' => 0, 'label' => '—'],
                ['value' => 0, 'label' => '—'], ['value' => 0, 'label' => '—'],
            ],
        };

        $kpiRoutes = match ($rol) {
            'enlace'   => ['tramites.index', 'agenda-regulatoria.index', 'agenda.index', 'tramites.index'],
            'admin'    => ['admin.usuarios.index', 'admin.periodos', 'tramites.index', 'admin.bitacora'],
            'revisora' => ['tramites.index', 'agenda.index', 'tramites.index', 'dashboard'],
            'juridico' => ['regulaciones.index', 'agenda-regulatoria.index', 'dashboard', 'dashboard'],
            'sujeto'   => ['tramites.index', 'agenda.index', 'agenda-regulatoria.index', 'firmas.index'],
            default    => ['dashboard', 'dashboard', 'dashboard', 'dashboard'],
        };

        $kpiTipos = match ($rol) {
            'enlace'   => [['tramites', 'mios'], ['propuestas', 'mias'], ['agenda', 'mias'], ['tramites', 'en_correccion']],
            'revisora' => [[null, 'pendientes'], [null, 'por_revisar'], [null, 'por_aprobar'], [null, 'completados']],
            'juridico' => [[null, 'regulaciones_por_revisar'], [null, 'por_firmar'], [null, 'mis_observaciones'], [null, 'regulaciones_vigentes']],
            'sujeto'   => [[null, 'por_corregir'], [null, 'por_firmar'], [null, 'en_tramite'], [null, 'cerrados']],
            'admin'    => [[null, null], [null, null], ['tramites', 'todos'], [null, null]],
            default    => [[null, null], [null, null], [null, null], [null, null]],
        };

        // Listas de pendientes (todos los roles).
        $pendientesTramites = collect();
        $pendientesAgenda   = collect();
        $pendientesPropu    = collect();
        $pendientesAir      = collect();

        if (in_array($rol, User::ROLES_TODOS)) {
            // admin y revisora ven todo; el resto (enlace, jurídico, sujeto) solo su dependencia.
            $veTodoPend  = in_array($rol, [User::ROL_ADMIN, User::ROL_REVISORA], true);
            $filtrarDep  = !$veTodoPend && $user->dependencia_id;
            $depUsuario  = $user->dependencia_id;

            $pendientesTramites = Tramite::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();

            $pendientesAgenda = AccionAgenda::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();

            $pendientesPropu = PropuestaRegulatoria::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();
        }

        if ($user->tienePermiso('agenda_regulatoria.aprobar')) {
            $pendientesAir = AnalisisImpactoRegulatorio::with('propuesta.dependencia')
                ->where('estatus', AnalisisImpactoRegulatorio::ESTATUS_ENVIADO)
                ->latest()->take(5)->get();
        }

        // Pendientes de firma (sujeto y enlace).
        $pendientesFirma = collect();
        if (in_array($rol, ['sujeto', 'enlace'])) {
            $tipoFirma = $rol === 'sujeto' ? 'aceptacion_sujeto' : 'aceptacion_enlace';

            $tramitesFirma = Tramite::where('estatus', 'en_firma')
                ->whereDoesntHave('firmas', fn ($q) => $q->where('tipo', $tipoFirma)->where('firmante_id', $user->id)->where('estatus', 'activa'))
                ->when($rol === 'enlace', fn ($q) => $q->where('created_by', $user->id))
                ->when($rol === 'sujeto', fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
                ->get();

            foreach ($tramitesFirma as $t) {
                $pendientesFirma->push([
                    'folio'     => $t->homoclave ?? 'Sin folio',
                    'nombre'    => $t->nombre_oficial,
                    'tipo'      => 'Trámite',
                    'url_firma' => route('firmas.mostrar', ['tipo' => 'tramite', 'id' => $t->id]),
                ]);
            }

            $agendasFirma = AccionAgenda::where('estatus', AccionAgenda::ESTATUS_EN_FIRMA)
                ->whereDoesntHave('firmas', fn ($q) => $q->where('tipo', $tipoFirma)->where('firmante_id', $user->id)->where('estatus', 'activa'))
                ->when($rol === 'enlace', fn ($q) => $q->where('created_by', $user->id))
                ->when($rol === 'sujeto', fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
                ->get();

            foreach ($agendasFirma as $a) {
                $pendientesFirma->push([
                    'folio'     => $a->folio ?? 'AGD-' . str_pad($a->id, 3, '0', STR_PAD_LEFT),
                    'nombre'    => Str::limit($a->descripcion, 60),
                    'tipo'      => 'Agenda SyD',
                    'url_firma' => route('firmas.mostrar', ['tipo' => 'agenda', 'id' => $a->id]),
                ]);
            }
        }

        // Bug #B17: feed de actividad reciente del sistema (alternativa a notificaciones push).
        $feedActividad = $this->cargarFeedActividad($user, $rol);

        return compact(
            'rol', 'kpis', 'kpiRoutes', 'kpiTipos',
            'pendientesTramites', 'pendientesAgenda', 'pendientesPropu', 'pendientesAir',
            'panorama', 'sistemaTotales', 'pendientesFirma', 'feedActividad'
        );
    }

    // ─── Filtros AJAX ─────────────────────────────────────────────────────────

    /**
     * Maneja los filtros de la tabla inferior del dashboard.
     * Devuelve ['tipo' => ..., 'rows' => Collection] para serializar a JSON.
     */
    public function filtrar(User $user, string $tipo, string $filtro): array
    {
        // admin y revisora son transversales (ven todo). El resto (enlace,
        // jurídico, sujeto) solo ve lo de su dependencia. Este criterio coincide
        // con puedeVerRegistro() del modelo User, evitando que el dashboard
        // muestre registros que luego el show() bloquea con 403.
        $veTodo        = $user->isAnyRol([User::ROL_REVISORA, User::ROL_ADMIN]);
        $depId         = $user->dependencia_id;
        $filtrarPorDep = (!$veTodo && $depId);

        $alcance = function ($query) use ($filtrarPorDep, $depId) {
            return $query->when($filtrarPorDep, fn ($q) => $q->where('dependencia_id', $depId));
        };

        // Formatters: modelo → fila de tabla
        $filaTramite = fn ($t) => [
            'folio'   => $t->homoclave ?? 'Sin folio',
            'nombre'  => $t->nombre_oficial,
            'estatus' => $t->estatus,
            'fecha'   => $t->updated_at?->format('d/m/Y') ?? '—',
            'url'     => route('tramites.show', $t->id),
        ];
        $filaAgenda = fn ($a) => [
            'folio'   => 'AGD-' . str_pad($a->id, 3, '0', STR_PAD_LEFT),
            'nombre'  => $a->descripcion,
            'estatus' => $a->estatus,
            'fecha'   => $a->updated_at?->format('d/m/Y') ?? '—',
            'url'     => route('agenda.show', $a->id),
        ];
        $filaPropuesta = fn ($p) => [
            'folio'   => 'REG-' . str_pad($p->id, 3, '0', STR_PAD_LEFT),
            'nombre'  => $p->nombre,
            'estatus' => $p->estatus ?? 'borrador',
            'fecha'   => $p->updated_at?->format('d/m/Y') ?? '—',
            'url'     => route('propuestas.show', $p->id),
        ];
        $filaRegulacion = fn ($r) => [
            'folio'   => 'REG-' . str_pad($r->id, 3, '0', STR_PAD_LEFT),
            'nombre'  => $r->nombre,
            'estatus' => $r->estatus,
            'fecha'   => $r->updated_at?->format('d/m/Y') ?? '—',
            'url'     => route('regulaciones.show', $r->id),
        ];

        // Caso especial: "mis observaciones" (rol jurídico).
        if ($filtro === 'mis_observaciones') {
            $rutaPorClase = [
                Tramite::class              => 'tramites.show',
                AccionAgenda::class         => 'agenda.show',
                PropuestaRegulatoria::class => 'propuestas.show',
            ];
            $rows = Observacion::with('observable')
                ->where('realizada_por', $user->id)
                ->whereIn('estatus', config('flujos.observaciones_vivas'))
                ->latest()->take(20)->get()
                ->map(function ($obs) use ($rutaPorClase) {
                    $ruta = $rutaPorClase[$obs->observable_type] ?? null;
                    return [
                        'folio'   => 'OBS-' . str_pad($obs->id, 3, '0', STR_PAD_LEFT),
                        'nombre'  => $obs->seccion
                            ? ($obs->seccion . ': ' . Str::limit($obs->texto, 60))
                            : Str::limit($obs->texto, 80),
                        'estatus' => $obs->estatus,
                        'fecha'   => $obs->created_at?->format('d/m/Y') ?? '—',
                        'url'     => ($ruta && $obs->observable_id) ? route($ruta, $obs->observable_id) : '#',
                    ];
                });
            return ['tipo' => $filtro, 'rows' => $rows->values()];
        }

        // Panorama del admin: filtros tipo "{modulo}_{grupo}"
        $panoramaModulos = array_keys(config('flujos.panorama_admin'));
        $partes   = explode('_', (string) $filtro);
        $grupoPan = array_pop($partes);
        $moduloPan = implode('_', $partes);

        if (in_array($moduloPan, $panoramaModulos, true) && in_array($grupoPan, ['total', 'proceso', 'cierre'], true)) {
            $modelo = $this->modeloDe($moduloPan);
            $query  = $modelo::query();

            if ($grupoPan !== 'total') {
                $estatusPan = config("flujos.panorama_admin.{$moduloPan}.{$grupoPan}", []);
                if (!empty($estatusPan)) {
                    $query->whereIn('estatus', $estatusPan);
                }
            }

            [$fila, $cols] = match ($moduloPan) {
                'tramites'     => [$filaTramite,    ['id', 'homoclave', 'nombre_oficial', 'estatus', 'updated_at']],
                'agenda'       => [$filaAgenda,     ['id', 'descripcion', 'estatus', 'updated_at']],
                'propuestas'   => [$filaPropuesta,  ['id', 'nombre', 'estatus', 'updated_at']],
                'regulaciones' => [$filaRegulacion, ['id', 'nombre', 'estatus', 'updated_at']],
            };
            $rows = $query->latest()->take(50)->get($cols)->map($fila);
            return ['tipo' => $filtro, 'rows' => $rows->values()];
        }

        // Categorías de la autoridad revisora.
        $categoriasValidas = array_keys(config('flujos.categorias'));
        $esCategoria = ($filtro === 'pendientes') || in_array($filtro, $categoriasValidas, true);

        if ($esCategoria) {
            $cats     = $filtro === 'pendientes' ? config('flujos.pendientes_incluye') : [$filtro];
            $tramites = collect();
            $agenda   = collect();
            $regulaciones = collect();

            foreach ($cats as $cat) {
                $qt = $this->queryCategoria('tramites', $cat);
                if ($qt) {
                    $tramites = $tramites->concat(
                        $alcance($qt)->latest()->take(20)
                            ->get(['id', 'homoclave', 'nombre_oficial', 'estatus', 'updated_at'])->map($filaTramite)
                    );
                }
                $qa = $this->queryCategoria('agenda', $cat);
                if ($qa) {
                    $agenda = $agenda->concat(
                        $alcance($qa)->latest()->take(20)
                            ->get(['id', 'descripcion', 'estatus', 'updated_at'])->map($filaAgenda)
                    );
                }
                $qr = $this->queryCategoria('regulaciones', $cat);
                if ($qr) {
                    $regulaciones = $regulaciones->concat(
                        $alcance($qr)->latest()->take(20)
                            ->get(['id', 'nombre', 'estatus', 'updated_at'])->map($filaRegulacion)
                    );
                }
            }

            $rows = $tramites->concat($agenda)->concat($regulaciones);
            return ['tipo' => $filtro, 'rows' => $rows->values()];
        }

        // Filtros simples por módulo (estatus directo).
        $estatusDe = fn (string $modulo): ?array => match ($filtro) {
            'en_revision'   => ['en_observacion', 'en_correccion'],
            'en_correccion' => ['en_correccion'],
            default         => null,
        };

        $aplicarModulo = function ($query, string $modulo) use ($alcance, $estatusDe) {
            $alcance($query);
            $est = $estatusDe($modulo);
            if ($est) {
                $query->whereIn('estatus', $est);
            }
            return $query;
        };

        $rows = match ($tipo) {
            'tramites'   => $aplicarModulo(Tramite::query(), 'tramites')
                ->latest()->take(20)->get(['id', 'homoclave', 'nombre_oficial', 'estatus', 'updated_at'])->map($filaTramite),
            'agenda'     => $aplicarModulo(AccionAgenda::query(), 'agenda')
                ->latest()->take(20)->get(['id', 'descripcion', 'estatus', 'updated_at'])->map($filaAgenda),
            'propuestas' => $aplicarModulo(PropuestaRegulatoria::query(), 'propuestas')
                ->latest()->take(20)->get(['id', 'nombre', 'estatus', 'updated_at'])->map($filaPropuesta),
            default      => collect(),
        };

        return ['tipo' => $tipo, 'rows' => $rows->values()];
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Mapea la clave de módulo a su clase de modelo Eloquent.
     */
    private function modeloDe(string $modulo): ?string
    {
        return match ($modulo) {
            'tramites'     => Tramite::class,
            'agenda'       => AccionAgenda::class,
            'propuestas'   => PropuestaRegulatoria::class,
            'regulaciones' => Regulacion::class,
            default        => null,
        };
    }

    /**
     * Construye la query de un módulo para una categoría definida en config/flujos.php.
     * Devuelve null si la categoría no aplica a ese módulo.
     */
    private function queryCategoria(string $modulo, string $categoria)
    {
        $def = config("flujos.categorias.{$categoria}.{$modulo}");
        if (!$def) {
            return null;
        }
        $modelo = $this->modeloDe($modulo);
        if (!$modelo) {
            return null;
        }

        $obsVivas = config('flujos.observaciones_vivas');
        $query    = $modelo::whereIn('estatus', $def['estatus']);

        if ($def['obs_vivas'] === true) {
            $query->whereHas('observaciones', fn ($q) => $q->whereIn('estatus', $obsVivas));
        } elseif ($def['obs_vivas'] === false) {
            $query->whereDoesntHave('observaciones', fn ($q) => $q->whereIn('estatus', $obsVivas));
        }

        return $query;
    }

    /**
     * Cuenta registros de una categoría sumando todos los módulos que aplican.
     */
    private function contarCategoria(string $categoria, ?int $depId = null): int
    {
        $total = 0;
        foreach (['tramites', 'agenda', 'regulaciones'] as $modulo) {
            $q = $this->queryCategoria($modulo, $categoria);
            if ($q) {
                if ($depId) {
                    $q->where('dependencia_id', $depId);
                }
                $total += $q->count();
            }
        }
        return $total;
    }

    /**
     * Cuenta registros de un módulo para el panorama del admin.
     * $grupo: 'total' (todos), 'proceso' o 'cierre' (filtran por estatus del config).
     */
    private function contarPanorama(string $modulo, string $grupo): int
    {
        $modelo = $this->modeloDe($modulo);
        if (!$modelo) {
            return 0;
        }
        if ($grupo === 'total') {
            return $modelo::count();
        }
        $estatus = config("flujos.panorama_admin.{$modulo}.{$grupo}", []);
        return empty($estatus) ? 0 : $modelo::whereIn('estatus', $estatus)->count();
    }

    /**
     * Bug #B17: feed de actividad reciente del sistema.
     *
     * Lee los últimos eventos de la tabla `bitacora` (que ya se llena
     * automáticamente vía AuditObserver + BitacoraService) y los formatea
     * como noticias mostrables en el dashboard.
     *
     * Es la alternativa a recibir notificaciones push de TODO: el usuario
     * que quiera enterarse de lo que pasa entra al feed; quien necesita
     * actuar recibe notificaciones puntuales (sistema actual).
     *
     * Reglas de visibilidad:
     *  - admin / revisora: ven actividad de todas las dependencias
     *  - jurídico / enlace / sujeto: ven solo actividad de su dependencia
     *
     * Filtros aplicados:
     *  - Se excluyen eventos 'updated' del AuditObserver porque son ruido
     *    (cada edit de formulario dispara uno). Sí se muestran 'created',
     *    'deleted' y los eventos custom (hito, observacion, etc.).
     *  - Se traen los 10 más recientes para que el dashboard cargue rápido.
     */
    private function cargarFeedActividad(User $user, string $rol): \Illuminate\Support\Collection
    {
        $veTodo = in_array($rol, [User::ROL_ADMIN, User::ROL_REVISORA], true);

        // Mapa de modulo → ruta para construir el link al detalle
        $rutaPorModulo = [
            'tramites'           => 'tramites.show',
            'agenda'             => 'agenda.show',
            'agenda_regulatoria' => 'propuestas.show',
            'regulaciones'       => 'regulaciones.show',
        ];

        $query = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->leftJoin('dependencias', 'bitacora.dependencia_id', '=', 'dependencias.id')
            ->whereNotIn('bitacora.tipo', ['updated'])   // filtrar el ruido de updates
            ->select(
                'bitacora.id',
                'bitacora.auditable_type',
                'bitacora.auditable_id',
                'bitacora.modulo',
                'bitacora.tipo',
                'bitacora.accion',
                'bitacora.created_at',
                'users.name as autor_nombre',
                'dependencias.nombre as dependencia_nombre'
            )
            ->orderByDesc('bitacora.created_at')
            ->limit(10);

        // Scope por rol: si no ve todo, filtrar por su dependencia
        if (!$veTodo && $user->dependencia_id) {
            $query->where('bitacora.dependencia_id', $user->dependencia_id);
        }

        return $query->get()->map(function ($evento) use ($rutaPorModulo) {
            // Intento construir URL al detalle del registro auditado
            $ruta = $rutaPorModulo[$evento->modulo] ?? null;
            $url  = ($ruta && $evento->auditable_id) ? route($ruta, $evento->auditable_id) : null;

            $fecha = $evento->created_at ? \Carbon\Carbon::parse($evento->created_at) : null;
            $fechaRelativa = $fecha ? $fecha->diffForHumans() : '—';

            // Etiqueta legible del módulo para el chip de identificación
            $etiquetasModulo = [
                'tramites'           => 'Trámite',
                'agenda'             => 'Agenda SyD',
                'agenda_regulatoria' => 'Propuesta',
                'regulaciones'       => 'Regulación',
                'air'                => 'AIR',
                'usuarios'           => 'Usuarios',
                'periodos'           => 'Periodo',
            ];

            // Agrupador "Hoy", "Ayer" o fecha
            if ($fecha?->isToday()) {
                $grupo = 'Hoy';
            } elseif ($fecha?->isYesterday()) {
                $grupo = 'Ayer';
            } else {
                $grupo = $fecha?->translatedFormat('d \d\e F') ?? 'Sin fecha';
            }

            return (object) [
                'id'                => $evento->id,
                'modulo_etiqueta'   => $etiquetasModulo[$evento->modulo] ?? ucfirst($evento->modulo),
                'modulo'            => $evento->modulo,
                'tipo'              => $evento->tipo,
                'accion'            => $evento->accion,
                'autor'             => $evento->autor_nombre ?? 'Sistema',
                'dependencia'       => $evento->dependencia_nombre,
                'fecha_relativa'    => $fechaRelativa,
                'grupo'             => $grupo,
                'url'               => $url,
            ];
        });
    }
}
