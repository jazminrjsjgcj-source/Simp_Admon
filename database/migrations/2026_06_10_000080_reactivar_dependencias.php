<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reactiva todas las dependencias del catálogo original.
 *
 * La migración anterior (dependencia_unificada) las desactivó para
 * usar una sola. Con la regla de negocio de que cada usuario pertenece
 * a una dependencia y solo edita registros de su área, las dependencias
 * vuelven a ser necesarias.
 *
 * La dependencia '000' (Ayuntamiento unificado) se mantiene como opción
 * para registros que no pertenezcan a una dependencia específica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dependencias')->update(['activo' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('dependencias')
            ->where('codigo', '!=', '000')
            ->update(['activo' => false]);
    }
};
