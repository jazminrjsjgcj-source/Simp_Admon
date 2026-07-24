<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registro de tours guiados completados.
 *
 * Lo llama tour.js por fetch() cuando el usuario pulsa "Terminar" en el último
 * paso. No lo llama al salir a medias, a propósito: ver la migración de
 * tours_vistos.
 */
class TourController extends Controller
{
    /**
     * Marca el tour como visto por el usuario en sesión.
     *
     * Devuelve JSON porque quien llama es un fetch(), no un formulario: un
     * redirect aquí solo generaría una petición extra que nadie lee.
     */
    public function completado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tour' => ['required', 'string', 'max:120'],
        ]);

        /**
         * Se valida contra config/tours.php y no solo contra el tipo.
         *
         * Sin esta comprobación, cualquiera con sesión podría llenar la tabla con
         * cadenas inventadas mandando peticiones a mano. No es un agujero grave
         * —solo ensucia una tabla propia—, pero es basura que después habría que
         * limpiar sin saber de dónde salió.
         */
        if (!array_key_exists($datos['tour'], config('tours', []))) {
            return response()->json(['ok' => false, 'motivo' => 'tour desconocido'], 422);
        }

        /**
         * updateOrInsert y no insert: el usuario puede repetir el recorrido con el
         * botón "¿Cómo funciona esto?" tantas veces como quiera, y cada vez que
         * llegue al final se avisará de nuevo. Con insert, la segunda vuelta
         * reventaría contra el índice único.
         */
        DB::table('tours_vistos')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'tour'    => $datos['tour'],
            ],
            [
                'completado_en' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
