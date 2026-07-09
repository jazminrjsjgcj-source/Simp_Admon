<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el catálogo inicial del diccionario jurídico de PUNTA.
 *
 * Cada concepto apunta a una TABLA PROPIA de PUNTA (tabla_preferente), no a
 * una ley externa. Esto es lo que permite que el buscador funcione sin
 * importar qué regulaciones tengas cargadas: si preguntas por "costo", el
 * sistema ya sabe que debe ir a la tabla `requisitos`, sin importar si la
 * regulación que define ese costo es municipal, estatal o de cualquier tipo.
 *
 * Este catálogo es intencionalmente pequeño (7 conceptos) para la Fase 1.
 * Se puede ampliar más adelante agregando filas nuevas — no requiere
 * modificar ningún código de los servicios que lo consultan.
 */
class DiccionarioJuridicoSeeder extends Seeder
{
    public function run(): void
    {
        $conceptos = [
            [
                'termino'          => 'servicio',
                'tipo_concepto'    => 'concepto',
                'tabla_preferente' => 'definiciones_legales',
                'relacionados'     => json_encode(['beneficio', 'programa social', 'actividad']),
            ],
            [
                'termino'          => 'tramite',
                'tipo_concepto'    => 'concepto',
                'tabla_preferente' => 'definiciones_legales',
                'relacionados'     => json_encode(['solicitud', 'gestion', 'procedimiento']),
            ],
            [
                'termino'          => 'requisito',
                'tipo_concepto'    => 'dato',
                'tabla_preferente' => 'requisitos',
                'relacionados'     => json_encode(['documento', 'condicion']),
            ],
            [
                'termino'          => 'costo',
                'tipo_concepto'    => 'dato',
                'tabla_preferente' => 'requisitos',
                'relacionados'     => json_encode(['pago', 'monto', 'tarifa', 'derecho', 'uma']),
            ],
            [
                'termino'          => 'plazo',
                'tipo_concepto'    => 'dato',
                'tabla_preferente' => 'tramites',
                'relacionados'     => json_encode(['tiempo de resolucion', 'vigencia', 'dias']),
            ],
            [
                'termino'          => 'fundamento',
                'tipo_concepto'    => 'dato',
                'tabla_preferente' => 'fundamento_juridico',
                'relacionados'     => json_encode(['articulo', 'base legal', 'norma']),
            ],
            [
                'termino'          => 'agenda',
                'tipo_concepto'    => 'herramienta',
                'tabla_preferente' => 'acciones_agenda',
                'relacionados'     => json_encode(['simplificacion', 'digitalizacion', 'accion']),
            ],
        ];

        foreach ($conceptos as $concepto) {
            DB::table('busqueda_diccionario_juridico')->updateOrInsert(
                ['termino' => $concepto['termino']], // condición para no duplicar si se corre de nuevo
                [
                    ...$concepto,
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
