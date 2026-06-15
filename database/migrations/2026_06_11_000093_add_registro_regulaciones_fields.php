<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega los 7 campos imprescindibles del Art. 153 Lineamientos
     * para cumplir con el Registro Nacional de Regulaciones.
     */
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            // Art. 153 fracc. II — Ámbito de aplicación o materia
            $table->string('materia', 100)->nullable()->after('tipo');

            // Art. 153 fracc. VIII — Fundamento jurídico para la expedición
            $table->text('fundamento_juridico')->nullable()->after('resumen');

            // Art. 153 fracc. XI — Objetivo de la Regulación
            $table->text('objetivo')->nullable()->after('fundamento_juridico');

            // Art. 153 fracc. XIII — Sectores o sujetos regulados
            $table->foreignId('sector_id')->nullable()->after('objetivo')
                ->constrained('sectores_scian')->nullOnDelete();

            // Art. 153 fracc. XIV — Palabras clave para identificar la Regulación
            $table->string('palabras_clave', 500)->nullable()->after('sector_id');

            // Art. 153 fracc. XV — ¿Deja sin efectos alguna otra regulación?
            $table->boolean('deroga_otra')->default(false)->after('palabras_clave');
            $table->string('regulacion_derogada', 500)->nullable()->after('deroga_otra');

            // Art. 153 fracc. XII — Índice de la Regulación (auto-extraído + editable)
            $table->json('indice')->nullable()->after('regulacion_derogada');
        });
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropColumn([
                'materia',
                'fundamento_juridico',
                'objetivo',
                'sector_id',
                'palabras_clave',
                'deroga_otra',
                'regulacion_derogada',
                'indice',
            ]);
        });
    }
};
