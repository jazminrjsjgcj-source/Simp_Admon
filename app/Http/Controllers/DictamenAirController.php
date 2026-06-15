<?php

namespace App\Http\Controllers;

use App\Models\AnalisisImpactoRegulatorio;
use App\Models\ExencionAir;
use Illuminate\Http\Request;

/**
 * Bandeja de dictámenes AIR para la revisora.
 *
 * El AIR y las exenciones se gestionan dentro del detalle de cada
 * propuesta regulatoria, pero la revisora necesita una vista única
 * que reúna TODO lo que está pendiente de su dictamen, sin tener que
 * entrar propuesta por propuesta.
 *
 * Esta bandeja muestra dos tipos de pendientes:
 *  - AIR en estatus 'enviado'          → esperando dictamen favorable/no favorable.
 *  - Exenciones en estatus 'solicitada' → esperando aprobación/rechazo.
 *
 * Solo la revisora y el admin (que tienen el permiso
 * 'agenda_regulatoria.aprobar') pueden acceder.
 */
class DictamenAirController extends Controller
{
    /**
     * Lista todos los AIR y exenciones pendientes de dictamen.
     *
     * @param  Request  $request  Petición; se usa para verificar el permiso del usuario.
     * @return \Illuminate\View\View  Vista de la bandeja con ambos listados.
     */
    public function index(Request $request)
    {
        if (!$request->user()->tienePermiso('agenda_regulatoria.aprobar')) {
            abort(403, 'No tiene permiso para dictaminar propuestas regulatorias.');
        }

        // AIR enviados, esperando dictamen. Se cargan la propuesta y su
        // dependencia para evitar consultas N+1 al pintar la tabla.
        $airsPendientes = AnalisisImpactoRegulatorio::with(['propuesta.dependencia'])
            ->where('estatus', AnalisisImpactoRegulatorio::ESTATUS_ENVIADO)
            ->latest()
            ->get();

        // Exenciones solicitadas, esperando resolución.
        $exencionesPendientes = ExencionAir::with(['propuesta.dependencia'])
            ->where('estatus', ExencionAir::ESTATUS_SOLICITADA)
            ->latest()
            ->get();

        return view('screens.dictamenes-air.index', compact(
            'airsPendientes',
            'exencionesPendientes'
        ));
    }
}
