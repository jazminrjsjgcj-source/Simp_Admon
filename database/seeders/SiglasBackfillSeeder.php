<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena las siglas faltantes de dependencias y unidades administrativas.
 *
 * La homoclave de un trámite se arma como LPZ-(siglas dependencia)-(siglas
 * unidad)-(consecutivo). Si una dependencia o unidad no tiene siglas, la
 * previsualización no puede generarse (el endpoint responde 422). Esto pasa con
 * los registros creados después de la migración que agregó la columna, que
 * quedaron con siglas en blanco.
 *
 * Este seeder busca esos registros y les genera las siglas a partir del nombre,
 * con la MISMA lógica que la migración 2026_06_12_000200 (iniciales de las
 * palabras significativas, ignorando conectores como "de", "del", "la").
 *
 * Es idempotente y no destructivo: solo toca registros con siglas en blanco, así
 * que nunca sobreescribe unas siglas ya capturadas o ajustadas a mano. Se puede
 * correr las veces que haga falta.
 *
 * Uso:
 *   php artisan db:seed --class=SiglasBackfillSeeder
 */
class SiglasBackfillSeeder extends Seeder
{
    /** Conectores que no cuentan al generar las siglas (igual que la migración). */
    private array $conectores = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'en', 'a'];

    public function run(): void
    {
        $dependencias = $this->rellenar('dependencias');
        $unidades     = $this->rellenar('unidades_administrativas');

        $this->command?->info(
            "Siglas generadas → dependencias: {$dependencias}, unidades: {$unidades}."
        );
    }

    /**
     * Rellena las siglas en blanco de una tabla. Devuelve cuántos registros tocó.
     * Solo actúa sobre filas con siglas NULL o vacías: las ya capturadas no se
     * tocan.
     */
    private function rellenar(string $tabla): int
    {
        if (!Schema::hasColumn($tabla, 'siglas')) {
            return 0;
        }

        $pendientes = DB::table($tabla)
            ->select('id', 'nombre')
            ->where(function ($q) {
                $q->whereNull('siglas')->orWhere('siglas', '');
            })
            ->get();

        foreach ($pendientes as $registro) {
            $siglas = $this->generarSiglas($registro->nombre ?? '');

            // Si el nombre no da ninguna inicial (vacío o solo conectores), se
            // omite para no dejar unas siglas vacías otra vez.
            if ($siglas === '') {
                continue;
            }

            DB::table($tabla)
                ->where('id', $registro->id)
                ->update(['siglas' => $siglas]);
        }

        return $pendientes->count();
    }

    /**
     * Genera siglas tomando la inicial de cada palabra significativa.
     * Ej: "Dirección General de Seguridad Pública" → "DGSP".
     *
     * Misma lógica que la migración 2026_06_12_000200: es la fuente única del
     * criterio, así que las siglas quedan consistentes con las ya existentes.
     *
     * @param  string  $nombre  Nombre completo de la dependencia o unidad.
     * @return string  Siglas en mayúsculas (máx 15 caracteres).
     */
    private function generarSiglas(string $nombre): string
    {
        $palabras  = preg_split('/\s+/', trim($nombre));
        $iniciales = '';

        foreach ($palabras as $palabra) {
            if ($palabra === '' || in_array(mb_strtolower($palabra), $this->conectores, true)) {
                continue;
            }
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return mb_substr($iniciales, 0, 15);
    }
}
