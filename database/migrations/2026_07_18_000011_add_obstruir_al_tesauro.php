<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Añade al tesauro la entrada obstruir → obstaculo, el último eslabón que faltaba
 * para el caso insignia ("¿cuánto es la multa por obstruir la banqueta?").
 *
 * ── Por qué esta entrada, y por qué no es "lematización" ─────────────
 *
 * El artículo 65 del Bando dice: "Poner OBSTÁCULOS en las calles, banquetas…".
 * El ciudadano dice el verbo, "obstruir". El buscador busca por prefijo
 * (obstruir:*), que jamás casa "obstáculos": no comparten raíz (obstru- vs
 * obstacul-), así que ningún stemmer los uniría. No es conjugación; es una
 * relación de significado, y para eso existe el tesauro.
 *
 * ("banqueta" no necesita nada: el art. 65 dice "banquetas", y banqueta:* casa.)
 *
 * El seeder (TesauroJuridicoSeeder) ya trae esta entrada para instalaciones
 * nuevas; esta migración la aplica a una base YA sembrada, de forma idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('busqueda_tesauro')->where('termino_ciudadano', 'obstruir')->exists();

        DB::table('busqueda_tesauro')->updateOrInsert(
            ['termino_ciudadano' => 'obstruir'],
            [
                'terminos_ley' => 'obstaculo',
                'origen'       => 'refuerzo',
                'activo'       => true,
                'nota'         => 'obstruir/obstáculos: el ciudadano dice el verbo, el art. 65 dice el sustantivo.',
                'updated_at'   => now(),
            ] + ($existe ? [] : ['created_at' => now()])
        );

        // El tesauro se cachea una hora; se invalida para que el cambio surta efecto ya.
        Cache::forget('tesauro:completo');
    }

    public function down(): void
    {
        DB::table('busqueda_tesauro')->where('termino_ciudadano', 'obstruir')->delete();
        Cache::forget('tesauro:completo');
    }
};
