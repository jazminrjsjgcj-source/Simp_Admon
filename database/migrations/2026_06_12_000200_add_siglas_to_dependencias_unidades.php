<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `siglas` a dependencias y unidades administrativas.
 *
 * Las siglas se usan para construir la homoclave de los trámites con
 * el formato LPZ-{siglas_dependencia}-{siglas_unidad}-{consecutivo}
 * (ej: LPZ-DGSP-VU-5).
 *
 * La columna se llena automáticamente con las iniciales del nombre
 * (ignorando conectores como "de", "del", "la"). Las siglas quedan
 * editables después desde el catálogo admin, por si hay que ajustarlas
 * o resolver duplicados.
 */
return new class extends Migration
{
    /**
     * Conectores que no cuentan al generar las siglas de un nombre.
     */
    private array $conectores = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'en', 'a'];

    public function up(): void
    {
        // 1. Agregar la columna (nullable primero, para poder poblarla después)
        Schema::table('dependencias', function (Blueprint $table) {
            $table->string('siglas', 15)->nullable();
        });
        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->string('siglas', 15)->nullable();
        });

        // 2. Poblar siglas de dependencias a partir de su nombre
        foreach (DB::table('dependencias')->get() as $dep) {
            DB::table('dependencias')
                ->where('id', $dep->id)
                ->update(['siglas' => $this->generarSiglas($dep->nombre)]);
        }

        // 3. Poblar siglas de unidades administrativas
        foreach (DB::table('unidades_administrativas')->get() as $unidad) {
            DB::table('unidades_administrativas')
                ->where('id', $unidad->id)
                ->update(['siglas' => $this->generarSiglas($unidad->nombre)]);
        }
    }

    public function down(): void
    {
        Schema::table('dependencias', function (Blueprint $table) {
            $table->dropColumn('siglas');
        });
        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->dropColumn('siglas');
        });
    }

    /**
     * Genera siglas tomando la inicial de cada palabra significativa.
     * Ej: "Dirección General de Seguridad Pública" → "DGSP".
     *
     * @param  string  $nombre  Nombre completo de la dependencia o unidad.
     * @return string  Siglas en mayúsculas (máx 15 caracteres).
     */
    private function generarSiglas(string $nombre): string
    {
        $palabras = preg_split('/\s+/', trim($nombre));
        $iniciales = '';

        foreach ($palabras as $palabra) {
            if ($palabra === '' || in_array(mb_strtolower($palabra), $this->conectores, true)) {
                continue;
            }
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return mb_substr($iniciales, 0, 15);
    }
};
