<?php

namespace App\Observers;

use App\Models\AccionAgenda;
use App\Models\Tramite;
use Illuminate\Support\Facades\Log;

/**
 * Activa las acciones de agenda que estaban esperando a que su trámite se completara.
 *
 * ── Por qué existe ───────────────────────────────────────────────────
 *
 * Desde la agenda se puede registrar un trámite nuevo y, de paso, la acción de mejora
 * sobre él. Pero ese trámite todavía tiene que recorrer su camino (revisión, firma)
 * antes de existir oficialmente, así que la acción se guarda INACTIVA: no aparece en
 * los listados ajenos, ni en el calendario, ni en los indicadores.
 *
 * En cuanto el trámite queda completado, esas acciones dejan de estar en el limbo:
 * este observer las activa solas, sin que nadie tenga que acordarse de hacerlo.
 */
class TramiteCompletadoObserver
{
    /**
     * Se dispara cada vez que un trámite se guarda. Solo hace algo cuando el estatus
     * ACABA de cambiar a completado (no en cada guardado de un trámite que ya lo
     * estaba).
     */
    public function updated(Tramite $tramite): void
    {
        // wasChanged() mira si el campo cambió EN ESTE guardado. Sin esta comprobación
        // se reactivarían las acciones en cada edición de un trámite ya completado.
        if (! $tramite->wasChanged('estatus')) {
            return;
        }

        if ($tramite->estatus !== Tramite::ESTATUS_COMPLETADO) {
            return;
        }

        $activadas = AccionAgenda::where('tramite_id', $tramite->id)
            ->where('activa', false)
            ->update(['activa' => true]);

        if ($activadas > 0) {
            Log::info(
                "Trámite #{$tramite->id} completado: se activaron {$activadas} acción(es) de agenda que lo esperaban."
            );
        }
    }
}
