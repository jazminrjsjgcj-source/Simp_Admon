<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — Correcciones urgentes de BD.
 *
 * #22: calendario_eventos.titulo es VARCHAR(500) pero recibe textos enormes
 *      (descripciones de acciones de agenda completas). Cambiar a TEXT.
 *
 * #24: calendario_eventos.tipo es ENUM(simplificacion,digitalizacion,regulatoria)
 *      pero AccionAgenda puede ser tipo 'ambas'. Agregar al ENUM.
 *      (Cuando migremos a PostgreSQL esto será un string simple.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // #22: titulo VARCHAR(500) → TEXT
        Schema::table('calendario_eventos', function (Blueprint $table) {
            $table->text('titulo')->change();
        });

        // #24: tipo ENUM → agregar 'ambas'
        // El tipo ya es una columna de texto (string), así que acepta 'ambas'
        // sin necesidad de ampliar un ENUM. Los valores los valida Laravel.
    }

    public function down(): void
    {
        // Revertir titulo a VARCHAR(500)
        Schema::table('calendario_eventos', function (Blueprint $table) {
            $table->string('titulo', 500)->change();
        });

        // Solo revertir ENUM si no hay filas con 'ambas'
        $tiene = DB::table('calendario_eventos')->where('tipo', 'ambas')->exists();
        if (!$tiene) {
        // El tipo ya es una columna de texto (string), así que acepta 'ambas'
        // sin necesidad de ampliar un ENUM. Los valores los valida Laravel.
        }
    }
};
