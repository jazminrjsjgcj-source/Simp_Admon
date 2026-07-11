<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug #B5 — Columnas faltantes en `tramites`.
 *
 * Los formularios de trámite (create.blade.php y edit.blade.php) ya envían
 * dos campos hidden que la tabla nunca tuvo, por lo que se perdían en cada
 * guardado:
 *
 *   - sujeto_obligado_id: el titular (sujeto obligado) vigente al momento de
 *     capturar el trámite. Se guarda para conservar el dato histórico, ya que
 *     los titulares cambian con el tiempo (la tabla sujetos_obligados maneja
 *     historial con su campo `activo`).
 *   - enlace_id: el usuario que capturó el trámite. El formulario lo manda
 *     explícito (value="{{ auth()->id() }}"), separado de created_by.
 *
 * edit.blade.php incluso ya intenta leer $tramite->sujeto_obligado_id y
 * $tramite->enlace_id para precargar el formulario; sin estas columnas,
 * siempre obtenía null y caía a los valores por defecto.
 *
 * Ambas nullable + nullOnDelete: los trámites existentes quedan en null sin
 * romper, y borrar un sujeto/usuario no elimina el trámite (solo limpia la FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'sujeto_obligado_id')) {
                $table->foreignId('sujeto_obligado_id')->nullable()
                    ->constrained('sujetos_obligados')->nullOnDelete();
            }
            if (!Schema::hasColumn('tramites', 'enlace_id')) {
                $table->foreignId('enlace_id')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach (['enlace_id', 'sujeto_obligado_id'] as $col) {
                if (Schema::hasColumn('tramites', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
        });
    }
};
