<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 del módulo Digitalizador — tablas de cimiento.
 *
 * Crea las tablas necesarias para el flujo completo:
 *   Trámite → Levantamiento → Reingeniería → Firmas → Diagrama → Descarga → Digitalización
 *
 * También agrega campos de estado de flujo y digitalización a la tabla
 * `tramites` existente, para que la Biblioteca del Digitalizador pueda
 * consultar el estado de cada trámite/servicio sin tablas intermedias.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Campos de estado en tramites ──────────────────────────────
        // No se crea tabla nueva: el trámite ya existe, solo se le agregan
        // campos de estado para el flujo de digitalización.
        Schema::table('tramites', function (Blueprint $table) {
            // Estado del levantamiento del flujo (AS-IS)
            $table->string('flujo_estado', 30)->default('sin_flujo')->after('estatus');
            // Estado de la digitalización
            $table->string('digitalizacion_estado', 40)->default('no_iniciada')->after('flujo_estado');
            // Origen de la digitalización (null = no aplica todavía)
            $table->string('digitalizacion_origen', 20)->nullable()->after('digitalizacion_estado');

            $table->index('flujo_estado');
            $table->index('digitalizacion_estado');
        });

        // ── 2. Reingenierías ─────────────────────────────────────────────
        // Cada reingeniería es una versión del flujo TO-BE de un trámite.
        // Se vincula opcionalmente a una acción de agenda.
        Schema::create('reingenierias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('agenda_accion_id')->nullable()->constrained('acciones_agenda')->nullOnDelete();

            $table->enum('origen', ['agenda', 'directa'])->default('agenda');
            $table->unsignedSmallInteger('version')->default(1);

            // Estado del flujo de reingeniería
            $table->string('estado', 30)->default('en_reingenieria');

            // Justificación (obligatoria cuando origen = directa)
            $table->string('motivo_directa', 60)->nullable();
            $table->text('justificacion')->nullable();
            $table->string('documento_soporte')->nullable();
            $table->string('area_solicitante')->nullable();
            $table->date('fecha_limite')->nullable();

            // Flujo TO-BE estructurado (JSON con fases, pasos, actores, decisiones)
            $table->json('flujo_to_be')->nullable();

            // Hash de la versión firmada (se calcula al momento de la firma)
            $table->string('hash_reingenieria', 128)->nullable();
            $table->timestamp('firmado_en')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index(['tramite_id', 'version']);
        });

        // ── 3. Diagramas ─────────────────────────────────────────────────
        // Cada diagrama se genera a partir de una reingeniería firmada.
        Schema::create('diagramas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('reingenieria_id')->constrained('reingenierias')->cascadeOnDelete();

            $table->enum('tipo_diagrama', ['as_is', 'to_be'])->default('to_be');

            // Contenido generado
            $table->mediumText('contenido_mermaid')->nullable();
            $table->mediumText('contenido_drawio_xml')->nullable();

            $table->string('hash_diagrama', 128)->nullable();
            $table->string('estado', 30)->default('sin_diagrama');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('estado');
        });

        // ── 4. Descargas de diagramas (bitácora) ─────────────────────────
        Schema::create('descargas_diagrama', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagrama_id')->constrained('diagramas')->cascadeOnDelete();
            $table->foreignId('reingenieria_id')->constrained('reingenierias')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('formato', ['pdf', 'jpg', 'png', 'svg', 'drawio']);
            $table->string('hash_archivo_generado', 128)->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descargas_diagrama');
        Schema::dropIfExists('diagramas');
        Schema::dropIfExists('reingenierias');

        Schema::table('tramites', function (Blueprint $table) {
            $table->dropIndex(['flujo_estado']);
            $table->dropIndex(['digitalizacion_estado']);
            $table->dropColumn(['flujo_estado', 'digitalizacion_estado', 'digitalizacion_origen']);
        });
    }
};
