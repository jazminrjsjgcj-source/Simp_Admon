<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando se intenta registrar una firma activa de un tipo que el
 * registro ya tiene.
 *
 * ── Por qué existe una excepción propia y no un RuntimeException pelado ──
 *
 * La firma duplicada puede llegar por dos caminos distintos:
 *
 *   1. El camino normal: el usuario le da a "Firmar" en un trámite que ya
 *      firmó. FirmaDigitalService lo detecta antes de escribir y avisa.
 *
 *   2. El camino de la carrera: dos peticiones simultáneas (doble clic) pasan
 *      las dos la comprobación y las dos intentan escribir. Ahí es el índice
 *      único de la base el que frena la segunda, y lo hace lanzando una
 *      QueryException genérica de PostgreSQL.
 *
 * Sin esta clase, el controlador tendría que capturar QueryException y mirar el
 * código de error de PostgreSQL para saber si fue una firma duplicada o
 * cualquier otro problema de base de datos. Eso sería meter detalles del motor
 * en el controlador.
 *
 * Con esta clase, el servicio traduce el error técnico a un hecho del negocio, y
 * el controlador solo tiene que decir: "este registro ya estaba firmado".
 */
class FirmaDuplicadaException extends RuntimeException
{
    public static function paraTipo(string $tipo): self
    {
        return new self("Este registro ya tiene una firma activa de tipo '{$tipo}'.");
    }
}
