<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a la tabla `acciones_agenda` la columna `indicador_avance`, que
 * corresponde al rubro 18 del documento oficial de Agenda de Simplificación y
 * Digitalización (artículo 29 fracción XI de los LIMNETB).
 *
 * El documento distingue dos indicadores:
 *   - Rubro 17, indicador de cumplimiento → ya cubierto por la columna `indicador`
 *   - Rubro 18, indicador de avance       → esta nueva columna
 *
 * Es nullable: una acción existente sin este dato sigue siendo válida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            if (!Schema::hasColumn('acciones_agenda', 'indicador_avance')) {
                $table->string('indicador_avance', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            if (Schema::hasColumn('acciones_agenda', 'indicador_avance')) {
                $table->dropColumn('indicador_avance');
            }
        });
    }
};
