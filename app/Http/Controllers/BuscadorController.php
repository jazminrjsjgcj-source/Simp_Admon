<?php

namespace App\Http\Controllers;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use App\Services\BusquedaLogService;
use App\Services\LegalArticleResolverService;
use Illuminate\Http\Request;

/**
 * Controlador del buscador global de PUNTA.
 *
 * Responsabilidad única: recibir la consulta HTTP, delegarla al servicio
 * de búsqueda, y pasar los resultados a la vista. No contiene lógica de
 * búsqueda, scoring ni acceso a BD — todo eso vive en BuscadorService.
 *
 * La bitácora (BusquedaLogService) se llama DESPUÉS de obtener los
 * resultados — si falla, la búsqueda sigue funcionando normalmente.
 *
 * Accesible para TODOS los roles (sin restricción de permiso en la ruta).
 */
class BuscadorController extends Controller
{
    public function __construct(
        private BuscadorService $buscador,
        private BusquedaLogService $log,
        private LegalArticleResolverService $resolutorArticulo,
    ) {}

    /**
     * GET /buscar?q=...&todos=1&regulacion_id[]=42&tipos[]=tramite
     */
    public function index(Request $request)
    {
        $consulta       = $request->input('q', '');
        $forzarCompleto = $request->boolean('todos');

        // regulacion_id puede venir como array de checkboxes (regulacion_id[])
        // o como valor único (compatibilidad hacia atrás).
        $regulacionIds = $request->input('regulacion_id');
        if (is_array($regulacionIds)) {
            $regulacionIds = array_filter(array_map('intval', $regulacionIds));
            $regulacionIds = !empty($regulacionIds) ? $regulacionIds : null;
        } elseif ($regulacionIds) {
            $regulacionIds = [(int) $regulacionIds];
        } else {
            $regulacionIds = null;
        }

        // tipos[] filtra por tipo de fuente.
        $tiposSeleccionados = $request->input('tipos');
        if (is_array($tiposSeleccionados)) {
            $validos = ['articulo', 'regulacion', 'tramite', 'requisito', 'fundamento', 'agenda'];
            $tiposSeleccionados = array_values(array_intersect($tiposSeleccionados, $validos));
            $tiposSeleccionados = !empty($tiposSeleccionados) ? $tiposSeleccionados : null;
        } else {
            $tiposSeleccionados = null;
        }

        $resultados         = collect();
        $respuestaDestacada = null;
        $modo               = 'completo';
        $tiempo             = 0;
        $busquedaLogId      = null;

        if (trim($consulta) !== '') {
            $inicio    = microtime(true);
            $respuesta = $this->buscador->buscar($consulta, $forzarCompleto, $regulacionIds, $tiposSeleccionados);
            $tiempo    = round((microtime(true) - $inicio) * 1000);

            $resultados         = $respuesta['resultados'];
            $respuestaDestacada = $respuesta['respuesta_destacada'];
            $modo               = $respuesta['modo'];

            // ── Capa 1: registrar en bitácora (después de obtener resultados) ──
            $busquedaLogId = $this->log->registrarBusqueda(
                consulta:        $consulta,
                regulacionIds:   $regulacionIds,
                tipos:           $tiposSeleccionados,
                modo:            $modo,
                totalResultados: $resultados->count(),
                tiempoMs:        (int) $tiempo,
                tieneDestacada:  $respuestaDestacada !== null,
            );
        }

        $regulaciones = Regulacion::query()
            ->where('estatus', Regulacion::ESTATUS_VIGENTE)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $regulacionesFiltro = $regulacionIds
            ? $regulaciones->whereIn('id', $regulacionIds)->values()
            : null;

        return view('screens.buscar', compact(
            'consulta',
            'resultados',
            'respuestaDestacada',
            'modo',
            'tiempo',
            'regulaciones',
            'regulacionIds',
            'regulacionesFiltro',
            'tiposSeleccionados',
            'busquedaLogId',
        ));
    }

    /**
     * POST /buscar/clic
     *
     * Registra que el usuario hizo clic en un resultado.
     * Se llama vía fetch() desde el frontend (fire-and-forget).
     */
    public function registrarClic(Request $request)
    {
        $request->validate([
            'log_id'     => 'required|integer',
            'tipo'       => 'required|string|max:20',
            'id'         => 'required|integer',
        ]);

        $this->log->registrarClic(
            logId:          $request->integer('log_id'),
            tipoResultado:  $request->input('tipo'),
            resultadoId:    $request->integer('id'),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /buscar/feedback
     *
     * Registra el voto del usuario sobre un resultado: le sirvió o no le sirvió.
     *
     * OJO: el log_id viene del NAVEGADOR, y el validate() de abajo solo comprueba que sea un
     * ENTERO. No comprueba de quién es esa búsqueda.
     *
     * El candado de propiedad está en BusquedaLogService, que exige que la búsqueda sea del
     * usuario autenticado. Sin él, cualquiera podía votar sobre las búsquedas de todo el
     * Ayuntamiento con un bucle de fetch() — y esos votos son las "training labels" de un futuro
     * modelo de ranking.
     */
    public function registrarFeedback(Request $request)
    {
        $request->validate([
            'log_id'   => 'required|integer',
            'consulta' => 'required|string|max:500',
            'tipo'     => 'required|string|max:20',
            'id'       => 'required|integer',
            'titulo'   => 'nullable|string|max:500',
            'util'     => 'required|boolean',
        ]);

        $ok = $this->log->registrarFeedback(
            busquedaLogId:  $request->integer('log_id'),
            consulta:       $request->input('consulta'),
            tipoResultado:  $request->input('tipo'),
            resultadoId:    $request->integer('id'),
            titulo:         $request->input('titulo'),
            util:           $request->boolean('util'),
        );

        return response()->json(['ok' => $ok]);
    }

    /**
     * GET /buscar/detalle/{tipo}/{id}
     *
     * Devuelve en JSON el contenido detallado de cualquier resultado del
     * buscador, para mostrarlo en el modal de lectura sin navegar fuera.
     * Cada tipo devuelve los campos más relevantes para entender POR QUÉ
     * apareció en la búsqueda.
     */
    public function obtenerDetalle(string $tipo, int $id)
    {
        return match ($tipo) {
            'articulo'   => $this->detalleArticulo($id),
            'regulacion' => $this->detalleRegulacion($id),
            'tramite'    => $this->detalleTramite($id),
            'requisito'  => $this->detalleRequisito($id),
            'fundamento' => $this->detalleFundamento($id),
            'agenda'     => $this->detalleAgenda($id),
            default      => response()->json(['error' => 'Tipo no reconocido'], 404),
        };
    }

    private function detalleArticulo(int $id)
    {
        $nodo = RegulacionNodo::findOrFail($id);
        $articulo = $this->resolutorArticulo->resolverArticuloPadre($nodo) ?? $nodo;
        $completo = $this->resolutorArticulo->obtenerArticuloCompleto($articulo);

        return response()->json([
            'titulo'    => $articulo->etiquetaTipo() . ($articulo->numero ? ' ' . $articulo->numero : ''),
            'subtitulo' => $articulo->regulacion->nombre ?? '',
            'url'       => route('regulaciones.show', $articulo->regulacion_id),
            'campos'    => array_filter([
                $articulo->texto ? ['label' => 'Texto del artículo', 'valor' => $articulo->texto] : null,
            ]),
            'hijos' => $completo['hijos']->map(fn (RegulacionNodo $h) => [
                'tipo'   => $h->etiquetaTipo(),
                'numero' => $h->numero,
                'texto'  => $h->texto,
            ])->values(),
        ]);
    }

    private function detalleRegulacion(int $id)
    {
        $r = Regulacion::findOrFail($id);
        return response()->json([
            'titulo'    => $r->nombre,
            'subtitulo' => $r->tipo . ($r->materia ? ' · ' . $r->materia : ''),
            'url'       => route('regulaciones.show', $r->id),
            'campos'    => array_filter([
                $r->objetivo  ? ['label' => 'Objetivo',        'valor' => $r->objetivo]  : null,
                $r->resumen   ? ['label' => 'Resumen',         'valor' => $r->resumen]   : null,
                $r->fundamento_juridico ? ['label' => 'Fundamento de expedición', 'valor' => $r->fundamento_juridico] : null,
                $r->palabras_clave      ? ['label' => 'Palabras clave',           'valor' => $r->palabras_clave]      : null,
            ]),
            'tags' => array_filter([
                $r->tipo,
                $r->materia,
                $r->estatus ? ucfirst($r->estatus) : null,
                $r->fecha_publicacion ? 'Publicada: ' . $r->fecha_publicacion : null,
            ]),
        ]);
    }

    private function detalleTramite(int $id)
    {
        $t = \App\Models\Tramite::with(['dependencia', 'tipoTramite'])->findOrFail($id);
        $esServicio = $t->naturaleza === 'servicio';
        $plazo = $t->plazo_resolucion_cantidad
            ? "{$t->plazo_resolucion_cantidad} {$t->plazo_resolucion_unidad}"
            : null;

        return response()->json([
            'titulo'    => $t->nombre_oficial,
            'subtitulo' => ($esServicio ? 'Servicio' : 'Trámite')
                . ($t->dependencia ? ' · ' . $t->dependencia->nombre : ''),
            'url'       => route('tramites.show', $t->id),
            'campos'    => array_filter([
                $t->objetivo            ? ['label' => 'Objetivo',            'valor' => $t->objetivo]            : null,
                $t->poblacion_objetivo  ? ['label' => 'Población objetivo',  'valor' => $t->poblacion_objetivo]  : null,
                $plazo                  ? ['label' => 'Plazo de resolución', 'valor' => $plazo]                  : null,
                $t->cbu_unitario        ? ['label' => 'Costo burocrático',   'valor' => '$' . number_format($t->cbu_unitario, 2)] : null,
            ]),
            'tags' => array_filter([
                $esServicio ? ($t->tipo_servicio ?? 'Servicio') : ($t->tipoTramite->nombre ?? 'Trámite'),
                $t->homoclave,
                $t->estatus ? ucfirst(str_replace('_', ' ', $t->estatus)) : null,
            ]),
        ]);
    }

    private function detalleRequisito(int $id)
    {
        $rq = \App\Models\Requisito::with('tramite')->findOrFail($id);
        $costo = '';
        if ($rq->tiene_costo && $rq->costo_requisito > 0) {
            $unidad = $rq->costo_unidad ?? 'PESOS';
            $costo = $unidad === 'UMA'
                ? number_format($rq->costo_requisito, 2) . ' UMA'
                : '$' . number_format($rq->costo_requisito, 2);
        }
        $tiempo = $rq->tiempo_homologado_hrs
            ? round($rq->tiempo_homologado_hrs, 1) . ' horas'
            : null;
        $nat = $rq->tramite && $rq->tramite->naturaleza === 'servicio' ? 'Servicio' : 'Trámite';

        return response()->json([
            'titulo'    => $rq->nombre,
            'subtitulo' => "Requisito del {$nat}: " . ($rq->tramite->nombre_oficial ?? ''),
            'url'       => route('tramites.show', $rq->tramite_id) . '#requisitos',
            'campos'    => array_filter([
                $tiempo ? ['label' => 'Tiempo estimado para obtenerlo', 'valor' => $tiempo]           : null,
                $costo  ? ['label' => 'Costo del requisito',            'valor' => $costo]            : null,
                !$rq->tiene_costo ? ['label' => 'Costo',               'valor' => 'Sin costo']       : null,
                $rq->observaciones ? ['label' => 'Observaciones',      'valor' => $rq->observaciones] : null,
            ]),
            'tags' => array_filter([
                $rq->tipo_presentacion ? ucfirst($rq->tipo_presentacion) : null,
                $rq->original ? 'Original' : null,
                $rq->copia ? 'Copia' : null,
            ]),
        ]);
    }

    private function detalleFundamento(int $id)
    {
        $fj = \App\Models\FundamentoJuridico::with(['tramite', 'regulacion'])->findOrFail($id);

        return response()->json([
            'titulo'    => $fj->normativa_nombre ?: ($fj->regulacion->nombre ?? 'Fundamento jurídico'),
            'subtitulo' => 'Fundamenta: ' . ($fj->tramite->nombre_oficial ?? ''),
            'url'       => $fj->regulacion_id
                ? route('regulaciones.show', $fj->regulacion_id)
                : route('tramites.show', $fj->tramite_id) . '#fundamento',
            'campos'    => array_filter([
                $fj->articulo_fraccion ? ['label' => 'Artículo / fracción', 'valor' => $fj->articulo_fraccion] : null,
                $fj->resumen           ? ['label' => 'Resumen',             'valor' => $fj->resumen]           : null,
                $fj->tipo_normativa    ? ['label' => 'Tipo de normativa',   'valor' => $fj->tipo_normativa]    : null,
            ]),
            'tags' => array_filter([
                $fj->tipo_normativa,
                $fj->regulacion ? 'Vinculada al catálogo' : 'Sin vínculo al catálogo',
            ]),
        ]);
    }

    private function detalleAgenda(int $id)
    {
        $aa = \App\Models\AccionAgenda::with(['tramite', 'dependencia'])->findOrFail($id);
        $tipoLabel = $aa->tipo === 'simplificacion' ? 'Simplificación' : 'Digitalización';

        return response()->json([
            'titulo'    => $aa->folio ?? "Acción de agenda #{$aa->id}",
            'subtitulo' => "Acción de {$tipoLabel}"
                . ($aa->dependencia ? ' · ' . $aa->dependencia->nombre : ''),
            'url'       => route('tramites.show', $aa->tramite_id),
            'campos'    => array_filter([
                $aa->descripcion      ? ['label' => 'Descripción',        'valor' => $aa->descripcion]      : null,
                $aa->meta             ? ['label' => 'Meta',               'valor' => $aa->meta]             : null,
                $aa->indicador        ? ['label' => 'Indicador',          'valor' => $aa->indicador]        : null,
                $aa->fecha_compromiso ? ['label' => 'Fecha compromiso',   'valor' => $aa->fecha_compromiso->format('d/m/Y')] : null,
                $aa->tramite          ? ['label' => 'Trámite vinculado',  'valor' => $aa->tramite->nombre_oficial] : null,
            ]),
            'tags' => array_filter([
                $tipoLabel,
                $aa->estatus ? ucfirst(str_replace('_', ' ', $aa->estatus)) : null,
            ]),
        ]);
    }

    /**
     * GET /buscar/articulo/{nodo}
     */
    public function obtenerArticulo(int $nodo)
    {
        $nodoEncontrado = RegulacionNodo::findOrFail($nodo);

        $articulo = $this->resolutorArticulo->resolverArticuloPadre($nodoEncontrado) ?? $nodoEncontrado;
        $completo = $this->resolutorArticulo->obtenerArticuloCompleto($articulo);

        return response()->json([
            'regulacion_id'     => $articulo->regulacion_id,
            'regulacion_nombre' => $articulo->regulacion->nombre,
            'tipo'              => $articulo->etiquetaTipo(),
            'numero'            => $articulo->numero,
            'texto'             => $articulo->texto,
            'hijos'             => $completo['hijos']->map(fn (RegulacionNodo $h) => [
                'tipo'   => $h->etiquetaTipo(),
                'numero' => $h->numero,
                'texto'  => $h->texto,
            ])->values(),
        ]);
    }
}
