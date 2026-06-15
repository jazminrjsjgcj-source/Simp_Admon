<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `tramite_derechos`.
 *
 * Un trámite puede tener varios conceptos de "pago de derechos": cobros
 * fiscales ligados al trámite (ej. "Derecho de inspección: $250"), que son
 * INDEPENDIENTES del costo público del trámite. Un trámite puede ser
 * gratuito (costo $0) y aun así tener conceptos de derechos por pagar.
 *
 * Cada fila es un concepto con su monto. Se guarda en tabla relacional
 * (no como JSON) para poder consultar y reportar: cuánto se cobra por
 * cada concepto, qué trámites lo incluyen, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_derechos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('concepto');
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();

            // Acelera traer los derechos de un trámite.
            $table->index('tramite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_derechos');
    }
};
