<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Tramite;
use App\Models\Regulacion;
use App\Models\AccionAgenda;
use App\Models\PropuestaRegulatoria;
use App\Models\AnalisisImpactoRegulatorio;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $rol  = $user->rolEfectivo();

        // Contadores de cada categoría de la revisora (leídos de config/flujos.php).
        // queryCategoria() construye la query a partir de la definición del config,
        // así no hay estatus hardcodeados aquí.
        $cPorRevisar  = $this->contarCategoria('por_revisar');
        $cPorAprobar  = $this->contarCategoria('por_aprobar');
        $cCompletados = $this->contarCategoria('completados');
        $cPendientes  = collect(config('flujos.pendientes_incluye'))
            ->sum(fn ($cat) => $this->contarCategoria($cat));

        // Contadores del sujeto obligado: solo su dependencia.
        $depSujeto    = $user->dependencia_id;
        $cPorCorregir = $this->contarCategoria('por_corregir', $depSujeto);
        $cPorFirmar   = $this->contarCategoria('por_firmar', $depSujeto);
        $cEnTramite   = $this->contarCategoria('en_tramite', $depSujeto);
        $cCerrados    = $this->contarCategoria('cerrados', $depSujeto);

        // Contadores del rol jurídico: filtra por su dependencia, como el sujeto.
        $depJuridico  = $user->dependencia_id;
        $cRegRevisar  = $this->contarCategoria('regulaciones_por_revisar', $depJuridico);
        $cRegVigentes = $this->contarCategoria('regulaciones_vigentes', $depJuridico);
        $cJurPorFirmar = $this->contarCategoria('por_firmar', $depJuridico);
        // "Mis observaciones": las que él hizo y siguen vivas (por autor, no por estatus de módulo).
        $cMisObs = \App\Models\Observacion::where('realizada_por', $user->id)
            ->whereIn('estatus', config('flujos.observaciones_vivas'))
            ->count();

        // Panorama del admin: una fila por módulo, con total / en proceso / cierre.
        // Solo se calcula para el admin (evita queries innecesarias en otros roles).
        $panorama = [];
        $sistemaTotales = [];
        if ($rol === 'admin') {
            foreach (config('flujos.panorama_admin') as $modulo => $def) {
                $panorama[] = [
                    'modulo'   => $modulo,
                    'etiqueta' => $def['etiqueta'],
                    'cifras'   => [
                        ['label' => 'Totales',               'value' => $this->contarPanorama($modulo, 'total'),   'filtro' => "{$modulo}_total"],
                        ['label' => 'En proceso',            'value' => $this->contarPanorama($modulo, 'proceso'), 'filtro' => "{$modulo}_proceso"],
                        ['label' => $def['cierre_label'],    'value' => $this->contarPanorama($modulo, 'cierre'),  'filtro' => "{$modulo}_cierre"],
                    ],
                ];
            }
            $sistemaTotales = [
                ['label' => 'Usuarios',        'value' => DB::table('users')->count(),                                        'ruta' => 'admin.usuarios.index'],
                ['label' => 'Dependencias',    'value' => DB::table('dependencias')->count(),                                  'ruta' => 'admin.catalogos.dependencias'],
                ['label' => 'Movimientos hoy', 'value' => DB::table('bitacora')->whereDate('created_at', today())->count(),    'ruta' => 'admin.bitacora'],
            ];
        }

        $kpis = match($rol) {
            'enlace' => [
                ['value' => Tramite::where('created_by', $user->id)->count(),                               'label' => 'Mis trámites'],
                ['value' => PropuestaRegulatoria::where('created_by', $user->id)->count(),                  'label' => 'Propuestas regulatorias'],
                ['value' => AccionAgenda::where('created_by', $user->id)->count(),                          'label' => 'Acciones de agenda'],
                ['value' => Tramite::where('created_by', $user->id)->where('estatus','en_correccion')->count(), 'label' => 'Observaciones pendientes'],
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
                ['value'=>0,'label'=>'—'],['value'=>0,'label'=>'—'],
                ['value'=>0,'label'=>'—'],['value'=>0,'label'=>'—'],
            ],
        };

        $kpiRoutes = match($rol) {
            'enlace'   => ['tramites.index', 'agenda-regulatoria.index', 'agenda.index', 'tramites.index'],
            'admin'    => ['admin.usuarios.index', 'admin.periodos', 'tramites.index', 'admin.bitacora'],
            'revisora' => ['tramites.index', 'agenda.index', 'tramites.index', 'dashboard'],
            'juridico' => ['regulaciones.index', 'agenda-regulatoria.index', 'dashboard', 'dashboard'],
            'sujeto'   => ['tramites.index', 'agenda.index', 'agenda-regulatoria.index', 'firmas.index'],
            default    => ['dashboard', 'dashboard', 'dashboard', 'dashboard'],
        };

        $pendientesTramites = collect();
        $pendientesAgenda   = collect();
        $pendientesPropu    = collect();
        $pendientesAir      = collect();

        if (in_array($rol, User::ROLES_TODOS)) {
            $filtrarPorUsuario = ($rol === User::ROL_ENLACE);

            $pendientesTramites = Tramite::when($filtrarPorUsuario, fn ($q) => $q->where('created_by', $user->id))
                ->whereIn('estatus', ['en_correccion','en_observacion','borrador'])->latest()->take(5)->get();
            $pendientesAgenda = AccionAgenda::when($filtrarPorUsuario, fn ($q) => $q->where('created_by', $user->id))
                ->whereIn('estatus', ['en_correccion','en_observacion','borrador'])->latest()->take(5)->get();
            $pendientesPropu = PropuestaRegulatoria::when($filtrarPorUsuario, fn ($q) => $q->where('created_by', $user->id))
                ->whereIn('estatus', ['en_correccion','en_observacion','borrador'])->latest()->take(5)->get();
        }

        // Dictámenes AIR pendientes: solo para quien puede dictaminar
        // (revisora y admin). Son los AIR en estatus 'enviado', esperando
        // dictamen. Se muestran en el dashboard para que la revisora no
        // tenga que entrar a un módulo aparte.
        if ($user->tienePermiso('agenda_regulatoria.aprobar')) {
            $pendientesAir = AnalisisImpactoRegulatorio::with('propuesta.dependencia')
                ->where('estatus', AnalisisImpactoRegulatorio::ESTATUS_ENVIADO)
                ->latest()->take(5)->get();
        }

        // Cada tarjeta KPI define [módulo, filtro de estatus] para la tabla.
        // El filtro lo traduce el método filtrar() a estatus concretos.
        // null = la tarjeta no filtra la tabla (es un enlace normal).
        $kpiTipos = match($rol) {
            'enlace'   => [['tramites','mios'], ['propuestas','mias'], ['agenda','mias'], ['tramites','en_correccion']],
            'revisora' => [[null,'pendientes'], [null,'por_revisar'], [null,'por_aprobar'], [null,'completados']],
            'juridico' => [[null,'regulaciones_por_revisar'], [null,'por_firmar'], [null,'mis_observaciones'], [null,'regulaciones_vigentes']],
            'sujeto'   => [[null,'por_corregir'], [null,'por_firmar'], [null,'en_tramite'], [null,'cerrados']],
            'admin'    => [[null,null], [null,null], ['tramites','todos'], [null,null]],
            default    => [[null,null], [null,null], [null,null], [null,null]],
        };

        // Pendientes de firma para sujeto y enlace
        $pendientesFirma = collect();
        if (in_array($rol, ['sujeto', 'enlace'])) {
            $tipoFirma = $rol === 'sujeto' ? 'aceptacion_sujeto' : 'aceptacion_enlace';

            // Trámites en firma donde el usuario NO ha firmado aún
            $tramitesFirma = \App\Models\Tramite::where('estatus', 'en_firma')
                ->whereDoesntHave('firmas', fn($q) => $q->where('tipo', $tipoFirma)->where('firmante_id', $user->id)->where('estatus', 'activa'))
                ->when($rol === 'enlace', fn($q) => $q->where('created_by', $user->id))
                ->when($rol === 'sujeto', fn($q) => $q->where('dependencia_id', $user->dependencia_id))
                ->get();

            foreach ($tramitesFirma as $t) {
                $pendientesFirma->push([
                    'folio'    => $t->homoclave ?? 'Sin folio',
                    'nombre'   => $t->nombre_oficial,
                    'tipo'     => 'Trámite',
                    'url_firma' => route('firmas.mostrar', ['tipo' => 'tramite', 'id' => $t->id]),
                ]);
            }

            // Agendas en firma donde el usuario NO ha firmado aún
            $agendasFirma = \App\Models\AccionAgenda::where('estatus', \App\Models\AccionAgenda::ESTATUS_EN_FIRMA)
                ->whereDoesntHave('firmas', fn($q) => $q->where('tipo', $tipoFirma)->where('firmante_id', $user->id)->where('estatus', 'activa'))
                ->when($rol === 'enlace', fn($q) => $q->where('created_by', $user->id))
                ->when($rol === 'sujeto', fn($q) => $q->where('dependencia_id', $user->dependencia_id))
                ->get();

            foreach ($agendasFirma as $a) {
                $pendientesFirma->push([
                    'folio'    => $a->folio ?? 'AGD-' . str_pad($a->id, 3, '0', STR_PAD_LEFT),
                    'nombre'   => \Illuminate\Support\Str::limit($a->descripcion, 60),
                    'tipo'     => 'Agenda SyD',
                    'url_firma' => route('firmas.mostrar', ['tipo' => 'agenda', 'id' => $a->id]),
                ]);
            }
        }

        return view('screens.dashboard', compact('rol','kpis','kpiRoutes','kpiTipos','pendientesTramites','pendientesAgenda','pendientesPropu','pendientesAir','panorama','sistemaTotales','pendientesFirma'));
    }
    /**
     * Fase H.2 — Filtros Dashboard inline.
     *
     * Devuelve JSON con los registros filtrados por tipo (tramites, agenda, propuestas).
     * El JS del dashboard actualiza la tabla inferior sin redirigir.
     */
    public function filtrar(Request $request)
    {
        $tipo   = $request->input('tipo');
        $filtro = $request->input('filtro'); // categoría o estatus a mostrar
        $user   = auth()->user();
        $rol    = $user->rolEfectivo();

        // Quién ve qué:
        //  - enlace            → solo los registros que él creó (created_by).
        //  - revisora / admin  → TODO (autoridad evaluadora / administrador).
        //  - juridico / sujeto → solo los de su dependencia.
        $soloPropio    = $user->isRol(User::ROL_ENLACE);
        $veTodo        = $user->isAnyRol([User::ROL_REVISORA, User::ROL_ADMIN]);
        $depId         = $user->dependencia_id;
        $filtrarPorDep = (!$soloPropio && !$veTodo && $depId);

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

        // Aplica alcance por rol (común a todas las queries).
        $alcance = function ($query) use ($soloPropio, $filtrarPorDep, $depId, $user) {
            return $query->when($soloPropio, fn ($q) => $q->where('created_by', $user->id))
                         ->when($filtrarPorDep, fn ($q) => $q->where('dependencia_id', $depId));
        };

        // --- Filtro especial: "Mis observaciones" (rol jurídico) ---
        // No filtra registros por estatus, sino las observaciones que hizo el
        // usuario y siguen vivas, con enlace al registro observado.
        if ($filtro === 'mis_observaciones') {
            $rutaPorClase = [
                Tramite::class               => 'tramites.show',
                AccionAgenda::class          => 'agenda.show',
                PropuestaRegulatoria::class  => 'propuestas.show',
            ];
            $rows = \App\Models\Observacion::with('observable')
                ->where('realizada_por', $user->id)
                ->whereIn('estatus', config('flujos.observaciones_vivas'))
                ->latest()
                ->take(20)
                ->get()
                ->map(function ($obs) use ($rutaPorClase) {
                    $ruta = $rutaPorClase[$obs->observable_type] ?? null;
                    return [
                        'folio'   => 'OBS-' . str_pad($obs->id, 3, '0', STR_PAD_LEFT),
                        'nombre'  => $obs->seccion ? ($obs->seccion . ': ' . \Str::limit($obs->texto, 60)) : \Str::limit($obs->texto, 80),
                        'estatus' => $obs->estatus,
                        'fecha'   => $obs->created_at?->format('d/m/Y') ?? '—',
                        'url'     => ($ruta && $obs->observable_id) ? route($ruta, $obs->observable_id) : '#',
                    ];
                });
            return response()->json(['tipo' => $filtro, 'rows' => $rows->values()]);
        }

        // --- Panorama del admin: filtros tipo "{modulo}_{grupo}" ---
        // Ej.: tramites_total, agenda_proceso, propuestas_cierre.
        $panoramaModulos = array_keys(config('flujos.panorama_admin'));
        $partes = explode('_', (string) $filtro);
        $grupoPan = array_pop($partes);          // total | proceso | cierre
        $moduloPan = implode('_', $partes);       // tramites | agenda | propuestas | regulaciones
        if (in_array($moduloPan, $panoramaModulos, true) && in_array($grupoPan, ['total', 'proceso', 'cierre'], true)) {
            $modelo = $this->modeloDe($moduloPan);
            $query  = $modelo::query();

            // 'total' no filtra por estatus; 'proceso'/'cierre' usan el config.
            if ($grupoPan !== 'total') {
                $estatusPan = config("flujos.panorama_admin.{$moduloPan}.{$grupoPan}", []);
                if (!empty($estatusPan)) {
                    $query->whereIn('estatus', $estatusPan);
                }
            }

            $mapeador = match ($moduloPan) {
                'tramites'     => [$filaTramite, ['id','homoclave','nombre_oficial','estatus','updated_at']],
                'agenda'       => [$filaAgenda, ['id','descripcion','estatus','updated_at']],
                'propuestas'   => [$filaPropuesta, ['id','nombre','estatus','updated_at']],
                'regulaciones' => [$filaRegulacion, ['id','nombre','estatus','updated_at']],
            };
            [$fila, $cols] = $mapeador;
            $rows = $query->latest()->take(50)->get($cols)->map($fila);
            return response()->json(['tipo' => $filtro, 'rows' => $rows->values()]);
        }

        // --- Categorías de la Autoridad Revisora (leídas de config/flujos.php) ---
        // "pendientes" se expande a las categorías que lo componen.
        $categoriasValidas = array_keys(config('flujos.categorias'));
        $esCategoria = ($filtro === 'pendientes') || in_array($filtro, $categoriasValidas, true);

        if ($esCategoria) {
            $cats = $filtro === 'pendientes'
                ? config('flujos.pendientes_incluye')
                : [$filtro];

            $tramites = collect();
            $agenda   = collect();
            $regulaciones = collect();
            foreach ($cats as $cat) {
                $qt = $this->queryCategoria('tramites', $cat);
                if ($qt) {
                    $tramites = $tramites->concat(
                        $alcance($qt)->latest()->take(20)
                            ->get(['id','homoclave','nombre_oficial','estatus','updated_at'])->map($filaTramite)
                    );
                }
                $qa = $this->queryCategoria('agenda', $cat);
                if ($qa) {
                    $agenda = $agenda->concat(
                        $alcance($qa)->latest()->take(20)
                            ->get(['id','descripcion','estatus','updated_at'])->map($filaAgenda)
                    );
                }
                $qr = $this->queryCategoria('regulaciones', $cat);
                if ($qr) {
                    $regulaciones = $regulaciones->concat(
                        $alcance($qr)->latest()->take(20)
                            ->get(['id','nombre','estatus','updated_at'])->map($filaRegulacion)
                    );
                }
            }
            $rows = $tramites->concat($agenda)->concat($regulaciones);
            return response()->json(['tipo' => $filtro, 'rows' => $rows->values()]);
        }

        // --- Filtros por módulo (otros roles): estatus simple ---
        // Trámites y agenda comparten vocabulario, así que no hay traducción por módulo.
        $estatusDe = function (string $modulo) use ($filtro): ?array {
            return match ($filtro) {
                'en_revision'   => ['en_observacion', 'en_correccion'],
                'en_correccion' => ['en_correccion'],
                default         => null,
            };
        };
        $aplicarModulo = function ($query, string $modulo) use ($alcance, $estatusDe) {
            $alcance($query);
            $est = $estatusDe($modulo);
            if ($est) $query->whereIn('estatus', $est);
            return $query;
        };

        $rows = match ($tipo) {
            'tramites' => $aplicarModulo(Tramite::query(), 'tramites')
                ->latest()->take(20)->get(['id','homoclave','nombre_oficial','estatus','updated_at'])->map($filaTramite),
            'agenda' => $aplicarModulo(AccionAgenda::query(), 'agenda')
                ->latest()->take(20)->get(['id','descripcion','estatus','updated_at'])->map($filaAgenda),
            'propuestas' => $aplicarModulo(PropuestaRegulatoria::query(), 'propuestas')
                ->latest()->take(20)->get(['id','nombre','estatus','updated_at'])->map($filaPropuesta),
            default => collect(),
        };

        return response()->json(['tipo' => $tipo, 'rows' => $rows->values()]);
    }

    /**
     * Mapea la clave de módulo del config a su clase de modelo.
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
     * Construye la query de un módulo para una categoría, leyendo del config.
     * Aplica los estatus de la categoría y el filtro de observaciones vivas.
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
        $query = $modelo::whereIn('estatus', $def['estatus']);

        // obs_vivas: true = solo con observaciones vivas; false = solo sin ellas;
        // null = no filtra por observaciones.
        if ($def['obs_vivas'] === true) {
            $query->whereHas('observaciones', fn ($q) => $q->whereIn('estatus', $obsVivas));
        } elseif ($def['obs_vivas'] === false) {
            $query->whereDoesntHave('observaciones', fn ($q) => $q->whereIn('estatus', $obsVivas));
        }

        return $query;
    }

    /**
     * Cuenta los registros (trámites + agenda) de una categoría.
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
     * $grupo: 'total' (todos), 'proceso' o 'cierre' (estatus del config).
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
        if (empty($estatus)) {
            return 0;
        }
        return $modelo::whereIn('estatus', $estatus)->count();
    }

}
