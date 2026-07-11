<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B18 — Rubro 12 del Anexo Agenda Regulatoria: "Trámites y servicios en los que
 * impacta la Propuesta Regulatoria, indicando si crea, modifica o elimina".
 *
 * La tabla propuesta_tramite_impacto ya existía para vincular propuesta↔trámite,
 * pero le faltaba registrar QUÉ tipo de impacto produce. Este campo lo agrega:
 * cada trámite vinculado indica si la propuesta lo crea, lo modifica o lo elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propuesta_tramite_impacto', function (Blueprint $table) {
            // Tipo de impacto sobre el trámite. Nullable para no romper los
            // registros que ya existen sin este dato.
            $table->string('accion', 30)
                ->nullable()
                ;
        });
    }

    public function down(): void
    {
        Schema::table('propuesta_tramite_impacto', function (Blueprint $table) {
            $table->dropColumn('accion');
        });
    }
};
