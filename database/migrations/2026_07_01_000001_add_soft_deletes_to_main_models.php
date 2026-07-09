<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #36: agrega la columna `deleted_at` (soft delete de Laravel) a las tablas
 * principales que el usuario puede borrar desde la interfaz.
 *
 * - propuestas_regulatorias: el enlace puede borrar borradores propios.
 * - acciones_agenda: el enlace puede borrar borradores propios.
 * - users: el admin puede desactivar/borrar usuarios.
 *
 * La tabla `regulaciones` ya tiene la columna (migración 2026_06_08_000010)
 * pero el modelo no usaba el trait — eso se corrige en el modelo, no aquí.
 *
 * La tabla `tramites` ya tiene soft delete funcional (modelo + columna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propuestas_regulatorias', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('acciones_agenda', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('propuestas_regulatorias', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('acciones_agenda', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
