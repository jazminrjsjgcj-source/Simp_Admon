<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca si una regulación ya tiene su articulado estructurado como árbol de
 * nodos (regulacion_nodos). Las regulaciones viejas siguen mostrándose con su
 * markdown plano hasta que se estructuran; este flag distingue ambos casos sin
 * romper lo existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->boolean('estructurada')->default(false)->after('indice');
        });
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropColumn('estructurada');
        });
    }
};
