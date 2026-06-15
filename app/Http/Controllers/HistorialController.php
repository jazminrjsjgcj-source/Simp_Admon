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
            ->paginate(30);

        return view('screens.historial', [
            'movimientos' => $movimientos,
            'tipo'        => $tipo,
            'id'          => $id,
        ]);
    }
}
