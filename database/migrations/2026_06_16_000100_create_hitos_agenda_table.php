<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla hitos_agenda: registra el avance de cada acción de agenda por hitos.
 *
 * Cada acción de agenda tiene una lista de hitos definida según su tipo de
 * acción (ver config/hitos.php). Cada fila de esta tabla es un hito concreto
 * de una acción, con su orden, su estado (completado o no) y, cuando se marca,
 * la fecha y el usuario que lo completó.
 *
 * El "Diagnóstico" siempre es el primer hito y se marca como completado en el
 * momento en que se registra la acción (ver HitoAgendaService::sembrarHitos).
 *
 * El porcentaje de avance se calcula como: hitos completados / total de hitos.
 *
 * Se usa `string` para clave_accion y nombre (no enum) por compatibilidad con
 * una futura migración a PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hitos_agenda')) {
            Schema::create('hitos_agenda', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accion_agenda_id')->constrained('acciones_agenda')->cascadeOnDelete();
                $table->unsignedTinyInteger('orden')->default(1);
                $table->string('clave', 100);          // identificador del hito dentro de su tipo de acción
                $table->string('nombre', 255);          // nombre legible del hito
                $table->boolean('completado')->default(false);
                $table->date('fecha_completado')->nullable();
                $table->foreignId('completado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['accion_agenda_id', 'orden']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hitos_agenda');
    }
};
