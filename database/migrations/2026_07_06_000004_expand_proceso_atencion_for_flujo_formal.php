<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 del Digitalizador — Levantamiento formal del flujo.
 *
 * Expande la tabla proceso_atencion con campos adicionales para capturar
 * un flujo estructurado completo: actor, tipo de paso enriquecido,
 * duración estimada, requisitos de entrada/salida, y canal digital.
 *
 * También agrega campos de revisión del flujo al trámite para que el
 * enlace pueda enviar el levantamiento a revisión y el revisor aprobarlo.
 *
 * Columnas actuales de proceso_atencion (previas a esta migración):
 *   id, tramite_id, tipo (atencion/resolucion), paso, subpaso, accion,
 *   detalle, area, created_at, updated_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            // Tipo de paso enriquecido (más allá de atencion/resolucion)
            if (!Schema::hasColumn('proceso_atencion', 'tipo_paso')) {
                $table->string('tipo_paso', 30)->default('paso')->after('area');
                // Valores: paso, decision, inspeccion, pago, resolutivo,
                //          notificacion, espera, firma, entrega
            }

            // Actor que ejecuta este paso
            if (!Schema::hasColumn('proceso_atencion', 'actor')) {
                $table->string('actor', 200)->nullable()->after('tipo_paso');
                // Ej: "Ciudadano", "Ventanilla", "Director", "Sistema"
            }

            // Duración estimada del paso
            if (!Schema::hasColumn('proceso_atencion', 'duracion_estimada')) {
                $table->string('duracion_estimada', 100)->nullable()->after('actor');
                // Ej: "15 min", "3 días hábiles", "inmediato"
            }

            // ¿Este paso se puede digitalizar?
            if (!Schema::hasColumn('proceso_atencion', 'es_digital')) {
                $table->boolean('es_digital')->default(false)->after('duracion_estimada');
            }

            // Requisito de entrada (qué necesita el paso para ejecutarse)
            if (!Schema::hasColumn('proceso_atencion', 'entrada')) {
                $table->string('entrada', 500)->nullable()->after('es_digital');
            }

            // Resultado de salida (qué produce el paso)
            if (!Schema::hasColumn('proceso_atencion', 'salida')) {
                $table->string('salida', 500)->nullable()->after('entrada');
            }

            // Observaciones del levantamiento
            if (!Schema::hasColumn('proceso_atencion', 'notas')) {
                $table->text('notas')->nullable()->after('salida');
            }

            // Orden explícito (para reordenar sin depender de paso/subpaso)
            if (!Schema::hasColumn('proceso_atencion', 'orden')) {
                $table->unsignedSmallInteger('orden')->default(0)->after('notas');
            }
        });

        // Campos de revisión del flujo en tramites
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'flujo_enviado_en')) {
                $table->timestamp('flujo_enviado_en')->nullable()->after('digitalizacion_origen');
            }
            if (!Schema::hasColumn('tramites', 'flujo_aprobado_en')) {
                $table->timestamp('flujo_aprobado_en')->nullable()->after('flujo_enviado_en');
            }
            if (!Schema::hasColumn('tramites', 'flujo_aprobado_por')) {
                $table->foreignId('flujo_aprobado_por')->nullable()->after('flujo_aprobado_en')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            $cols = ['tipo_paso', 'actor', 'duracion_estimada', 'es_digital', 'entrada', 'salida', 'notas', 'orden'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('proceso_atencion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('tramites', function (Blueprint $table) {
            $cols = ['flujo_enviado_en', 'flujo_aprobado_en', 'flujo_aprobado_por'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tramites', $col)) {
                    if ($col === 'flujo_aprobado_por') {
                        $table->dropForeign(['flujo_aprobado_por']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
