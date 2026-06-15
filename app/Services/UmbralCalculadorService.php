<?php

namespace App\Services;

use App\Models\UnidadValorReferencia;

/**
 * Servicio auxiliar para convertir un monto entre distintas unidades
 * (pesos, UMA, salario mínimo, UDI) usando los valores vigentes
 * registrados en `unidades_valor_referencia`.
 *
 * Si la unidad de referencia no está cargada para el año, devuelve null
 * en esa equivalencia (no se inventan valores).
 */
class UmbralCalculadorService
{
    /**
     * Calcula las equivalencias de un monto base.
     *
     * @param  float   $montoBase
     * @param  string  $unidadBase  'pesos', 'UMA', 'salario_minimo', 'UDI'
     * @param  int     $anio
     * @return array{monto_pesos: float, monto_uma: ?float, monto_salario_minimo: ?float, monto_udis: ?float}
     */
    public function calcularEquivalencias(float $montoBase, string $unidadBase, int $anio): array
    {
        $valores = $this->cargarValoresDelAnio($anio);

        $montoEnPesos = $this->convertirAPesos($montoBase, $unidadBase, $valores);

        return [
            'monto_pesos'          => round($montoEnPesos, 4),
            'monto_uma'            => $this->convertirDesdesPesos($montoEnPesos, UnidadValorReferencia::UMA,            $valores),
            'monto_salario_minimo' => $this->convertirDesdesPesos($montoEnPesos, UnidadValorReferencia::SALARIO_MINIMO, $valores),
            'monto_udis'           => $this->convertirDesdesPesos($montoEnPesos, UnidadValorReferencia::UDI,            $valores),
        ];
    }

    private function convertirAPesos(float $monto, string $unidad, array $valores): float
    {
        if ($unidad === 'pesos') {
            return $monto;
        }

        $valorUnidad = $valores[$unidad] ?? null;
        if (!$valorUnidad) {
            throw new \RuntimeException(
                "No hay valor de referencia activo para {$unidad}. Cargue el valor en la pantalla de unidades de referencia."
            );
        }

        return $monto * $valorUnidad;
    }

    private function convertirDesdesPesos(float $montoPesos, string $unidadDestino, array $valores): ?float
    {
        $valor = $valores[$unidadDestino] ?? null;
        if (!$valor || $valor <= 0) {
            return null;
        }

        return round($montoPesos / $valor, 4);
    }

    private function cargarValoresDelAnio(int $anio): array
    {
        return UnidadValorReferencia::activos()
            ->where('anio', $anio)
            ->pluck('valor_pesos', 'unidad')
            ->map(fn ($v) => floatval($v))
            ->toArray();
    }
}
