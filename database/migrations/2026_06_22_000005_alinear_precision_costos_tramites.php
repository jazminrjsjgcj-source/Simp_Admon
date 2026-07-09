<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige una inconsistencia de esquema en las columnas de costo burocrático.
 *
 * Problema de raíz:
 * El costo burocrático total anual (CBT) se persiste en dos lugares:
 *   - tramite_costos_burocraticos.cbt_total_anual  -> decimal(16, 2)
 *   - tramites.cbt_total (columna legacy)           -> decimal(14, 2)
 *
 * El cálculo cabe en la tabla nueva (16 dígitos) pero desborda en la columna
 * legacy (14 dígitos) cuando un trámite tiene un volumen anual alto y plazos
 * largos. El resultado es un error "Out of range value for column 'cbt_total'"
 * que rompe el recálculo, aunque el valor sea aritméticamente correcto.
 *
 * Las columnas intermedias legacy (cbd_directo, cbi_indirecto, cbu_unitario)
 * comparten el mismo riesgo: son decimal(14, 2) pero alimentan un total que
 * puede crecer al multiplicarse por el volumen anual.
 *
 * Solución:
 * Unificar la precisión de las columnas de costo legacy en `tramites` con la
 * que ya usa la tabla nueva, llevando los acumuladores a decimal(16, 2). Así
 * ambas representaciones del mismo dato tienen idéntica capacidad y el recálculo
 * deja de desbordar.
 */
return new class extends Migration
{
    /** Columnas legacy de acumulación que se alinean a decimal(16, 2). */
    private const COLUMNAS_A_AMPLIAR = [
        'cbd_directo',
        'cbi_indirecto',
        'cbu_unitario',
        'cbt_total',
    ];

    private const PRECISION_NUEVA = 16;
    private const PRECISION_ANTERIOR = 14;
    private const ESCALA = 2;

    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach (self::COLUMNAS_A_AMPLIAR as $columna) {
                $table->decimal($columna, self::PRECISION_NUEVA, self::ESCALA)
                    ->nullable()
                    ->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            foreach (self::COLUMNAS_A_AMPLIAR as $columna) {
                $table->decimal($columna, self::PRECISION_ANTERIOR, self::ESCALA)
                    ->nullable()
                    ->change();
            }
        });
    }
};
