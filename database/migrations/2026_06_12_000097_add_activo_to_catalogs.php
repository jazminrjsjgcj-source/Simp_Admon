<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C — Soft toggle para catálogos.
 *
 * Agrega `activo` boolean a unidades_administrativas y
 * unidades_responsables para activar/desactivar sin eliminar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // unidades_administrativas ya no tiene activo — lo añadimos
        if (!Schema::hasColumn('unidades_administrativas', 'activo')) {
            Schema::table('unidades_administrativas', function (Blueprint $table) {
                $table->boolean('activo')->default(true);
            });
        }

        // unidades_responsables
        if (Schema::hasTable('unidades_responsables') && !Schema::hasColumn('unidades_responsables', 'activo')) {
            Schema::table('unidades_responsables', function (Blueprint $table) {
                $table->boolean('activo')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unidades_administrativas', 'activo')) {
            Schema::table('unidades_administrativas', function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }

        if (Schema::hasTable('unidades_responsables') && Schema::hasColumn('unidades_responsables', 'activo')) {
            Schema::table('unidades_responsables', function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }
    }
};
