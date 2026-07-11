<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega requisitos.dias_estimados.
 *
 * La columna faltaba en la base, pero el código la usa en todas partes: está en
 * el $fillable del modelo Requisito, la escribe TramiteService al guardar, la lee
 * CostoBurocraticoService para el cálculo del tiempo, y aparece en las vistas de
 * alta, edición y detalle. Al insertar un requisito, la base respondía
 * "column dias_estimados does not exist".
 *
 * Va junto a horas_estimadas y minutos_estimados, que sí existían: las tres
 * componen el tiempo de obtención de un requisito (días + horas + minutos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            if (! Schema::hasColumn('requisitos', 'dias_estimados')) {
                $table->unsignedInteger('dias_estimados')->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            if (Schema::hasColumn('requisitos', 'dias_estimados')) {
                $table->dropColumn('dias_estimados');
            }
        });
    }
};
