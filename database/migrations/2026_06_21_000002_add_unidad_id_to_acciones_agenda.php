<?php

/**
 * Agrega la columna unidad_id a la tabla acciones_agenda.
 *
 * El wizard de creación ya capturaba el campo tramite_unidad_id (la unidad
 * administrativa que gestiona el trámite vinculado), pero AgendaService no
 * lo guardaba en la acción. Esta migración añade el campo para que pueda
 * mostrarse en el índice de agenda junto con la dependencia.
 *
 * Registros existentes quedarán con unidad_id = NULL (esperado, no es error).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            // Después de dependencia_id para mantener cohesión semántica.
            $table->foreignId('unidad_id')
                ->nullable()
                
                ->constrained('unidades_administrativas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acciones_agenda', function (Blueprint $table) {
            $table->dropForeign(['unidad_id']);
            $table->dropColumn('unidad_id');
        });
    }
};
