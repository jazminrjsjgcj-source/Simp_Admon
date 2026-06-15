<?php

namespace Database\Seeders;

use App\Models\UnidadValorReferencia;
use Illuminate\Database\Seeder;

/**
 * Valores oficiales 2026 de UMA, salario mínimo y UDI.
 * Estos valores deben actualizarse cada año según publicación oficial:
 *   - UMA: INEGI (publicación enero)
 *   - Salario mínimo: CONASAMI (publicación diciembre del año anterior)
 *   - UDI: BANXICO (diaria, se promedia o se toma cierre de año)
 */
class UnidadesValorReferenciaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            [
                'unidad'      => UnidadValorReferencia::UMA,
                'valor_pesos' => 113.14,
                'anio'        => 2026,
                'fuente'      => 'INEGI - Valor anual UMA 2026',
            ],
            [
                'unidad'      => UnidadValorReferencia::SALARIO_MINIMO,
                'valor_pesos' => 278.80,
                'anio'        => 2026,
                'fuente'      => 'CONASAMI - Salario mínimo general 2026',
            ],
        ];

        foreach ($unidades as $u) {
            UnidadValorReferencia::updateOrCreate(
                ['unidad' => $u['unidad'], 'anio' => $u['anio']],
                array_merge($u, ['activo' => true])
            );
        }

        $this->command?->info('Unidades de valor de referencia cargadas (UMA, salario mínimo 2026).');
    }
}
