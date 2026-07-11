<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B18 — Rubros 13 y 14 del Anexo Agenda Regulatoria:
 *   13.0 Acciones de simplificación asociadas a la Propuesta Regulatoria
 *   14.0 Acciones de digitalización asociadas a la Propuesta Regulatoria
 *
 * El anexo oficial indica que estas acciones (que viven en la Agenda SyD como
 * registros propios) deben quedar VINCULADAS a la propuesta regulatoria.
 * Esta tabla pivote relaciona una propuesta con las acciones SyD existentes,
 * distinguiendo si la vinculación es de simplificación o de digitalización.
 *
 *   - propuesta_id     → la propuesta regulatoria
 *   - accion_agenda_id → la acción de Agenda SyD vinculada (acciones_agenda)
 *   - tipo             → 'simplificacion' | 'digitalizacion'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propuesta_accion_syd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propuesta_id')
                ->constrained('propuestas_regulatorias')
                ->cascadeOnDelete();
            $table->foreignId('accion_agenda_id')
                ->constrained('acciones_agenda')
                ->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->timestamps();

            // Una misma acción no debe vincularse dos veces con el mismo tipo
            // a la misma propuesta.
            $table->unique(['propuesta_id', 'accion_agenda_id', 'tipo'], 'propuesta_accion_syd_unico');
            $table->index('propuesta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuesta_accion_syd');
    }
};
