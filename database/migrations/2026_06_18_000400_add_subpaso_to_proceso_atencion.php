<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `subpaso` a proceso_atencion para permitir pasos dentro
 * de un paso, numerados como 1.1, 1.2, etc.
 *
 * - subpaso = 0  → paso principal (se muestra como "1", "2", "3"...).
 * - subpaso = 1+ → subpaso del paso (se muestra como "1.1", "1.2"...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            if (!Schema::hasColumn('proceso_atencion', 'subpaso')) {
                $table->unsignedTinyInteger('subpaso')->default(0)->after('paso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proceso_atencion', function (Blueprint $table) {
            if (Schema::hasColumn('proceso_atencion', 'subpaso')) {
                $table->dropColumn('subpaso');
            }
        });
    }
};
