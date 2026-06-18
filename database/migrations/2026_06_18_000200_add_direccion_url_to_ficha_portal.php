<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los campos de ubicación del trámite a la ficha portal:
 * - direccion: dónde se realiza el trámite cuando es presencial o mixto.
 * - url: enlace donde se realiza el trámite cuando es en línea o mixto.
 *
 * Se muestran/ocultan según la modalidad de atención del paso 6 del wizard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ficha_portal', function (Blueprint $table) {
            if (!Schema::hasColumn('ficha_portal', 'direccion')) {
                $table->string('direccion', 500)->nullable()->after('oficina');
            }
            if (!Schema::hasColumn('ficha_portal', 'url')) {
                $table->string('url', 500)->nullable()->after('direccion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ficha_portal', function (Blueprint $table) {
            if (Schema::hasColumn('ficha_portal', 'direccion')) {
                $table->dropColumn('direccion');
            }
            if (Schema::hasColumn('ficha_portal', 'url')) {
                $table->dropColumn('url');
            }
        });
    }
};
