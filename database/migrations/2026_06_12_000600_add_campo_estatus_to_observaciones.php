<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla observaciones para la corrección #18:
 *
 * - `campo`: a qué campo específico se liga la observación (ej.
 *   'nombre_oficial', 'fundamento_juridico'). Antes solo existía `seccion`;
 *   ahora una observación puede apuntar a un campo concreto dentro de la
 *   sección, para que el enlace sepa exactamente qué corregir.
 *
 * - `estatus`: estatus rico de la observación (pendiente, en_atencion,
 *   atendida, reabierta, validada). Reemplaza en la práctica al booleano
 *   `atendida`, que se conserva por compatibilidad y se mantiene en sync.
 *
 * Las observaciones existentes se migran: las atendidas pasan a 'atendida',
 * las demás a 'pendiente'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('observaciones', 'campo')) {
                $table->string('campo', 100)->nullable();
            }
            if (!Schema::hasColumn('observaciones', 'estatus')) {
                $table->string('estatus', 20)->default('pendiente');
            }
        });

        // Pone el estatus inicial según el booleano atendida que ya existía.
        DB::table('observaciones')->where('atendida', true)->update(['estatus' => 'atendida']);
        DB::table('observaciones')->where('atendida', false)->update(['estatus' => 'pendiente']);
    }

    public function down(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            if (Schema::hasColumn('observaciones', 'campo')) {
                $table->dropColumn('campo');
            }
            if (Schema::hasColumn('observaciones', 'estatus')) {
                $table->dropColumn('estatus');
            }
        });
    }
};
