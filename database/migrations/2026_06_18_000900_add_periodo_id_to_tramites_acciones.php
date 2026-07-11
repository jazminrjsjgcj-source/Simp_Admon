<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #12 — Filtrado real por periodo.
 *
 * Hoy la "pill" de periodo en el header es solo informativa: muestra el periodo
 * activo y los días restantes, pero ningún trámite ni acción está realmente
 * ligado a un periodo, así que no se puede filtrar por él.
 *
 * Esta migración agrega `periodo_id` (nullable, con FK) a `tramites` y a
 * `acciones_agenda`. Ambos pertenecen a la Agenda SyD, así que se ligan al
 * periodo de tipo 'agenda_syd'. Es nullable para no romper los registros
 * existentes (que se crearon antes de tener periodo); al asignar nulo en la FK
 * con nullOnDelete, si se borra un periodo los registros no se pierden, solo
 * quedan sin periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'periodo_id')) {
                $table->foreignId('periodo_id')->nullable()
                    ->constrained('periodos')->nullOnDelete();
            }
        });

        Schema::table('acciones_agenda', function (Blueprint $table) {
            if (!Schema::hasColumn('acciones_agenda', 'periodo_id')) {
                $table->foreignId('periodo_id')->nullable()
                    ->constrained('periodos')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (Schema::hasColumn('tramites', 'periodo_id')) {
                $table->dropForeign(['periodo_id']);
                $table->dropColumn('periodo_id');
            }
        });

        Schema::table('acciones_agenda', function (Blueprint $table) {
            if (Schema::hasColumn('acciones_agenda', 'periodo_id')) {
                $table->dropForeign(['periodo_id']);
                $table->dropColumn('periodo_id');
            }
        });
    }
};
