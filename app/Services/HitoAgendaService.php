<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\HitoAgenda;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona los hitos de avance de una acción de agenda.
 *
 * Responsabilidades:
 *   - Sembrar los hitos de una acción al registrarla (sembrarHitos).
 *   - Marcar el siguiente hito como completado (marcarSiguiente / marcarHito).
 *   - Calcular el porcentaje de avance (calcularPorcentaje).
 *
 * Los hitos de cada tipo de acción se definen en config/hitos.php. El primero
 * siempre es "Diagnóstico" y queda marcado como completado en el momento de la
 * siembra (el diagnóstico ya se hizo al capturar la acción en la agenda).
 *
 * El avance es lineal: solo se puede marcar el siguiente hito pendiente, no
 * saltarse pasos.
 */
class HitoAgendaService
{
    /**
     * Crea los hitos de una acción según su tipo. Idempotente: si la acción ya
     * tiene hitos, no hace nada (evita duplicar al reenviar o editar).
     */
    public function sembrarHitos(AccionAgenda $accion): void
    {
        if ($accion->hitos()->exists()) {
            return;
        }

        $config       = config('hitos');
        $diagnostico  = $config['diagnostico'];
        $listaEspecif = $this->resolverLista($accion, $config);

        DB::transaction(function () use ($accion, $diagnostico, $listaEspecif) {
            $orden = 1;

            // Hito 1: Diagnóstico, ya completado al registrar la acción.
            $accion->hitos()->create([
                'orden'            => $orden++,
                'clave'            => $diagnostico['clave'],
                'nombre'           => $diagnostico['nombre'],
                'completado'       => true,
                'fecha_completado' => now()->toDateString(),
                'completado_por'   => $accion->created_by,
            ]);

            // Hitos siguientes: pendientes, en orden.
            foreach ($listaEspecif as $hito) {
                $accion->hitos()->create([
                    'orden'      => $orden++,
                    'clave'      => $hito['clave'],
                    'nombre'     => $hito['nombre'],
                    'completado' => false,
                ]);
            }
        });
    }

    /**
     * Marca un hito como completado, pero solo si es el siguiente pendiente
     * (avance lineal). Devuelve true si lo marcó, false si no correspondía.
     */
    public function marcarHito(AccionAgenda $accion, int $hitoId, int $usuarioId): bool
    {
        $siguiente = $this->siguientePendiente($accion);

        // Solo se puede marcar el siguiente hito pendiente.
        if (!$siguiente || $siguiente->id !== $hitoId) {
            return false;
        }

        $siguiente->update([
            'completado'       => true,
            'fecha_completado' => now()->toDateString(),
            'completado_por'   => $usuarioId,
        ]);

        return true;
    }

    /** Devuelve el primer hito pendiente (el único que se puede marcar). */
    public function siguientePendiente(AccionAgenda $accion): ?HitoAgenda
    {
        return $accion->hitos()
            ->where('completado', false)
            ->orderBy('orden')
            ->first();
    }

    /** Porcentaje de avance: hitos completados / total, redondeado. */
    public function calcularPorcentaje(AccionAgenda $accion): int
    {
        $total = $accion->hitos()->count();
        if ($total === 0) {
            return 0;
        }
        $completados = $accion->hitos()->where('completado', true)->count();
        return (int) round($completados / $total * 100);
    }

    /* ----------------------------------------------------------------------
     | Helpers privados
     |----------------------------------------------------------------------*/

    /**
     * Resuelve la lista de hitos específica de la acción.
     *
     * Hoy la acción solo guarda 'tipo' (simplificacion/digitalizacion), no el
     * subtipo concreto. Cuando se implemente el guardado del subtipo (pendiente
     * del Excel), esta función lo leerá de 'tipo_accion' y devolverá la lista
     * específica. Mientras tanto, cae en la lista 'generico'.
     */
    private function resolverLista(AccionAgenda $accion, array $config): array
    {
        $tipo    = $accion->tipo;                 // simplificacion | digitalizacion
        $subtipo = $accion->tipo_accion ?? null;  // ej. reduccion_requisitos (futuro)

        if ($subtipo && isset($config[$tipo][$subtipo])) {
            return $config[$tipo][$subtipo];
        }

        return $config['generico'];
    }
}
