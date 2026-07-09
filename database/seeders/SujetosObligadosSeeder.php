<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga los sujetos obligados (titulares) de cada dependencia.
 *
 * Uso:
 *   php artisan db:seed --class=SujetosObligadosSeeder
 *
 * Busca la dependencia por coincidencia parcial del nombre,
 * así que funciona aunque los nombres en BD no sean exactos.
 * Si no encuentra la dependencia, la crea automáticamente.
 *
 * Es idempotente: si el sujeto ya existe (mismo nombre + misma
 * dependencia), no lo duplica.
 */
class SujetosObligadosSeeder extends Seeder
{
    public function run(): void
    {
        $titulares = [
            ['dependencia' => 'Gestión Integral de la Ciudad',                 'nombre' => 'Ing. Kenia Selene Cervantes Villegas',        'cargo' => 'Directora General'],
            ['dependencia' => 'Servicios Públicos',                            'nombre' => 'Carlos Gabriel Núñez Geraldo',                'cargo' => 'Director General'],
            ['dependencia' => 'Bienestar y Desarrollo Económico',              'nombre' => 'Carla Jonguitud Mendarozqueta',               'cargo' => 'Directora General'],
            ['dependencia' => 'Seguridad Vial y de Transporte',                'nombre' => 'Francisco Javier Ramírez Robles',             'cargo' => 'Director General'],
            ['dependencia' => 'Seguridad Pública y Policía Preventiva',        'nombre' => 'Capitán de Corbeta Ruth de la Fuente Velázquez', 'cargo' => 'Directora General'],
            ['dependencia' => 'Catastro',                                      'nombre' => 'Ing. Luis Alberto Nah González',              'cargo' => 'Director General'],
            ['dependencia' => 'Inclusión y Diversidad',                        'nombre' => 'Lic. Mayra Cisneros Nevarez',                 'cargo' => 'Directora General'],
            ['dependencia' => 'Gobierno Digital',                              'nombre' => 'Ing. Jorge Alberto Armenta Atamoros',          'cargo' => 'Director General'],
            ['dependencia' => 'Sustentabilidad y Manejo de Residuos',          'nombre' => 'Daniel Cabral Ramírez',                       'cargo' => 'Director General'],
        ];

        $creados = 0;
        $existentes = 0;
        $depCreadas = 0;

        foreach ($titulares as $t) {
            // Buscar dependencia por coincidencia parcial
            $dep = DB::table('dependencias')
                ->where('nombre', 'LIKE', '%' . $t['dependencia'] . '%')
                ->first();

            // Si no existe, crearla
            if (!$dep) {
                $nombre = 'Dirección General de ' . $t['dependencia'];
                $depId = DB::table('dependencias')->insertGetId([
                    'nombre'     => $nombre,
                    'codigo'     => strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($t['dependencia'])), 0, 5)),
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $depCreadas++;
                $this->command?->warn("  + Dependencia creada: {$nombre}");
            } else {
                $depId = $dep->id;
            }

            // Verificar si ya existe este sujeto obligado
            $existe = DB::table('sujetos_obligados')
                ->where('dependencia_id', $depId)
                ->where('nombre', $t['nombre'])
                ->exists();

            if ($existe) {
                $existentes++;
                continue;
            }

            // Desactivar sujetos anteriores de esta dependencia
            DB::table('sujetos_obligados')
                ->where('dependencia_id', $depId)
                ->where('activo', true)
                ->update(['activo' => false]);

            // Crear el nuevo sujeto obligado
            DB::table('sujetos_obligados')->insert([
                'dependencia_id' => $depId,
                'nombre'         => $t['nombre'],
                'cargo'          => $t['cargo'],
                'activo'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $creados++;
        }

        $this->command?->info('');
        $this->command?->info("  ✓ Sujetos obligados: {$creados} creados, {$existentes} ya existían");
        if ($depCreadas > 0) {
            $this->command?->info("  ✓ {$depCreadas} dependencias nuevas creadas");
        }
        $this->command?->info('');
    }
}
