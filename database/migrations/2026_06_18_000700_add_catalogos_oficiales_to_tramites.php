<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ítem F — Catálogos oficiales del instrumento ATDT que faltaban en el trámite.
 *
 *   1. acciones_simplificacion (JSON): selección múltiple de las 10 acciones
 *      oficiales del rubro 14 de la sección Simplificación.
 *   2. grupos_atencion (JSON): selección múltiple de los grupos de atención
 *      prioritaria del rubro 9 (lista oficial del instrumento).
 *   3. etapa_operacion (string): APERTURA / OPERACIÓN / CIERRE. Solo aplica a
 *      personas morales; en la vista se muestra cuando dirigido_a es moral o
 *      ambas. La columna es nullable para no obligar a los trámites de persona
 *      física.
 *   4. Se agrega el valor 'anios' al enum plazo_resolucion_unidad (antes solo
 *      habiles/naturales/meses). El costeo homologa años a 365 días.
 *
 * Las columnas JSON son nullable; los trámites existentes siguen válidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'acciones_simplificacion')) {
                $table->json('acciones_simplificacion')->nullable()->after('tipo_relacion');
            }
            if (!Schema::hasColumn('tramites', 'grupos_atencion')) {
                $table->json('grupos_atencion')->nullable()->after('acciones_simplificacion');
            }
            if (!Schema::hasColumn('tramites', 'etapa_operacion')) {
                $table->string('etapa_operacion', 20)->nullable()->after('grupos_atencion');
            }
        });

        // Agregar 'anios' al enum de unidad de plazo. Se hace con SQL crudo porque
        // modificar un enum en MySQL no requiere doctrine/dbal por esta vía.
        DB::statement("ALTER TABLE tramites MODIFY COLUMN plazo_resolucion_unidad
            ENUM('habiles','naturales','meses','anios') NULL DEFAULT 'habiles'");
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach (['acciones_simplificacion', 'grupos_atencion', 'etapa_operacion'] as $col) {
                if (Schema::hasColumn('tramites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Revertir el enum a sus tres valores originales. Cualquier trámite con
        // 'anios' debe ajustarse antes de revertir (se normaliza a 'meses').
        DB::statement("UPDATE tramites SET plazo_resolucion_unidad = 'meses' WHERE plazo_resolucion_unidad = 'anios'");
        DB::statement("ALTER TABLE tramites MODIFY COLUMN plazo_resolucion_unidad
            ENUM('habiles','naturales','meses') NULL DEFAULT 'habiles'");
    }
};
