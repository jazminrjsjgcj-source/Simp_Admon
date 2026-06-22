<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7 — Citas en agenda regulatoria (Flujo 1 de 2).
 *
 * Una propuesta regulatoria puede afectar uno o varios trámites existentes.
 * El jurídico declara ese impacto al llenar la propuesta: indica qué trámite,
 * opcionalmente qué requisito concreto, y qué artículo o fracción de la
 * propuesta lo modifica.
 *
 * Flujo 2 (los requisitos citan la regulación ya publicada como fundamento
 * legal) usa la tabla requisito_regulacion que ya existe — no necesita migración.
 *
 * Esta tabla es propuesta_tramite_impacto:
 *   - propuesta_id  → la propuesta regulatoria que declara el impacto
 *   - tramite_id    → el trámite afectado
 *   - requisito_id  → el requisito concreto afectado (nullable, puede ser el
 *                     trámite en general sin señalar un requisito específico)
 *   - articulo_fraccion → qué artículo/fracción de la propuesta lo afecta
 *   - descripcion   → nota libre sobre el tipo de cambio esperado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propuesta_tramite_impacto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propuesta_id')
                ->constrained('propuestas_regulatorias')
                ->cascadeOnDelete();
            $table->foreignId('tramite_id')
                ->constrained('tramites')
                ->cascadeOnDelete();
            $table->foreignId('requisito_id')
                ->nullable()
                ->constrained('requisitos')
                ->nullOnDelete();
            $table->string('articulo_fraccion', 200)->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->timestamps();

            $table->index(['propuesta_id', 'tramite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuesta_tramite_impacto');
    }
};
