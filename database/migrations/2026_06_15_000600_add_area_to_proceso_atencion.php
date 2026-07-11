<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `area` a la tabla `proceso_atencion`.
 *
 * Los rubros 12 y 13 del documento oficial de Agenda SyD piden describir el
 * proceso de atención y el de resolución POR PASOS, donde cada paso indica:
 *   - paso     (número de orden — ya existía)
 *   - accion   (nombre del paso — ya existía)
 *   - detalle  (qué se hace en ese paso — ya existía)
 *   - area     (área que interviene en ese paso — se agrega aquí)
 *
 * El campo `tipo` (atencion/resolucion) ya distingue ambos procesos.
 * `area` es nullable: un paso sin área asignada sigue siendo válido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            if (!Schema::hasColumn('proceso_atencion', 'area')) {
                $table->string('area', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            if (Schema::hasColumn('proceso_atencion', 'area')) {
                $table->dropColumn('area');
            }
        });
    }
};
