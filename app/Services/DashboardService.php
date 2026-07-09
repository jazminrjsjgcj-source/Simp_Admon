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
                ['value' => Tramite::where('dependencia_id', $user->dependencia_id)->where('naturaleza', 'tramite')->count(),   'label' => 'Trámites'],
                ['value' => Tramite::where('dependencia_id', $user->dependencia_id)->where('naturaleza', 'servicio')->count(),  'label' => 'Servicios'],
                ['value' => PropuestaRegulatoria::where('dependencia_id', $user->dependencia_id)->count(),                      'label' => 'Propuestas regulatorias'],
                ['value' => AccionAgenda::where('dependencia_id', $user->dependencia_id)->count(),                              'label' => 'Acciones de agenda'],
                // Bug #37 (sesión anterior): contar en_observacion además de en_correccion.
                ['value' => Tramite::where('dependencia_id', $user->dependencia_id)
                    ->whereIn('estatus', ['en_observacion', 'en_correccion'])->count(),    'label' => 'Observaciones pendientes'],
            ],
            'admin' => [
                ['value' => DB::table('users')->count(),                                    'label' => 'Usuarios'],
                ['value' => DB::table('periodos')->count(),                                 'label' => 'Periodos'],
                ['value' => Tramite::where('naturaleza', 'tramite')->count(),               'label' => 'Trámites'],
                ['value' => Tramite::where('naturaleza', 'servicio')->count(),              'label' => 'Servicios'],
                ['value' => DB::table('bitacora')->whereDate('created_at', today())->count(), 'label' => 'Movimientos hoy'],
            ],
            'revisora' => $this->kpisRevisora(),
            'juridico' => $this->kpisJuridico($user),
            'sujeto'   => $this->kpisSujeto($user->dependencia_id),
            default => [
                ['value' => 0, 'label' => '—'], ['value' => 0, 'label' => '—'],
                ['value' => 0, 'label' => '—'], ['value' => 0, 'label' => '—'],
            ],
        };

        $kpiRoutes = match ($rol) {
            'enlace'   => ['tramites.index', 'tramites.index', 'agenda-regulatoria.index', 'agenda.index', 'tramites.index'],
            'admin'    => ['admin.usuarios.index', 'admin.periodos', 'tramites.index', 'tramites.index', 'admin.bitacora'],
            'revisora' => ['tramites.index', 'agenda.index', 'tramites.index', 'dashboard'],
            'juridico' => ['regulaciones.index', 'agenda-regulatoria.index', 'dashboard', 'dashboard'],
            'sujeto'   => ['tramites.index', 'agenda.index', 'agenda-regulatoria.index', 'firmas.index'],
            default    => ['dashboard', 'dashboard', 'dashboard', 'dashboard'],
        };

        $kpiTipos = match ($rol) {
            'enlace'   => [['tramites', 'tramites_dependencia'], ['tramites', 'servicios_dependencia'], ['propuestas', 'propuestas_dependencia'], ['agenda', 'agenda_dependencia'], ['tramites', 'en_correccion']],
            'revisora' => [[null, 'pendientes'], [null, 'por_revisar'], [null, 'por_aprobar'], [null, 'completados']],
            'juridico' => [[null, 'regulaciones_por_revisar'], [null, 'por_firmar'], [null, 'mis_observaciones'], [null, 'regulaciones_vigentes']],
            'sujeto'   => [[null, 'por_corregir'], [null, 'por_firmar'], [null, 'en_tramite'], [null, 'cerrados']],
            'admin'    => [[null, null], [null, null], ['tramites', 'solo_tramites'], ['tramites', 'solo_servicios'], [null, null]],
            default    => [[null, null], [null, null], [null, null], [null, null]],
        };

        // Listas de pendientes (todos los roles).
        $pendientesTramites  = collect();
        $pendientesServicios = collect();
        $pendientesAgenda    = collect();
        $pendientesPropu     = collect();
        $pendientesAir       = collect();

        if (in_array($rol, User::ROLES_TODOS)) {
            // admin y revisora ven todo; el resto (enlace, jurídico, sujeto) solo su dependencia.
            $veTodoPend  = in_array($rol, [User::ROL_ADMIN, User::ROL_REVISORA], true);
            $filtrarDep  = !$veTodoPend && $user->dependencia_id;
            $depUsuario  = $user->dependencia_id;

            // Visibilidad de BORRADORES (#32): un borrador es privado de quien lo
            // creó. En las listas de pendientes, solo su creador (o el admin) lo
            // ve; así la revisora no encuentra borradores ajenos —que aún no le
            // han enviado— mezclados con lo que sí debe atender.
            $soloBorradoresPropios = function ($q) use ($user) {
                if ($user->isRol(User::ROL_ADMIN)) {
                    return;
                }
                $q->where(function ($sub) use ($user) {
                    $sub->where('estatus', '!=', 'borrador')
                        ->orWhere('created_by', $user->id);
                });
            };

            $pendientesTramites = Tramite::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->where($soloBorradoresPropios)
                ->where('naturaleza', 'tramite')
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();

            $pendientesServicios = Tramite::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->where($soloBorradoresPropios)
                ->where('naturaleza', 'servicio')
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();

            $pendientesAgenda = AccionAgenda::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->where($soloBorradoresPropios)
                ->whereIn('estatus', ['en_correccion', 'en_observacion', 'borrador'])->latest()->take(5)->get();

            $pendientesPropu = PropuestaRegulatoria::when($filtrarDep, fn ($q) => $q->where('dependencia_id', $depUsuario))
                ->where($soloBorradoresPropios)
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

        // Feed de actividad reciente para el carrusel de transparencia del dashboard.
        $actividadGeneral = $this->cargarActividadGeneral();

        return compact(
            'rol', 'kpis', 'kpiRoutes', 'kpiTipos',
            'pendientesTramites', 'pendientesServicios', 'pendientesAgenda', 'pendientesPropu', 'pendientesAir',
            'panorama', 'sistemaTotales', 'pendientesFirma', 'actividadGeneral'
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
            'folio'   => $p->folio ?? 'Sin folio',
            'nombre'  => $p->nombre,
            'estatus' => $p->estatus ?? 'borrador',
            'fecha'   => $p->updated_at?->format('d/m/Y') ?? '—',
            'url'     => route('propuestas.show', $p->id),
        ];
        $filaRegulacion = fn ($r) => [
            'folio'   => $r->folio ?? 'Sin folio',
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
                            ->get(['id', 'folio', 'nombre', 'estatus', 'updated_at'])->map($filaRegulacion)
                    );
                }
            }

            $rows = $tramites->concat($agenda)->concat($regulaciones);
            return ['tipo' => $filtro, 'rows' => $rows->values()];
        }

        // Filtros simples por módulo (estatus directo).
        //
        // Devuelve la lista de estatus por la que se filtra, o null si el
        // filtro no impone estatus (solo aplica el alcance por dependencia).
        //
        // Los filtros '*_dependencia' provienen de los KPIs del rol enlace y
        // significan "registros de mi dependencia". El recorte por dependencia
        // ya lo hace $alcance dentro de $aplicarModulo, por eso devuelven null:
        // no añaden ningún estatus, solo se apoyan en el alcance.
        $estatusDe = fn (string $modulo): ?array => match ($filtro) {
            'en_revision'   => ['en_observacion', 'en_correccion'],
            'en_correccion' => ['en_correccion'],
            'tramites_dependencia',
            'propuestas_dependencia',
            'agenda_dependencia' => null,
            default              => null,
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
                ->latest()->take(20)->get(['id', 'folio', 'nombre', 'estatus', 'updated_at'])->map($filaPropuesta),
            default      => collect(),
        };

        return ['tipo' => $tipo, 'rows' => $rows->values()];
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /**
     * KPIs del rol revisora. Son contadores transversales (toda la institución,
     * sin filtro de dependencia) de categorías definidas en config/flujos.php.
     * Se calcula aquí, dentro del brazo del match, para no ejecutar estas
     * consultas cuando el dashboard lo abre otro rol.
     */
    private function kpisRevisora(): array
    {
        $cPendientes = collect(config('flujos.pendientes_incluye'))
            ->sum(fn ($cat) => $this->contarCategoria($cat));

        return [
            ['value' => $cPendientes,                                'label' => 'Pendientes'],
            ['value' => $this->contarCategoria('por_revisar'),       'label' => 'Por revisar'],
            ['value' => $this->contarCategoria('por_aprobar'),       'label' => 'Por aprobar'],
            ['value' => $this->contarCategoria('completados'),       'label' => 'Completados'],
        ];
    }

    /**
     * KPIs del rol jurídico. Tres contadores se acotan a la dependencia del
     * usuario; "Mis observaciones" cuenta las que ese usuario realizó y siguen
     * vivas. Se calcula dentro del brazo del match para no correr estas
     * consultas en los demás roles.
     */
    private function kpisJuridico(User $user): array
    {
        $dep = $user->dependencia_id;

        $cMisObs = Observacion::where('realizada_por', $user->id)
            ->whereIn('estatus', config('flujos.observaciones_vivas'))
            ->count();

        return [
            ['value' => $this->contarCategoria('regulaciones_por_revisar', $dep), 'label' => 'Regulaciones por revisar'],
            ['value' => $this->contarCategoria('por_firmar', $dep),               'label' => 'Por firmar'],
            ['value' => $cMisObs,                                                 'label' => 'Mis observaciones'],
            ['value' => $this->contarCategoria('regulaciones_vigentes', $dep),    'label' => 'Regulaciones vigentes'],
        ];
    }

    /**
     * KPIs del sujeto obligado. Todos los contadores se acotan a su dependencia.
     * Se calcula dentro del brazo del match para no correr estas consultas en
     * los demás roles.
     */
    private function kpisSujeto(?int $dep): array
    {
        return [
            ['value' => $this->contarCategoria('por_corregir', $dep), 'label' => 'Por corregir'],
            ['value' => $this->contarCategoria('por_firmar', $dep),   'label' => 'Por firmar'],
            ['value' => $this->contarCategoria('en_tramite', $dep),   'label' => 'En trámite'],
            ['value' => $this->contarCategoria('cerrados', $dep),     'label' => 'Completados'],
        ];
    }

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
     * Actividad general del sistema para el apartado de transparencia del
     * dashboard. Lee la bitácora y devuelve los últimos eventos significativos,
     * visibles para TODOS los roles sin filtro de dependencia (transparencia
     * total). Se excluyen los 'updated' porque son ruido (cada guardado dispara
     * uno); se muestran creaciones, aprobaciones, observaciones, firmas, etc.
     */
    private function cargarActividadGeneral(): \Illuminate\Support\Collection
    {
        // Para el carrusel de transparencia: solo dos clases de evento, sin
        // ruido. (1) Creaciones de registros, leídas de la bitácora (tipo
        // 'created'). (2) Registros que llegaron a su estatus final
        // (completado/publicada), consultados directamente porque "completar"
        // no deja un evento limpio en la bitácora (queda como 'updated').
        // No se incluyen borradores, ediciones ni observaciones.

        // Ventana de tiempo: solo actividad de los últimos 3 meses. Las
        // creaciones y completados más antiguos se consideran "noticias
        // viejas" y dejan de aparecer en el carrusel. Si no hay actividad
        // reciente en ningún módulo, el bloque entero se oculta (la vista
        // ya verifica $actividadGeneral->count() antes de renderizar).
        $desde = now()->subMonths(3);

        $iconoModulo = [
            'tramites'           => 'tramite',
            'agenda'             => 'agenda',
            'agenda_regulatoria' => 'propuesta',
            'regulaciones'       => 'regulacion',
        ];
        $etiquetaModulo = [
            'tramites'           => 'Trámite',
            'agenda'             => 'Agenda SyD',
            'agenda_regulatoria' => 'Propuesta',
            'regulaciones'       => 'Regulación',
        ];

        // (1) Creaciones recientes desde la bitácora.
        $creaciones = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->leftJoin('dependencias', 'bitacora.dependencia_id', '=', 'dependencias.id')
            ->where('bitacora.tipo', 'created')
            ->whereIn('bitacora.modulo', ['tramites', 'agenda', 'agenda_regulatoria', 'regulaciones'])
            ->where('bitacora.created_at', '>=', $desde)
            ->orderByDesc('bitacora.created_at')
            ->limit(20)
            ->get([
                'bitacora.modulo',
                'bitacora.accion',
                'bitacora.created_at',
                'users.name as autor',
                'dependencias.nombre as dependencia',
            ])
            ->map(function ($e) use ($iconoModulo, $etiquetaModulo) {
                $fecha = $e->created_at ? \Carbon\Carbon::parse($e->created_at) : null;
                // La acción viene como "Registro creado: NOMBRE". Separo el
                // prefijo (para mostrarlo en negritas) del nombre (peso normal).
                $partes  = explode(': ', $e->accion, 2);
                $prefijo = count($partes) === 2 ? $partes[0] . ':' : $e->accion;
                $nombre  = count($partes) === 2 ? $partes[1] : '';
                return (object) [
                    'evento'          => 'creado',
                    'icono'           => $iconoModulo[$e->modulo] ?? 'registro',
                    'modulo_etiqueta' => $etiquetaModulo[$e->modulo] ?? ucfirst($e->modulo),
                    'prefijo'         => $prefijo,
                    'nombre'          => $nombre,
                    'autor'           => $e->autor ?? 'Sistema',
                    'dependencia'     => $e->dependencia,
                    'fecha'           => $fecha,
                    'fecha_relativa'  => $fecha ? $fecha->diffForHumans() : '—',
                ];
            });

        // (2) Registros que llegaron a su estatus final.
        $completados = collect();

        $tramitesComp = Tramite::where('estatus', Tramite::ESTATUS_COMPLETADO)
            ->where('updated_at', '>=', $desde)
            ->with('dependencia')->latest('updated_at')->limit(20)->get();
        foreach ($tramitesComp as $t) {
            $completados->push((object) [
                'evento'          => 'completado',
                'icono'           => 'tramite',
                'modulo_etiqueta' => 'Trámite',
                'prefijo'         => 'Trámite completado:',
                'nombre'          => $t->nombre_oficial ?? 'ID #' . $t->id,
                'autor'           => null,
                'dependencia'     => $t->dependencia->nombre ?? null,
                'fecha'           => $t->updated_at,
                'fecha_relativa'  => $t->updated_at ? $t->updated_at->diffForHumans() : '—',
            ]);
        }

        $agendaComp = AccionAgenda::where('estatus', AccionAgenda::ESTATUS_COMPLETADO)
            ->where('updated_at', '>=', $desde)
            ->with('dependencia')->latest('updated_at')->limit(20)->get();
        foreach ($agendaComp as $a) {
            $completados->push((object) [
                'evento'          => 'completado',
                'icono'           => 'agenda',
                'modulo_etiqueta' => 'Agenda SyD',
                'prefijo'         => 'Acción completada:',
                'nombre'          => $a->descripcion ?? 'ID #' . $a->id,
                'autor'           => null,
                'dependencia'     => $a->dependencia->nombre ?? null,
                'fecha'           => $a->updated_at,
                'fecha_relativa'  => $a->updated_at ? $a->updated_at->diffForHumans() : '—',
            ]);
        }

        // Para propuestas, el estatus final es "publicada".
        $propComp = PropuestaRegulatoria::where('estatus', PropuestaRegulatoria::ESTATUS_PUBLICADA)
            ->where('updated_at', '>=', $desde)
            ->with('dependencia')->latest('updated_at')->limit(20)->get();
        foreach ($propComp as $p) {
            $completados->push((object) [
                'evento'          => 'completado',
                'icono'           => 'propuesta',
                'modulo_etiqueta' => 'Propuesta',
                'prefijo'         => 'Propuesta publicada:',
                'nombre'          => $p->nombre ?? 'ID #' . $p->id,
                'autor'           => null,
                'dependencia'     => $p->dependencia->nombre ?? null,
                'fecha'           => $p->updated_at,
                'fecha_relativa'  => $p->updated_at ? $p->updated_at->diffForHumans() : '—',
            ]);
        }

        // Mezclar creaciones + completados, ordenar por fecha y tomar los 15
        // más recientes para el carrusel.
        return $creaciones->concat($completados)
            ->sortByDesc(fn ($e) => $e->fecha)
            ->take(15)
            ->values();
    }
}
