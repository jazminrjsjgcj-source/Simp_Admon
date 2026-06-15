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
            'tipo'             => $datos['tipo']           ?? 'simplificacion',
            'titulo'           => $datos['titulo']         ?? '',
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
     * Devuelve los eventos del mes filtrados por tipo opcional.
     */
    public function eventosDelMes(int $anio, int $mes, ?string $tipo = null)
    {
        return DB::table('calendario_eventos')
            ->when($tipo && $tipo !== 'todos', fn ($q) => $q->where('tipo', $tipo))
            ->whereYear('fecha',  $anio)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha')
            ->get();
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
            'sim'       => $baseQuery()->where('tipo',    'simplificacion')->count(),
            'dig'       => $baseQuery()->where('tipo',    'digitalizacion')->count(),
            'reg'       => $baseQuery()->where('tipo',    'regulatoria')->count(),
            'cumplidos' => $baseQuery()->where('estatus', 'cumplido')->count(),
        ];
    }
}
