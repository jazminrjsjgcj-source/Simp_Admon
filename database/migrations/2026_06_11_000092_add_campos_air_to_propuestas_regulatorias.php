<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saca del JSON de `justificacion` los tres campos booleanos
     * que el sistema necesita para calcular si una propuesta
     * requiere AIR (Art. 35 LNETB + Art. 71-73 Lineamientos).
     *
     * Los textos descriptivos permanecen en el JSON porque son
     * solo respaldo narrativo, no datos consultables.
     */
    public function up(): void
    {
        Schema::table('propuestas_regulatorias', function (Blueprint $table) {
            // Art. 48 fracción IX Lineamientos:
            // ¿La propuesta genera nuevos costos burocráticos?
            // Criterio 1 del Art. 35 LNETB para requerir AIR.
            $table->boolean('genera_costos_burocraticos')
                ->nullable()
                ->after('poblacion_afectada');

            // Art. 48 fracción VII Lineamientos:
            // ¿La propuesta impacta en comercio o inversión?
            // Criterio 2 del Art. 35 LNETB (impacto en actividad económica).
            $table->boolean('impacta_comercio_inversion')
                ->nullable()
                ->after('genera_costos_burocraticos');

            // Art. 48 fracción VIII Lineamientos:
            // ¿La propuesta crea, modifica o elimina trámites existentes?
            // Complementa el criterio 2 del Art. 35 LNETB.
            $table->boolean('impacta_tramites_existentes')
                ->nullable()
                ->after('impacta_comercio_inversion');
        });
    }

    public function down(): void
    {
        Schema::table('propuestas_regulatorias', function (Blueprint $table) {
            $table->dropColumn([
                'genera_costos_burocraticos',
                'impacta_comercio_inversion',
                'impacta_tramites_existentes',
            ]);
        });
    }
};
