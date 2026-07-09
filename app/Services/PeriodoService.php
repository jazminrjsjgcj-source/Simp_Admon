<?php

namespace App\Services;

use App\Models\Periodo;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de los periodos (agenda SyD y agenda regulatoria).
 *
 * Concentra la regla central del módulo: solo puede haber UN periodo activo
 * por tipo. Antes esta transacción estaba copiada en tres métodos distintos
 * del AdminController (crear, actualizar y activar); aquí vive una sola vez.
 */
class PeriodoService
{
    /**
     * Crea un periodo. Si nace activo, cierra los demás activos de su tipo
     * dentro de la misma transacción para mantener la regla "1 activo por tipo".
     *
     * @param  array  $datos  Campos ya validados del formulario.
     * @param  string $tipo   'agenda_syd' o 'agenda_regulatoria'.
     */
    public function crear(array $datos, string $tipo): Periodo
    {
        return DB::transaction(function () use ($datos, $tipo) {
            if (($datos['estatus'] ?? null) === 'activo') {
                $this->cerrarOtrosActivos($tipo);
            }

            return Periodo::create([
                'nombre'       => $datos['nombre'],
                'tipo'         => $tipo,
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin'    => $datos['fecha_fin'],
                'estatus'      => $datos['estatus'] ?? 'proximo',
                'descripcion'  => $datos['descripcion'] ?? null,
                'created_by'   => auth()->id(),
            ]);
        });
    }

    /**
     * Actualiza un periodo. Si queda activo, cierra los demás activos de su
     * tipo (excepto él mismo) dentro de la misma transacción.
     *
     * @param  array $datos  Campos ya validados del formulario.
     */
    public function actualizar(Periodo $periodo, array $datos): Periodo
    {
        return DB::transaction(function () use ($periodo, $datos) {
            if (($datos['estatus'] ?? null) === 'activo') {
                $this->cerrarOtrosActivos($periodo->tipo, $periodo->id);
            }

            $periodo->update([
                'nombre'       => $datos['nombre'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin'    => $datos['fecha_fin'],
                'estatus'      => $datos['estatus'] ?? 'proximo',
                'descripcion'  => $datos['descripcion'] ?? null,
            ]);

            return $periodo;
        });
    }

    /**
     * Marca un periodo como activo y cierra los demás activos de su tipo
     * dentro de la misma transacción.
     */
    public function activar(Periodo $periodo): Periodo
    {
        return DB::transaction(function () use ($periodo) {
            $this->cerrarOtrosActivos($periodo->tipo, $periodo->id);
            $periodo->update(['estatus' => 'activo']);

            return $periodo;
        });
    }

    /**
     * Regla central del módulo: cierra todos los periodos activos de un tipo,
     * opcionalmente excluyendo uno (el que se está creando/activando).
     *
     * No abre transacción propia: se llama siempre desde dentro de una.
     */
    private function cerrarOtrosActivos(string $tipo, ?int $exceptoId = null): void
    {
        Periodo::where('estatus', 'activo')
            ->where('tipo', $tipo)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->update(['estatus' => 'cerrado']);
    }
}
