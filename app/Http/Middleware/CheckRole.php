<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de control por rol.
 *
 * Uso en rutas:
 *   ->middleware('role:enlace')
 *   ->middleware('role:juridico,admin')
 *
 * Consulta primero los roles ACL del usuario y si no tiene asignaciones
 * en la tabla pivote, cae al campo legacy `users.rol`.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if (!$user->tieneAlgunRol($roles)) {
            abort(403, 'No tiene permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
