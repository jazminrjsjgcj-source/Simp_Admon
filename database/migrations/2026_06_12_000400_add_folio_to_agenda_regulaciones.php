<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `folio` a las tablas que aún no la tienen:
 * acciones_agenda y regulaciones.
 *
 * El folio se genera automáticamente cuando el registro se envía a
 * revisión, con formato LPZ-{TIPO}-{SIGLAS}-{AÑO}-{consecutivo}.
 * Las tablas propuestas_regulatorias y analisis_impacto_regulatorio
 * ya tenían esta columna desde su creación.
 *
 * La columna es nullable (los borradores aún no tienen folio) y única
 * (no puede repetirse un folio entre registros del mismo tipo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('acciones_agenda', 'folio')) {
            Schema::table('acciones_agenda', function (Blueprint $table) {
                $table->string('folio', 50)->nullable()->unique();
            });
        }

        if (!Schema::hasColumn('regulaciones', 'folio')) {
            Schema::table('regulaciones', function (Blueprint $table) {
                $table->string('folio', 50)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('acciones_agenda', 'folio')) {
            Schema::table('acciones_agenda', function (Blueprint $table) {
                $table->dropColumn('folio');
            });
        }

        if (Schema::hasColumn('regulaciones', 'folio')) {
            Schema::table('regulaciones', function (Blueprint $table) {
                $table->dropColumn('folio');
            });
        }
    }
};
