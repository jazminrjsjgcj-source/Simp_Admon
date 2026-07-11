<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la distinción Trámite / Servicio a la tabla tramites.
 *
 * Dos columnas nuevas:
 *
 *   naturaleza    ENUM('tramite', 'servicio') DEFAULT 'tramite'
 *     Determina si el registro es un trámite o un servicio municipal.
 *     Todos los registros existentes quedan como 'tramite' automáticamente.
 *
 *   tipo_servicio VARCHAR(200) NULLABLE
 *     Solo se usa cuando naturaleza = 'servicio'. Almacena el tipo específico
 *     de servicio (ej. "Servicio catastral o territorial") de la lista fija
 *     definida en config('punta.tipos_servicio').
 *     Cuando naturaleza = 'tramite', este campo queda NULL y el tipo se lee
 *     de tipo_tramite_id (FK al catálogo editable de tipos de trámite).
 *
 * Se ubican después de nombre_oficial, antes de tipo_tramite_id, para que
 * la lectura de la tabla tenga sentido: nombre → naturaleza → tipo → dependencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->string('naturaleza', 30)
                  ->default('tramite')
                  
                  ->comment('Distingue si el registro es un trámite o un servicio municipal.');

            $table->string('tipo_servicio', 200)
                  ->nullable()
                  
                  ->comment('Tipo de servicio (lista fija LNETB). NULL cuando naturaleza=tramite.');
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->dropColumn(['naturaleza', 'tipo_servicio']);
        });
    }
};
