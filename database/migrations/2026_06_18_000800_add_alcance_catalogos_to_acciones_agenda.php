<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete 3 — Pieza 1. Agenda SyD: alcance, catálogos oficiales y niveles.
 *
 * Hoy la acción de agenda solo distingue 'simplificacion' o 'digitalizacion'
 * (enum tipo), pero el wizard ofrece también "ambas", que la validación
 * rechazaba. Además, los datos oficiales del instrumento (acciones de
 * simplificación, catálogo de digitalización, nivel actual y meta) no tenían
 * dónde guardarse. Esta migración:
 *
 *   1. Agrega 'ambas' al enum `tipo` (para que el alcance combinado sea válido).
 *   2. acciones_simplificacion (JSON): acciones SIMP elegidas (rubro 14).
 *   3. acciones_digitalizacion (JSON): catálogo DIG oficial de 8 opciones.
 *   4. nivel_actual / nivel_meta (tinyint 0-5): niveles de digitalización como
 *      número, en lugar del texto libre que se usaba (`meta` / `meta_digital`).
 *
 * Todas las columnas son nullable; las acciones existentes siguen válidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar 'ambas' al enum tipo.
        // El tipo ya es columna de texto: acepta 'ambas' sin ampliar un ENUM.
        // Los valores válidos los valida Laravel.

        Schema::table('acciones_agenda', function (Blueprint $table) {
            if (!Schema::hasColumn('acciones_agenda', 'acciones_simplificacion')) {
                $table->json('acciones_simplificacion')->nullable();
            }
            if (!Schema::hasColumn('acciones_agenda', 'acciones_digitalizacion')) {
                $table->json('acciones_digitalizacion')->nullable();
            }
            if (!Schema::hasColumn('acciones_agenda', 'nivel_actual')) {
                $table->unsignedTinyInteger('nivel_actual')->nullable();
            }
            if (!Schema::hasColumn('acciones_agenda', 'nivel_meta')) {
                $table->unsignedTinyInteger('nivel_meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            foreach (['acciones_simplificacion', 'acciones_digitalizacion', 'nivel_actual', 'nivel_meta'] as $col) {
                if (Schema::hasColumn('acciones_agenda', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Revertir el enum: cualquier acción 'ambas' se normaliza a 'simplificacion'.
        DB::statement("UPDATE acciones_agenda SET tipo = 'simplificacion' WHERE tipo = 'ambas'");
        // El tipo ya es columna de texto: acepta 'ambas' sin ampliar un ENUM.
        // Los valores válidos los valida Laravel.
    }
};
