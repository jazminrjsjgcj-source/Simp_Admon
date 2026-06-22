<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug #B4 — Columnas faltantes en `tramite_derechos`.
 *
 * El servicio TramiteService::sincronizarDerechos() ya intenta guardar dos
 * datos por cada concepto de derecho que la tabla nunca tuvo:
 *
 *   - unidad:      si el monto está en 'pesos' o en 'UMA'. Sin esta columna,
 *                  no se podía recordar que un derecho estaba en UMA, y al
 *                  recargar siempre se asumía pesos (perdiendo la conversión).
 *   - es_variable: si el monto del derecho es variable (no fijo).
 *
 * El modelo TramiteDerecho incluso ya declara el cast 'es_variable' => 'boolean'
 * y las constantes UNIDAD_PESOS / UNIDAD_UMA, pero faltaba el respaldo en BD.
 *
 * Defaults elegidos para que los derechos ya existentes queden con su
 * comportamiento actual de facto: 'pesos' y no variable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_derechos', function (Blueprint $table) {
            if (!Schema::hasColumn('tramite_derechos', 'unidad')) {
                $table->string('unidad', 10)->default('pesos')->after('monto');
            }
            if (!Schema::hasColumn('tramite_derechos', 'es_variable')) {
                $table->boolean('es_variable')->default(false)->after('unidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramite_derechos', function (Blueprint $table) {
            foreach (['unidad', 'es_variable'] as $col) {
                if (Schema::hasColumn('tramite_derechos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
