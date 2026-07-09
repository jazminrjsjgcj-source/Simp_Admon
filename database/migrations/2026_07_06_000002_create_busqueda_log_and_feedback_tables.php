<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea las tablas de bitácora de búsquedas y feedback de resultados.
 *
 * Estas tablas NO afectan el rendimiento del buscador: el log es un
 * INSERT de ~500 bytes sin índices pesados, y el feedback solo se
 * escribe cuando el usuario da clic en 👍/👎 (raramente).
 *
 * ── Capa 1: busqueda_log ────────────────────────────────────────────
 * Registra cada búsqueda con su consulta, filtros, resultados y tiempo.
 * Es el dataset principal para entrenar un modelo de ranking futuro:
 *   - ¿Qué busca la gente? → consulta
 *   - ¿Qué filtra? → regulacion_ids, tipos
 *   - ¿Encuentra algo? → total_resultados
 *   - ¿El sistema es rápido? → tiempo_ms
 *   - ¿Le sirvió la respuesta destacada? → tiene_destacada
 *   - ¿Qué resultado abrió? → resultado_clickeado_tipo/id (via JS)
 *
 * ── Capa 2: busqueda_feedback ───────────────────────────────────────
 * Registra la señal de relevancia: ¿este resultado sirvió o no?
 * Es el label de entrenamiento para un modelo supervisado:
 *   - consulta + resultado + util=true  → "este par es relevante"
 *   - consulta + resultado + util=false → "este par NO es relevante"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busqueda_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // La consulta tal como la escribió el usuario
            $table->string('consulta', 500);

            // Filtros aplicados (null = sin filtro)
            $table->json('regulacion_ids')->nullable();
            $table->json('tipos')->nullable();

            // Resultado de la búsqueda
            $table->string('modo', 20)->default('completo');      // completo|enfocado|filtrado
            $table->unsignedSmallInteger('total_resultados')->default(0);
            $table->unsignedSmallInteger('tiempo_ms')->default(0);
            $table->boolean('tiene_destacada')->default(false);

            // Resultado que el usuario abrió (se llena async vía JS)
            $table->string('resultado_clickeado_tipo', 20)->nullable(); // articulo|regulacion|tramite|...
            $table->unsignedBigInteger('resultado_clickeado_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Índices para análisis posterior — no afectan la escritura
            $table->index('user_id');
            $table->index('created_at');
            $table->index('consulta');
        });

        Schema::create('busqueda_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('busqueda_log_id')->nullable()->constrained('busqueda_log')->nullOnDelete();

            // Contexto de la búsqueda que produjo este resultado
            $table->string('consulta', 500);

            // El resultado evaluado
            $table->string('tipo_resultado', 20);                // articulo|regulacion|tramite|requisito|fundamento|agenda
            $table->unsignedBigInteger('resultado_id');          // ID del registro en su tabla de origen
            $table->string('titulo_resultado', 500)->nullable(); // Para contexto si el registro se borra después

            // La señal
            $table->boolean('util');                              // true = 👍, false = 👎

            $table->timestamp('created_at')->useCurrent();

            // Índices para análisis
            $table->index('user_id');
            $table->index(['tipo_resultado', 'resultado_id']);
            $table->index('consulta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busqueda_feedback');
        Schema::dropIfExists('busqueda_log');
    }
};
