<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F.4 — Horarios de atención estructurados.
 *
 * Agrega columna JSON para guardar horarios por día,
 * manteniendo el campo horario (varchar) como resumen legible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ficha_portal', function (Blueprint $table) {
            $table->json('horarios_json')->nullable()->after('horario');
        });
    }

    public function down(): void
    {
        Schema::table('ficha_portal', function (Blueprint $table) {
            $table->dropColumn('horarios_json');
        });
    }
};
