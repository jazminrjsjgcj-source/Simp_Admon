<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a la tabla `tramites` la columna `tipo_relacion`, que corresponde al
 * rubro 10.1 del documento oficial de Agenda de Simplificación y Digitalización
 * (artículo 29 fracción VI de los LIMNETB).
 *
 * Cuando un trámite guarda relación con otros (tiene_relacionados = true), este
 * campo especifica la forma de la relación, según los tres supuestos oficiales:
 *   - naturaleza            (se resuelven de forma similar/igual)
 *   - secuencia             (uno se requiere para iniciar el otro, distinta materia)
 *   - dependencia_funcional (uno se requiere para iniciar el otro, misma materia)
 *
 * Es nullable: un trámite sin relación o sin este dato sigue siendo válido.
 * El detalle de cuáles trámites (rubro 10.2) ya se guarda en relacionados_detalle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'tipo_relacion')) {
                $table->string('tipo_relacion', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (Schema::hasColumn('tramites', 'tipo_relacion')) {
                $table->dropColumn('tipo_relacion');
            }
        });
    }
};
