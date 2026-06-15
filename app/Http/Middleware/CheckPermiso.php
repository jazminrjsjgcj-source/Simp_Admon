<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de control de permisos granular.
 *
 * Uso en rutas:
 *   ->middleware('permiso:tramites.crear')
 *   ->middleware('permiso:tramites.crear,tramites.editar')  // requiere alguno
 */
class CheckPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if (!$user->tieneAlgunPermiso($permisos)) {
            abort(403, 'No tiene los permisos necesarios para esta acción.');
        }

        return $next($request);
    }
}
