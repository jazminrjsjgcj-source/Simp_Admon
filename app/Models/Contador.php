<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Entrega números de serie únicos y crecientes.
 *
 * Cuando el sistema necesita "el siguiente número" de algo —el consecutivo de una
 * homoclave, el de un folio— lo pide aquí en vez de calcularlo leyendo la tabla y
 * sumando uno. La diferencia importa en concurrencia: si dos personas dan de alta a
 * la vez, ambas leerían el mismo máximo y la segunda chocaría contra el índice
 * único. Aquí la fila del contador se bloquea mientras se entrega el número, así que
 * el segundo espera su turno y recibe otro.
 *
 *   Contador::siguiente('tramite.homoclave');   // cada clave es una serie aparte
 *
 * El bloqueo solo dura mientras hay una transacción abierta, por eso siguiente()
 * abre la suya. Si ya existe una en curso, Laravel crea un punto de guardado dentro
 * y el bloqueo se mantiene hasta que termine la de fuera, que es lo correcto.
 *
 * Si se pide un número y luego algo falla, ese número se pierde y la serie salta.
 * Es deliberado: un hueco en la numeración es cosmético, dos documentos oficiales
 * con el mismo identificador es un problema legal.
 */
class Contador extends Model
{
    protected $table = 'contadores';

    protected $fillable = ['clave', 'valor'];

    protected $casts = ['valor' => 'integer'];

    /** Clave de la serie global de homoclaves de trámites y servicios. */
    public const HOMOCLAVE_TRAMITE = 'tramite.homoclave';

    /**
     * Entrega el siguiente número de una serie y lo reserva.
     *
     * Dos llamadas simultáneas con la misma clave NUNCA devuelven lo mismo: la
     * segunda espera a que la primera termine.
     *
     * @param  string $clave  Identificador de la serie. Ej.: 'tramite.homoclave'.
     * @return int            El número entregado (1 la primera vez).
     */
    public static function siguiente(string $clave): int
    {
        return DB::transaction(function () use ($clave) {

            // 1. Asegurar que la fila de esta serie existe.
            //
            //    Se usa insertOrIgnore y no firstOrCreate porque dos peticiones
            //    simultáneas podrían intentar crear la misma fila a la vez. Con
            //    firstOrCreate, la segunda lanzaría una excepción de clave duplicada
            //    y —en PostgreSQL— abortaría toda la transacción. insertOrIgnore no
            //    se queja: si la fila ya está, no hace nada.
            DB::table('contadores')->insertOrIgnore([
                'clave'      => $clave,
                'valor'      => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Bloquear la fila. A partir de aquí, cualquier otra petición que
            //    pida un número de ESTA MISMA serie se queda esperando. Las de
            //    otras series siguen su camino sin enterarse.
            $valorActual = (int) DB::table('contadores')
                ->where('clave', $clave)
                ->lockForUpdate()
                ->value('valor');

            $siguiente = $valorActual + 1;

            // 3. Guardar el número entregado y soltar el bloqueo (al cerrar la
            //    transacción). El siguiente en la fila leerá ya el valor nuevo.
            DB::table('contadores')
                ->where('clave', $clave)
                ->update(['valor' => $siguiente, 'updated_at' => now()]);

            return $siguiente;
        });
    }
}
