<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítem E — Costos variables (no cuantificables).
 *
 * La metodología ATDT distingue dos situaciones donde el monto en pesos NO se
 * puede fijar de antemano porque depende de factores externos (metraje, valor
 * catastral, tarifa por predio, precio de un despacho privado, etc.):
 *
 *   1. El TRÁMITE cuyo pago de derechos es variable (ej. predial). Se marca con
 *      `monto_derechos_variable` y se anota en `monto_derechos_referencia` la
 *      base de cálculo (ej. "tarifa mínima de la tabla municipal"). El costo
 *      directo (CBD) usa el monto capturado como estimación y muestra una nota.
 *
 *   2. El REQUISITO con costo de mercado (ej. plano arquitectónico). Se marca con
 *      `costo_variable`. Su TIEMPO sigue contando en el costo indirecto (CBI),
 *      pero su monto NO se suma al CBD (no es cuantificable de forma objetiva).
 *
 * Las columnas `tiene_costo` y `costo_requisito` ya existían en `requisitos`;
 * aquí solo se agrega `costo_variable`. Todas las columnas son nullable o con
 * default, así que los trámites existentes siguen siendo válidos sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'monto_derechos_variable')) {
                $table->boolean('monto_derechos_variable')->default(false)->after('monto_derechos');
            }
            if (!Schema::hasColumn('tramites', 'monto_derechos_referencia')) {
                $table->string('monto_derechos_referencia', 500)->nullable()->after('monto_derechos_variable');
            }
        });

        Schema::table('requisitos', function (Blueprint $table) {
            if (!Schema::hasColumn('requisitos', 'costo_variable')) {
                $table->boolean('costo_variable')->default(false)->after('tiene_costo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach (['monto_derechos_variable', 'monto_derechos_referencia'] as $col) {
                if (Schema::hasColumn('tramites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('requisitos', function (Blueprint $table) {
            if (Schema::hasColumn('requisitos', 'costo_variable')) {
                $table->dropColumn('costo_variable');
            }
        });
    }
};
