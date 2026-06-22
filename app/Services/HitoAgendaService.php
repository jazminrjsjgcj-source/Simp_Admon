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

            // Hito 1: Diagnóstico, ya completado y aprobado al registrar la acción.
            $accion->hitos()->create([
                'orden'             => $orden++,
                'clave'             => $diagnostico['clave'],
                'nombre'            => $diagnostico['nombre'],
                'completado'        => true,
                'fecha_completado'  => now()->toDateString(),
                'completado_por'    => $accion->created_by,
                'estado_aprobacion' => 'aprobado',
                'aprobado_por'      => $accion->created_by,
                'fecha_aprobacion'  => now()->toDateString(),
            ]);

            // Hitos siguientes: pendientes, sin evidencia todavía.
            foreach ($listaEspecif as $hito) {
                $accion->hitos()->create([
                    'orden'             => $orden++,
                    'clave'             => $hito['clave'],
                    'nombre'            => $hito['nombre'],
                    'completado'        => false,
                    'estado_aprobacion' => 'sin_evidencia',
                ]);
            }
        });
    }

    /**
     * #9: el enlace sube la evidencia de un hito y lo deja pendiente de visto
     * bueno. Flujo flexible: se puede subir evidencia de cualquier hito, sin
     * orden. Devuelve true si lo registró.
     *
     * @param string $rutaArchivo  ruta del archivo ya guardado en storage
     * @param string $nombreOriginal  nombre original del archivo (para mostrar)
     */
    public function subirEvidencia(HitoAgenda $hito, string $rutaArchivo, string $nombreOriginal, int $usuarioId): bool
    {
        $hito->update([
            'evidencia_archivo' => $rutaArchivo,
            'evidencia_nombre'  => $nombreOriginal,
            'estado_aprobacion' => 'pendiente',
            'completado'        => false,
            'completado_por'    => $usuarioId,
            'fecha_completado'  => now()->toDateString(),
            // Si venía de un rechazo, se limpia el motivo al volver a subir.
            'motivo_rechazo'    => null,
        ]);

        return true;
    }

    /**
     * #5: la revisora aprueba un hito (debe tener evidencia pendiente). Puede
     * aprobar en cualquier orden. Al aprobar, el hito queda completado.
     */
    public function aprobarHito(HitoAgenda $hito, int $revisoraId): bool
    {
        if ($hito->estado_aprobacion !== 'pendiente') {
            return false;
        }

        $hito->update([
            'estado_aprobacion' => 'aprobado',
            'completado'        => true,
            'aprobado_por'      => $revisoraId,
            'fecha_aprobacion'  => now()->toDateString(),
            'motivo_rechazo'    => null,
        ]);

        return true;
    }

    /**
     * #5: la revisora rechaza un hito con un motivo escrito. El hito vuelve al
     * enlace para que corrija y suba nueva evidencia.
     */
    public function rechazarHito(HitoAgenda $hito, string $motivo, int $revisoraId): bool
    {
        if ($hito->estado_aprobacion !== 'pendiente') {
            return false;
        }

        $hito->update([
            'estado_aprobacion' => 'rechazado',
            'completado'        => false,
            'aprobado_por'      => $revisoraId,
            'fecha_aprobacion'  => now()->toDateString(),
            'motivo_rechazo'    => $motivo,
        ]);

        return true;
    }

    /** Devuelve el primer hito aún no aprobado (referencia para la UI). */
    public function siguientePendiente(AccionAgenda $accion): ?HitoAgenda
    {
        return $accion->hitos()
            ->where('estado_aprobacion', '!=', 'aprobado')
            ->orderBy('orden')
            ->first();
    }

    /** Porcentaje de avance: hitos aprobados / total, redondeado. */
    public function calcularPorcentaje(AccionAgenda $accion): int
    {
        $total = $accion->hitos()->count();
        if ($total === 0) {
            return 0;
        }
        $aprobados = $accion->hitos()->where('estado_aprobacion', 'aprobado')->count();
        return (int) round($aprobados / $total * 100);
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
