<?php

namespace Database\Seeders;

use App\Models\ParametroCostoBurocratico;
use Illuminate\Database\Seeder;

/**
 * Seeder de parámetros del cálculo de costo burocrático.
 * Los valores son los mismos que las constantes del modelo Tramite,
 * pero ahora persistidos para que el admin los pueda modificar.
 */
class ParametrosCostoBurocraticoSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            ['clave' => 'salario_hora',        'valor' => 68.20,  'unidad' => 'pesos',  'fuente' => 'Salario diario INEGI / 8 hrs'],
            ['clave' => 'precio_copia',        'valor' => 1.50,   'unidad' => 'pesos',  'fuente' => 'Precio promedio mercado'],
            ['clave' => 'jornada_laboral',     'valor' => 8,      'unidad' => 'horas',  'fuente' => 'LFT México'],
            ['clave' => 'dias_por_mes',        'valor' => 30.42,  'unidad' => 'dias',   'fuente' => '365 / 12'],
            ['clave' => 'factor_dias_habiles', 'valor' => 1.4,    'unidad' => 'factor', 'fuente' => 'Conversión hábiles a naturales'],
        ];

        foreach ($parametros as $p) {
            ParametroCostoBurocratico::updateOrCreate(
                ['clave' => $p['clave']],
                array_merge($p, ['activo' => true])
            );
        }

        $this->command?->info('Parámetros de costo burocrático cargados.');
    }
}
