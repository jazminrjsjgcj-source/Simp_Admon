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
}
