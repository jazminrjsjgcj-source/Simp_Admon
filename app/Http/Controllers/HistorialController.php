<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Muestra el historial (bitácora) de un registro específico.
 *
 * La bitácora ya registra automáticamente cada acción (crear, editar,
 * aprobar...) sobre los recursos del sistema, con quién la hizo, cuándo
 * y qué cambió (antes -> después). Este controlador toma esos registros
 * y los muestra filtrados a UN recurso concreto, para que desde el detalle
 * de un trámite, propuesta, etc., se pueda abrir su historia completa.
 *
 * Es genérico: funciona para cualquier modelo auditado. Recibe el tipo
 * (nombre corto del modelo) y el id del registro.
 */
class HistorialController extends Controller
{
    /**
     * Mapa de tipos cortos (los que vienen en la URL) a la clase real
     * del modelo, tal como se guarda en bitacora.auditable_type.
     */
    private const TIPOS = [
        'tramite'    => \App\Models\Tramite::class,
        'agenda'     => \App\Models\AccionAgenda::class,
        'propuesta'  => \App\Models\PropuestaRegulatoria::class,
        'regulacion' => \App\Models\Regulacion::class,
        'air'        => \App\Models\AnalisisImpactoRegulatorio::class,
    ];

    /**
     * Lista el historial de un registro.
     *
     * @param  string  $tipo  Tipo corto del recurso (tramite, propuesta...).
     * @param  int     $id    ID del registro.
     */
    public function index(string $tipo, int $id)
    {
        if (!isset(self::TIPOS[$tipo])) {
            abort(404, 'Tipo de registro no válido.');
        }

        $claseModelo = self::TIPOS[$tipo];

        // Trae los movimientos de la bitácora de este registro, del más
        // reciente al más antiguo, con el nombre de quien hizo cada acción.
        $movimientos = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->where('bitacora.auditable_type', $claseModelo)
            ->where('bitacora.auditable_id', $id)
            ->orderByDesc('bitacora.created_at')
            ->select(
                'bitacora.accion',
                'bitacora.tipo',
                'bitacora.detalle',
                'bitacora.ip_address',
                'bitacora.created_at',
                'users.name as usuario_nombre'
            )
            ->paginate(10);

        return view('screens.historial', [
            'movimientos' => $movimientos,
            'tipo'        => $tipo,
            'id'          => $id,
        ]);
    }

    /**
     * Devuelve el historial de un registro en JSON, paginado, para el modal
     * "Ver historial completo" que se abre desde el timeline del detalle.
     *
     * El modal carga los datos por AJAX (sin recargar la página) y permite
     * elegir cuántos movimientos ver por página. Solo se aceptan los tamaños
     * 5, 10 y 100 para evitar que se pida una página arbitrariamente grande.
     *
     * @param  string  $tipo  Tipo corto del recurso (tramite, propuesta...).
     * @param  int     $id    ID del registro.
     */
    public function json(Request $request, string $tipo, int $id)
    {
        if (!isset(self::TIPOS[$tipo])) {
            abort(404, 'Tipo de registro no válido.');
        }

        $claseModelo = self::TIPOS[$tipo];

        // Tamaño de página: solo se permiten 5, 10 o 100. Cualquier otro valor
        // cae al predeterminado de 10.
        $porPagina = (int) $request->input('por_pagina', 10);
        if (!in_array($porPagina, [5, 10, 100], true)) {
            $porPagina = 10;
        }

        $movimientos = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->where('bitacora.auditable_type', $claseModelo)
            ->where('bitacora.auditable_id', $id)
            ->orderByDesc('bitacora.created_at')
            ->select(
                'bitacora.accion',
                'bitacora.tipo',
                'bitacora.detalle',
                'bitacora.created_at',
                'users.name as usuario_nombre'
            )
            ->paginate($porPagina);

        // Se transforma cada fila a un arreglo plano y legible para el front:
        // fecha formateada, acción, autor y los cambios ya separados.
        $filas = collect($movimientos->items())->map(function ($ev) {
            return [
                'fecha'   => \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y H:i'),
                'accion'  => $ev->accion,
                'tipo'    => $ev->tipo,
                'usuario' => $ev->usuario_nombre ?? 'Sistema',
                'cambios' => $ev->detalle ? explode(' | ', $ev->detalle) : [],
            ];
        });

        return response()->json([
            'filas'         => $filas,
            'pagina_actual' => $movimientos->currentPage(),
            'ultima_pagina' => $movimientos->lastPage(),
            'total'         => $movimientos->total(),
            'por_pagina'    => $porPagina,
        ]);
    }
}
