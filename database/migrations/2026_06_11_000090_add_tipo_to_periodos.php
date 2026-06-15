<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columna `tipo` a la tabla periodos.
 *
 * Reglas de negocio:
 *   - tipo = 'agenda_syd': periodo semestral (6 meses), solo 1 activo a la vez
 *   - tipo = 'agenda_regulatoria': periodo anual (12 meses), solo 1 activo a la vez
 *   - Puede haber 2 periodos activos simultáneos si son de tipo diferente
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->string('tipo', 30)->default('agenda_syd')->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
