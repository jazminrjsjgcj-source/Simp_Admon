<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #16 — Agrega la columna `costo_unidad` a requisitos para registrar si el
 * costo está expresado en UMA o en Pesos. Antes solo existía `costo_requisito`
 * (decimal) sin la unidad, lo que obligaba a asumir pesos y rompía el cálculo
 * cuando el documento se cobraba en UMA (ej. constancias municipales).
 *
 * - 'PESOS' por defecto (el comportamiento previo era ese de facto).
 * - Nullable para no romper requisitos viejos que no tienen costo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            if (!Schema::hasColumn('requisitos', 'costo_unidad')) {
                $table->enum('costo_unidad', ['UMA', 'PESOS'])
                    ->default('PESOS')
                    ->nullable()
                    ->after('costo_requisito');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            if (Schema::hasColumn('requisitos', 'costo_unidad')) {
                $table->dropColumn('costo_unidad');
            }
        });
    }
};
