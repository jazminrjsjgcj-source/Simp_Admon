<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a la tabla `tramites` las preguntas de diagnóstico del prototipo
 * de agenda de simplificación/digitalización. Cada pregunta de Sí/No guarda
 * un booleano y, cuando aplica, un texto con el detalle:
 *
 *   - ¿Existen trámites relacionados?      → tiene_relacionados + relacionados_detalle
 *   - ¿Existen procesos redundantes?       → tiene_redundantes  + redundantes_detalle
 *   - ¿Requiere interoperabilidad?         → requiere_interop   + interop_detalle
 *   - Referencia de simplificación previa  → simplificacion_ref
 *   - Detalle del grupo prioritario        → grupo_prioritario_detalle
 *     (el booleano grupo_prioritario ya existía; aquí solo se añade su detalle)
 *
 * Todas son nullable: un trámite existente sin estos datos sigue siendo válido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'tiene_relacionados')) {
                $table->boolean('tiene_relacionados')->default(false);
            }
            if (!Schema::hasColumn('tramites', 'relacionados_detalle')) {
                $table->text('relacionados_detalle')->nullable();
            }
            if (!Schema::hasColumn('tramites', 'tiene_redundantes')) {
                $table->boolean('tiene_redundantes')->default(false);
            }
            if (!Schema::hasColumn('tramites', 'redundantes_detalle')) {
                $table->text('redundantes_detalle')->nullable();
            }
            if (!Schema::hasColumn('tramites', 'requiere_interop')) {
                $table->boolean('requiere_interop')->default(false);
            }
            if (!Schema::hasColumn('tramites', 'interop_detalle')) {
                $table->text('interop_detalle')->nullable();
            }
            if (!Schema::hasColumn('tramites', 'simplificacion_ref')) {
                $table->string('simplificacion_ref', 500)->nullable();
            }
            if (!Schema::hasColumn('tramites', 'grupo_prioritario_detalle')) {
                $table->string('grupo_prioritario_detalle', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach ([
                'tiene_relacionados', 'relacionados_detalle',
                'tiene_redundantes', 'redundantes_detalle',
                'requiere_interop', 'interop_detalle',
                'simplificacion_ref', 'grupo_prioritario_detalle',
            ] as $col) {
                if (Schema::hasColumn('tramites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
