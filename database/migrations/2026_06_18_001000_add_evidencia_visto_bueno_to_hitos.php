<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupo 3 (#5 + #9) — Evidencia y visto bueno de la revisora en los hitos.
 *
 * Hasta ahora un hito solo tenía `completado` (sí/no): el enlace lo marcaba y
 * listo. El nuevo flujo tiene dos momentos:
 *
 *   1. El ENLACE sube una evidencia (archivo) y marca el hito como cumplido.
 *      → el hito pasa a estado_aprobacion = 'pendiente'.
 *   2. La REVISORA revisa la evidencia y aprueba o rechaza (con motivo).
 *      → 'aprobado' (queda completado de verdad) o 'rechazado' (vuelve al enlace).
 *
 * Columnas nuevas:
 *   - evidencia_archivo (ruta del archivo subido)
 *   - evidencia_nombre  (nombre original, para mostrarlo legible)
 *   - estado_aprobacion (sin_evidencia / pendiente / aprobado / rechazado)
 *   - aprobado_por, fecha_aprobacion (quién y cuándo dio visto bueno)
 *   - motivo_rechazo (texto que escribe la revisora al rechazar)
 *
 * Se conserva `completado` por compatibilidad: un hito se considera completado
 * cuando estado_aprobacion = 'aprobado'. El servicio mantiene ambos en sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hitos_agenda', function (Blueprint $table) {
            if (!Schema::hasColumn('hitos_agenda', 'evidencia_archivo')) {
                $table->string('evidencia_archivo', 500)->nullable()->after('completado_por');
            }
            if (!Schema::hasColumn('hitos_agenda', 'evidencia_nombre')) {
                $table->string('evidencia_nombre', 255)->nullable()->after('evidencia_archivo');
            }
            if (!Schema::hasColumn('hitos_agenda', 'estado_aprobacion')) {
                $table->enum('estado_aprobacion', ['sin_evidencia', 'pendiente', 'aprobado', 'rechazado'])
                    ->default('sin_evidencia')->after('evidencia_nombre');
            }
            if (!Schema::hasColumn('hitos_agenda', 'aprobado_por')) {
                $table->foreignId('aprobado_por')->nullable()->after('estado_aprobacion')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('hitos_agenda', 'fecha_aprobacion')) {
                $table->date('fecha_aprobacion')->nullable()->after('aprobado_por');
            }
            if (!Schema::hasColumn('hitos_agenda', 'motivo_rechazo')) {
                $table->string('motivo_rechazo', 500)->nullable()->after('fecha_aprobacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hitos_agenda', function (Blueprint $table) {
            if (Schema::hasColumn('hitos_agenda', 'aprobado_por')) {
                $table->dropForeign(['aprobado_por']);
            }
            foreach ([
                'evidencia_archivo', 'evidencia_nombre', 'estado_aprobacion',
                'aprobado_por', 'fecha_aprobacion', 'motivo_rechazo',
            ] as $col) {
                if (Schema::hasColumn('hitos_agenda', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
