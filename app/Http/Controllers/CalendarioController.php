<?php

namespace App\Http\Controllers;

use App\Services\CalendarioEventoService;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function __construct(private CalendarioEventoService $calendario) {}

    public function index(Request $request)
    {
        $mes    = intval($request->input('mes',  now()->month));
        $anio   = intval($request->input('anio', now()->year));
        $filtro = $request->input('tipo',  'todos');
        $vista  = $request->input('vista', 'mes');

        $eventos = $this->calendario->eventosDelMes($anio, $mes, $filtro);
        $kpis    = $this->calendario->kpisDelMes($anio, $mes);

        return view('screens.calendario.index', compact('eventos', 'kpis', 'mes', 'anio', 'filtro', 'vista'));
    }

    /**
     * Actualiza el porcentaje de avance de un evento del calendario.
     * Si el avance llega a 100%, el estatus se marca como "cumplido" automáticamente.
     */
    public function actualizarAvance(Request $request, \App\Models\CalendarioEvento $evento): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->tienePermiso('calendario.ver')) {
            abort(403, 'No tiene permiso para actualizar avances.');
        }

        $avance = intval($request->input('avance', 0));
        $avance = max(0, min(100, $avance));

        $datos = ['avance' => $avance];
        if ($avance >= 100) {
            $datos['estatus'] = 'cumplido';
        }

        $evento->update($datos);

        return back()->with('success', 'Avance actualizado.');
    }
}
