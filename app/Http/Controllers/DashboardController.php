<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DashboardService;

/**
 * Dashboard principal del sistema.
 *
 * Este controlador es deliberadamente delgado: toda la lógica (KPIs,
 * pendientes, panorama, actividad general y los filtros AJAX) vive en
 * App\Services\DashboardService, que es la única fuente de verdad. Aquí solo
 * se resuelve el usuario actual, se delega al service y se entrega la vista o
 * la respuesta JSON.
 *
 * Antes existía una copia completa de la lógica duplicada dentro de este
 * controlador (el service quedó sin conectar tras un refactor a medias). Esa
 * duplicación se eliminó: ahora el controller usa el service y no hay dos
 * versiones que mantener.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
    ) {}

    /**
     * Pantalla principal del dashboard. Arma todos los datos a partir del
     * rol del usuario y los pasa a la vista.
     */
    public function index()
    {
        $user  = auth()->user();
        $rol   = $user->rolEfectivo();
        $datos = $this->dashboard->datosVista($user, $rol);

        return view('screens.dashboard', $datos);
    }

    /**
     * Filtros de la tabla inferior del dashboard (AJAX). Devuelve JSON con los
     * registros que coinciden con el tipo/filtro elegido. El alcance por
     * dependencia lo aplica el service.
     */
    public function filtrar(Request $request)
    {
        $user   = auth()->user();
        $tipo   = (string) $request->input('tipo');
        $filtro = (string) $request->input('filtro');

        return response()->json(
            $this->dashboard->filtrar($user, $tipo, $filtro)
        );
    }
}
