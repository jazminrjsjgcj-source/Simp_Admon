<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para gestionar eventos del calendario asociados a registros
 * polimórficos (acciones de agenda, propuestas regulatorias, etc.).
 */
class CalendarioEventoService
{
    public function crear(Model $eventable, array $datos): void
    {
        if (empty($datos['fecha'])) {
            return;
        }

        DB::table('calendario_eventos')->insert([
            'tipo'             => in_array($datos['tipo'] ?? '', ['simplificacion', 'digitalizacion', 'regulatoria', 'ambas'])
                                    ? $datos['tipo']
                                    : 'simplificacion',
            'titulo'           => mb_substr($datos['titulo'] ?? '', 0, 2000),
            'fecha'            => $datos['fecha'],
            'estatus'          => $datos['estatus']        ?? 'pendiente',
            'avance'           => $datos['avance']         ?? 0,
            'responsable'      => $datos['responsable']    ?? null,
            'dependencia_id'   => $datos['dependencia_id'] ?? null,
            'eventable_type'   => get_class($eventable),
            'eventable_id'     => $eventable->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function actualizar(Model $eventable, array $cambios): void
    {
        if (empty($cambios)) {
            return;
        }

        DB::table('calendario_eventos')
            ->where('eventable_type', get_class($eventable))
            ->where('eventable_id',   $eventable->id)
            ->update(array_merge($cambios, ['updated_at' => now()]));
    }

    public function eliminar(Model $eventable): void
    {
        DB::table('calendario_eventos')
            ->where('eventable_type', get_class($eventable))
            ->where('eventable_id',   $eventable->id)
            ->delete();
    }

    /**
     * Mapa de clase Eloquent → nombre de ruta para construir URLs de detalle.
     * Se usa para hacer clickeables los eventos del calendario (#24).
     */
    private const RUTAS_DETALLE = [
        'App\\Models\\AccionAgenda'          => 'agenda.show',
        'App\\Models\\PropuestaRegulatoria'  => 'propuestas.show',
        'App\\Models\\Tramite'               => 'tramites.show',
        'App\\Models\\Regulacion'            => 'regulaciones.show',
    ];

    /**
     * Devuelve los eventos del mes filtrados por tipo opcional.
     * Cada evento incluye una propiedad `url` con el enlace al detalle
     * del registro asociado (acción de agenda, propuesta, trámite, etc.).
     */
    public function eventosDelMes(int $anio, int $mes, ?string $tipo = null)
    {
        $eventos = DB::table('calendario_eventos')
            ->when($tipo && $tipo !== 'todos', function ($q) use ($tipo) {
                // Una acción 'ambas' es de simplificación y de digitalización a la
                // vez, así que aparece en los dos filtros. Regulatoria queda exacta.
                if ($tipo === 'simplificacion' || $tipo === 'digitalizacion') {
                    $q->whereIn('tipo', [$tipo, 'ambas']);
                } else {
                    $q->where('tipo', $tipo);
                }
            })
            ->whereYear('fecha',  $anio)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha')
            ->get();

        // Agregar URL de detalle a cada evento, basada en su tipo polimórfico.
        $eventos->transform(function ($ev) {
            $ruta = self::RUTAS_DETALLE[$ev->eventable_type] ?? null;
            $ev->url = $ruta ? route($ruta, $ev->eventable_id) : null;
            return $ev;
        });

        return $eventos;
    }

    /**
     * Devuelve KPIs del mes agrupados por tipo y eventos cumplidos.
     */
    public function kpisDelMes(int $anio, int $mes): array
    {
        $baseQuery = fn () => DB::table('calendario_eventos')
            ->whereYear('fecha',  $anio)
            ->whereMonth('fecha', $mes);

        return [
            // 'ambas' suma en ambos contadores, igual que en el filtro del calendario.
            'sim'       => $baseQuery()->whereIn('tipo', ['simplificacion', 'ambas'])->count(),
            'dig'       => $baseQuery()->whereIn('tipo', ['digitalizacion', 'ambas'])->count(),
            'reg'       => $baseQuery()->where('tipo',    'regulatoria')->count(),
            'cumplidos' => $baseQuery()->where('estatus', 'cumplido')->count(),
        ];
    }
}