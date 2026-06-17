<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla intermedia requisito_regulacion: liga cada requisito de un trámite con
 * una o varias regulaciones que le dan fundamento. Cada fila guarda además el
 * artículo o fracción citado de esa regulación.
 *
 * Un requisito puede tener varias regulaciones (relación uno-a-muchos hacia
 * esta tabla), por eso es una tabla aparte y no columnas en `requisitos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('requisito_regulacion')) {
            Schema::create('requisito_regulacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
                $table->foreignId('regulacion_id')->nullable()->constrained('regulaciones')->nullOnDelete();
                $table->string('articulo_fraccion')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requisito_regulacion');
    }
};
