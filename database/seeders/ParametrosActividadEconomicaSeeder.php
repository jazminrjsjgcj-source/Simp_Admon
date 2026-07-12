<?php

namespace Database\Seeders;

use App\Models\ParametroActividadEconomica;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos económicos por sector SCIAN, para calcular cuánto le cuesta a una empresa esperar
 * la resolución de un trámite.
 *
 * ── De dónde salen los números ───────────────────────────────────────
 *
 * INEGI, Censos Económicos 2024 — resultados definitivos.
 * Monografía de Baja California Sur, Tabla 3: "Características principales de las unidades
 * económicas por sector de actividad, 2023".
 * https://inegi.org.mx/contenidos/programas/ce/2024/doc/mbcs_ce24.pdf
 *
 * Los Censos se levantaron en 2024, pero la información económica (ingresos, gastos,
 * remuneraciones, activos) se refiere al ejercicio 2023. Por eso el campo `anio` dice 2023
 * y no 2024: es el año al que corresponden las CIFRAS, no el año en que se recogieron.
 *
 * Las cifras de la monografía vienen en MILLONES de pesos. Aquí se guardan en pesos, por
 * eso cada una va multiplicada por 1,000,000 (la constante MDP).
 *
 * ── Las seis variables y de dónde sale cada una ──────────────────────
 *
 *   valor_produccion   → Producción bruta total    ┐
 *   gasto_consumo      → Consumo intermedio        │ Monografía de BCS, Tabla 3
 *   remuneraciones     → Remuneraciones            │ (cifras en millones de pesos)
 *   activos_fijos      → Activos fijos             │
 *   num_empresas       → Unidades económicas       ┘
 *
 *   inversion          → A211A Inversión total     ← SAIC, export del 11/07/2026
 *
 * La inversión NO está en la Tabla 3 de la monografía: hubo que sacarla del SAIC
 * (inegi.org.mx/app/saic → Censos Económicos 2024 → Baja California Sur → Todos los
 * sectores → variable censal "Inversión total").
 *
 * OJO CON EL NOMBRE. La metodología la llama "Formación bruta de capital fijo" (Inv, en la
 * Ecuación 6: "inversión realizada en el año en la actividad a"). El SAIC la llama
 * "Inversión total", con la clave A211A. Es el mismo concepto. Quien vaya a actualizar
 * estos datos dentro de cinco años buscará por el nombre de la metodología y no lo
 * encontrará: por eso queda escrito aquí.
 *
 * ── Prueba de sensatez ───────────────────────────────────────────────
 *
 * Con estos datos, el costo de oportunidad diario de una empresa de comercio al por menor
 * en BCS sale en $10.84. El ejemplo de la metodología, para el mismo giro en otra región y
 * otro año, da $4.08. Mismo orden de magnitud: la fórmula está bien aplicada.
 *
 * La fórmula ANTERIOR del sistema daba $545.60 al día, para cualquier trámite y cualquier
 * giro. De ahí venía el bug.
 *
 * ── Sobre los sectores agrupados ─────────────────────────────────────
 *
 * El INEGI publica "Manufacturas" como una sola fila, pero el catálogo SCIAN las divide en
 * tres sectores (31, 32 y 33). Igual con "Transportes" (48 y 49).
 *
 * A cada código se le asignan las MISMAS cifras agregadas. No es una duplicación errónea:
 * lo que el cálculo usa son RAZONES (capital por empresa, productividad por peso
 * invertido), y esas razones son idénticas para los tres códigos si los datos vienen del
 * mismo agregado. Es la mejor estimación disponible con lo que el INEGI publica.
 *
 * ── Sectores que faltan y por qué ────────────────────────────────────
 *
 *   21 Minería             → el INEGI omite las cifras por confidencialidad (solo 21 UE)
 *   22 Electricidad y gas  → ídem (solo 5 UE)
 *   55 Corporativos        → no aparece en la monografía
 *   93 Gobierno            → no es actividad empresarial
 *
 * Un trámite de esos sectores no encuentra parámetros y queda "no calculable", que es la
 * respuesta honesta.
 *
 * ── Sector 11 (agropecuario) ─────────────────────────────────────────
 *
 * Los Censos Económicos solo cubren "Pesca y acuicultura" de ese sector; las actividades
 * agrícolas y ganaderas quedan fuera de su cobertura. Las cifras sembradas bajo el código
 * 11 son SOLO de pesca y acuicultura. Para un trámite agrícola, el costo estará
 * subestimado. Queda anotado en la columna `fuente`.
 */
class ParametrosActividadEconomicaSeeder extends Seeder
{
    /** Las cifras de la monografía vienen en millones de pesos. */
    private const MDP = 1_000_000;

    private const ANIO_DE_LAS_CIFRAS = 2023;

    /** Cabe de sobra en varchar(255). La explicación larga va en el docblock, no en la base. */
    private const FUENTE = 'INEGI, Censos Económicos 2024 (definitivos), Monografía BCS, Tabla 3. Cifras 2023.';

    public function run(): void
    {
        $sectoresPorCodigo = DB::table('sectores_scian')->pluck('id', 'codigo')->toArray();

        if ($sectoresPorCodigo === []) {
            $this->command?->error('No hay sectores SCIAN. Corre primero ScianSeeder.');

            return;
        }

        $sembrados = 0;

        foreach ($this->datosPorSector() as $fila) {
            foreach ($fila['codigos'] as $codigo) {
                if (! isset($sectoresPorCodigo[$codigo])) {
                    continue;
                }

                ParametroActividadEconomica::updateOrCreate(
                    [
                        'sector_id'    => $sectoresPorCodigo[$codigo],
                        'subsector_id' => null,
                        'anio'         => self::ANIO_DE_LAS_CIFRAS,
                    ],
                    [
                        'valor_produccion' => $fila['produccion_bruta'] * self::MDP,
                        'gasto_consumo'    => $fila['consumo_intermedio'] * self::MDP,
                        'remuneraciones'   => $fila['remuneraciones'] * self::MDP,
                        'activos_fijos'    => $fila['activos_fijos'] * self::MDP,
                        'num_empresas'     => $fila['unidades_economicas'],

                        'inversion'        => $fila['inversion'] * self::MDP,

                        'fuente'           => $fila['nota'] ?? self::FUENTE,
                        'activo'           => true,
                    ]
                );

                $sembrados++;
            }
        }

        $this->command?->info("Parámetros económicos: {$sembrados} sectores cargados (INEGI CE 2024, BCS).");
        $this->command?->warn(
            'Minería (21) y Electricidad, agua y gas (22) NO se siembran: el INEGI omite sus '
            . 'cifras por confidencialidad (solo 21 y 5 unidades económicas en todo BCS). Un '
            . 'trámite de esos sectores dirigido a personas morales quedará marcado como '
            . 'NO CALCULABLE, que es la respuesta honesta.'
        );
    }

    /**
     * Tabla 3 de la monografía, tal cual. Cifras en MILLONES de pesos.
     *
     * Se copian aquí, y no se leen de un CSV, para que sean auditables: cualquiera puede
     * abrir el PDF del INEGI y comparar fila por fila. Un número mágico dentro de un
     * archivo de datos es imposible de verificar; uno con su fuente al lado, no.
     */
    private function datosPorSector(): array
    {
        return [
            [
                'codigos'             => ['11'],
                'nombre_inegi'        => 'Pesca y acuicultura',
                'unidades_economicas' => 763,
                'remuneraciones'      => 526,
                'produccion_bruta'    => 3_188,
                'consumo_intermedio'  => 1_029,
                'activos_fijos'       => 2_350,
                'inversion'           => 33.457,
                // AVISO: los Censos Económicos solo cubren Pesca y acuicultura dentro del
                // sector 11. La agricultura y la ganadería quedan FUERA de su cobertura, así
                // que un trámite agrícola tendrá el costo de espera subestimado. La nota va
                // también en la columna `fuente` para que se vea desde la base de datos.
                'nota'                => self::FUENTE . ' Solo Pesca y acuicultura: la agricultura '
                    . 'y la ganadería quedan fuera de la cobertura censal.',
            ],
            [
                'codigos'             => ['23'],
                'nombre_inegi'        => 'Construcción',
                'unidades_economicas' => 348,
                'remuneraciones'      => 1_631,
                'produccion_bruta'    => 9_075,
                'consumo_intermedio'  => 5_470,
                'activos_fijos'       => 1_937,
                'inversion'           => 44.363,
            ],
            [
                // El INEGI publica Manufacturas como una sola fila; SCIAN las divide en 31, 32 y 33.
                'codigos'             => ['31', '32', '33'],
                'nombre_inegi'        => 'Manufacturas',
                'unidades_economicas' => 3_090,
                'remuneraciones'      => 1_543,
                'produccion_bruta'    => 10_403,
                'consumo_intermedio'  => 6_450,
                'activos_fijos'       => 4_070,
                'inversion'           => 232.033,
            ],
            [
                'codigos'             => ['43'],
                'nombre_inegi'        => 'Comercio al por mayor',
                'unidades_economicas' => 1_064,
                'remuneraciones'      => 1_970,
                'produccion_bruta'    => 19_952,
                'consumo_intermedio'  => 4_049,
                'activos_fijos'       => 5_142,
                'inversion'           => 296.182,
            ],
            [
                'codigos'             => ['46'],
                'nombre_inegi'        => 'Comercio al por menor',
                'unidades_economicas' => 12_719,
                'remuneraciones'      => 5_600,
                'produccion_bruta'    => 32_524,
                'consumo_intermedio'  => 7_198,
                'activos_fijos'       => 12_742,
                'inversion'           => 1351.415,
            ],
            [
                // Una sola fila del INEGI; SCIAN la divide en 48 y 49.
                'codigos'             => ['48', '49'],
                'nombre_inegi'        => 'Transportes, correos y almacenamiento',
                'unidades_economicas' => 444,
                'remuneraciones'      => 1_451,
                'produccion_bruta'    => 7_973,
                'consumo_intermedio'  => 3_275,
                'activos_fijos'       => 6_627,
                'inversion'           => 94.363,
            ],
            [
                'codigos'             => ['51'],
                'nombre_inegi'        => 'Información en medios masivos',
                'unidades_economicas' => 57,
                'remuneraciones'      => 188,
                'produccion_bruta'    => 952,
                'consumo_intermedio'  => 380,
                'activos_fijos'       => 200,
                'inversion'           => 8.348,
            ],
            [
                'codigos'             => ['52'],
                'nombre_inegi'        => 'Servicios financieros y de seguros',
                'unidades_economicas' => 213,
                'remuneraciones'      => 155,
                'produccion_bruta'    => 1_466,
                'consumo_intermedio'  => 333,
                'activos_fijos'       => 94,
                'inversion'           => 2.225,
            ],
            [
                'codigos'             => ['53'],
                'nombre_inegi'        => 'Servicios inmobiliarios y de alquiler de bienes',
                'unidades_economicas' => 1_104,
                'remuneraciones'      => 707,
                'produccion_bruta'    => 5_961,
                'consumo_intermedio'  => 2_463,
                'activos_fijos'       => 4_084,
                'inversion'           => 228.069,
            ],
            [
                'codigos'             => ['54'],
                'nombre_inegi'        => 'Servicios profesionales, científicos y técnicos',
                'unidades_economicas' => 1_102,
                'remuneraciones'      => 679,
                'produccion_bruta'    => 2_910,
                'consumo_intermedio'  => 909,
                'activos_fijos'       => 819,
                'inversion'           => 29.755,
            ],
            [
                'codigos'             => ['56'],
                'nombre_inegi'        => 'Apoyo a los negocios y manejo de residuos',
                'unidades_economicas' => 473,
                'remuneraciones'      => 920,
                'produccion_bruta'    => 5_877,
                'consumo_intermedio'  => 2_616,
                'activos_fijos'       => 1_519,
                'inversion'           => 27.527,
            ],
            [
                'codigos'             => ['61'],
                'nombre_inegi'        => 'Servicios educativos',
                'unidades_economicas' => 411,
                'remuneraciones'      => 540,
                'produccion_bruta'    => 1_220,
                'consumo_intermedio'  => 403,
                'activos_fijos'       => 622,
                'inversion'           => 33.729,
            ],
            [
                'codigos'             => ['62'],
                'nombre_inegi'        => 'Servicios de salud y de asistencia social',
                'unidades_economicas' => 1_725,
                'remuneraciones'      => 540,
                'produccion_bruta'    => 2_544,
                'consumo_intermedio'  => 1_204,
                'activos_fijos'       => 1_493,
                'inversion'           => 91.087,
            ],
            [
                'codigos'             => ['71'],
                'nombre_inegi'        => 'Servicios de esparcimiento, culturales y deportivos',
                'unidades_economicas' => 482,
                'remuneraciones'      => 503,
                'produccion_bruta'    => 2_967,
                'consumo_intermedio'  => 1_432,
                'activos_fijos'       => 3_640,
                'inversion'           => 108.755,
            ],
            [
                // El sector más grande de BCS: hoteles y restaurantes concentran el 35.5 % de
                // los activos fijos de todo el estado.
                'codigos'             => ['72'],
                'nombre_inegi'        => 'Hoteles y restaurantes',
                'unidades_economicas' => 5_813,
                'remuneraciones'      => 4_903,
                'produccion_bruta'    => 32_544,
                'consumo_intermedio'  => 15_732,
                'activos_fijos'       => 31_722,
                'inversion'           => 273.688,
            ],
            [
                'codigos'             => ['81'],
                'nombre_inegi'        => 'Otros servicios excepto gobierno',
                'unidades_economicas' => 6_311,
                'remuneraciones'      => 605,
                'produccion_bruta'    => 3_934,
                'consumo_intermedio'  => 1_796,
                'activos_fijos'       => 2_533,
                'inversion'           => 215.937,
            ],
        ];
    }
}
