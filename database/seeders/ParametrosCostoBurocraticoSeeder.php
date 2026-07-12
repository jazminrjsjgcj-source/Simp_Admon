<?php

namespace Database\Seeders;

use App\Models\ParametroCostoBurocratico;
use Illuminate\Database\Seeder;

/**
 * Parámetros del cálculo del Costo Burocrático.
 *
 * Hay DOS familias de parámetros aquí, y conviene no confundirlas:
 *
 * ── 1. CONVENCIONES ──
 *
 * salario_hora, precio_copia, jornada_laboral, dias_por_mes, factor_dias_habiles.
 *
 * Son acuerdos, no mediciones. La jornada laboral son 8 horas porque lo dice la ley; el
 * factor 1.4 sale de dividir los 2 días inhábiles entre los 5 laborales. Admiten un valor
 * por defecto razonable, y el modelo Tramite los tiene como constantes de respaldo por si
 * la tabla está vacía.
 *
 * ── 2. DATOS ECONÓMICOS ──
 *
 * pib, poblacion, tasa_libre_riesgo.
 *
 * Estos NO son convenciones: son mediciones de la economía real, y no admiten valor por
 * defecto. El PIB de un municipio no se puede "estimar razonablemente". Si no están
 * cargados, el sistema marca el costo de espera como NO CALCULABLE en vez de inventar una
 * cifra.
 *
 * Sirven para el costo de oportunidad de una PERSONA FÍSICA (Ecuaciones 6 y 7):
 *
 *     PIB per cápita = PIB / Población
 *     Costo diario   = (TasaLibreRiesgo / 365) × (PIBpc / 365)
 *
 * Con los datos de abajo:
 *     PIBpc          = 251,902,000,000 / 868,622 = $290,001
 *     Costo diario   = (0.0630 / 365) × (290,001 / 365) = $0.137
 *
 * Es decir: cada día que un ciudadano espera una resolución le cuesta unos 14 centavos. Y
 * ese número es del mismo orden que el ejemplo de la propia metodología ($0.07/día), lo
 * que confirma que la fórmula está bien aplicada.
 *
 * Para comparar: la fórmula ANTERIOR del sistema calculaba $545.60 por día. Estaba
 * multiplicando el salario por la jornada, que no es lo que la metodología pide.
 */
class ParametrosCostoBurocraticoSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            // ── Convenciones ──
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_SALARIO_HORA,
                'valor'  => 68.20,
                'unidad' => 'pesos',
                'fuente' => 'Salario diario INEGI / 8 hrs',
            ],
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_PRECIO_COPIA,
                'valor'  => 1.50,
                'unidad' => 'pesos',
                'fuente' => 'Precio promedio de mercado',
            ],
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_JORNADA_LABORAL,
                'valor'  => 8,
                'unidad' => 'horas',
                'fuente' => 'Ley Federal del Trabajo',
            ],
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_DIAS_POR_MES,
                'valor'  => 365 / 12,
                'unidad' => 'dias',
                'fuente' => 'Metodología ATDT: 365/12, para tratar todos los meses igual.',
            ],
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_FACTOR_DIAS_HABILES,
                'valor'  => 1.4,
                'unidad' => 'factor',
                'fuente' => 'Metodología ATDT: 2 días inhábiles / 5 laborales = 0.4.',
            ],

            // ── Datos económicos de la región ──
            [
                'clave'  => ParametroCostoBurocratico::CLAVE_PIB,
                'valor'  => 251_902_000_000, // 251,902 millones de pesos
                'unidad' => 'pesos',
                'fuente' => 'INEGI, PIB por Entidad Federativa 2024, valores corrientes. BCS: 251,902 mdp.',
            ],
            [
                // Se usa la proyección de CONAPO para 2024, y NO el Censo 2020 (798,447
                // habitantes), para que el PIB y la población sean del MISMO año. Dividir el
                // PIB de 2024 entre la población de 2020 inflaría el PIB per cápita en un 9 %.
                'clave'  => ParametroCostoBurocratico::CLAVE_POBLACION,
                'valor'  => 868_622,
                'unidad' => 'personas',
                'fuente' => 'CONAPO, proyección de población 2024 para Baja California Sur.',
            ],
            [
                // ⚠️ SE GUARDA COMO DECIMAL, NO COMO PORCENTAJE. 6.30 % anual → 0.0630.
                //
                // Capturarla como 6.30 multiplicaría el costo de espera POR CIEN, y el
                // resultado seguiría pareciendo plausible: nadie mira un costo de $14 al día
                // y piensa "esto está cien veces mal". Es el mismo tipo de error que el UMA
                // de los requisitos — un número correcto en la unidad equivocada.
                'clave'  => ParametroCostoBurocratico::CLAVE_TASA_LIBRE_RIESGO,
                'valor'  => 0.0630,
                'unidad' => 'decimal',
                'fuente' => 'Banxico, CETES 28 días, subasta del 2 de julio de 2026: 6.30 % anual.',
            ],
        ];

        foreach ($parametros as $p) {
            ParametroCostoBurocratico::updateOrCreate(
                ['clave' => $p['clave']],
                array_merge($p, ['activo' => true])
            );
        }

        $this->command?->info('Parámetros de costo burocrático cargados (convenciones + datos económicos de BCS).');
    }
}
