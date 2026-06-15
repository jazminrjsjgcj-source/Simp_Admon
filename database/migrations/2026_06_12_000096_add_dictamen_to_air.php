<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase E — Agrega columnas de dictamen al AIR.
 *
 * - dictamen: favorable | no_favorable | pendiente
 * - dictamen_observaciones: texto libre del dictaminador
 * - dictamen_fecha: cuándo se emitió
 * - dictaminado_por: usuario que dictaminó
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_impacto_regulatorio', function (Blueprint $table) {
            $table->enum('dictamen', ['pendiente', 'favorable', 'no_favorable'])
                  ->default('pendiente')
                  ->after('estatus');
            $table->text('dictamen_observaciones')->nullable()->after('dictamen');
            $table->date('dictamen_fecha')->nullable()->after('dictamen_observaciones');
            $table->foreignId('dictaminado_por')->nullable()->constrained('users')->after('dictamen_fecha');
        });

        // Agrega fracciones como JSON a exenciones_air
        Schema::table('exenciones_air', function (Blueprint $table) {
            $table->json('fracciones')->nullable()->after('supuesto');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_impacto_regulatorio', function (Blueprint $table) {
            $table->dropForeign(['dictaminado_por']);
            $table->dropColumn(['dictamen', 'dictamen_observaciones', 'dictamen_fecha', 'dictaminado_por']);
        });

        Schema::table('exenciones_air', function (Blueprint $table) {
            $table->dropColumn('fracciones');
        });
    }
};
