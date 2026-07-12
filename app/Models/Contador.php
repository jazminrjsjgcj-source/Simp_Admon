<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Entrega números de serie únicos y crecientes.
 *
 * ── Para qué sirve ────────────────────────────────────────────────────
 *
 * Cuando el sistema necesita "el siguiente número" de algo (el consecutivo de la
 * homoclave de un trámite, el consecutivo del folio de una acción de agenda), lo
 * pide aquí. Nadie más vuelve a calcularlo por su cuenta.
 *
 * ── Por qué existe ────────────────────────────────────────────────────
 *
 * Antes, cada quien averiguaba el siguiente número leyendo la tabla y sumando 1.
 * Eso tiene un problema que solo aparece cuando el sistema se usa de verdad: si
 * dos personas dan de alta un trámite en el mismo instante, las dos leen el mismo
 * máximo, las dos calculan el mismo número, y la segunda choca contra el índice
 * único. Para el usuario eso es un error 500 después de llenar el formulario.
 *
 * Aquí no se AVERIGUA el número: se PIDE. Y mientras se entrega, la fila del
 * contador queda bloqueada, así que el segundo en llegar espera su turno y recibe
 * un número distinto. Nunca dos iguales.
 *
 * ── Cómo se usa ───────────────────────────────────────────────────────
 *
 *   Contador::siguiente('tramite.homoclave');          // → 42
 *   Contador::siguiente('folio:LPZ-SIM-DGGD-2026-');   // → 1
 *
 * Cada clave es una serie independiente.
 *
 * ── Sobre las transacciones ───────────────────────────────────────────
 *
 * El bloqueo de una fila solo dura mientras hay una transacción abierta. Por eso
 * siguiente() abre la suya. Y funciona en los dos escenarios posibles:
 *
 *   - Si NO hay ninguna transacción en curso (por ejemplo, RegulacionController
 *     asignando un folio), esta abre una, entrega el número y la cierra.
 *
 *   - Si YA hay una en curso (por ejemplo, TramiteService::crear(), que envuelve
 *     todo el alta), Laravel crea un punto de guardado dentro de ella. El bloqueo
 *     se mantiene hasta que la transacción de fuera termine, que es lo correcto.
 *
 * ── Sobre los huecos en la serie ──────────────────────────────────────
 *
 * Si se pide un número y después algo falla, ese número se pierde: la serie salta
 * del 41 al 43. Es un compromiso deliberado. Un hueco en la numeración es un
 * detalle cosmético; dos documentos oficiales con el mismo identificador es un
 * problema legal. Se prefiere el hueco.
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
