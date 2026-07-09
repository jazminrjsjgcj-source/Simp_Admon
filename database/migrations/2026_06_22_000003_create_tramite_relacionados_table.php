<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot para el rubro 10.2 del documento oficial ATDT:
 * "¿Con cuáles otros trámites guarda relación?"
 *
 * Antes este dato se guardaba en `tramites.relacionados_detalle` como
 * texto libre (el enlace escribía los nombres a mano). Esta migración
 * introduce una tabla pivot que referencia IDs reales del catálogo,
 * manteniendo `relacionados_detalle` como campo de notas libres para
 * trámites que no están en el catálogo o contexto adicional.
 *
 * La relación es bidireccional pero no simétrica: si A dice que se
 * relaciona con B, eso no implica automáticamente que B diga que se
 * relaciona con A. Cada trámite gestiona sus propios relacionados.
 *
 * La clave UNIQUE evita duplicados: un par (tramite_id, relacionado_id)
 * solo puede existir una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_relacionados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')
                  ->constrained('tramites')
                  ->cascadeOnDelete();
            $table->foreignId('relacionado_id')
                  ->constrained('tramites')
                  ->cascadeOnDelete();
            $table->timestamps();

            // Un trámite no puede citarse a sí mismo como relacionado,
            // y el mismo par no puede repetirse.
            $table->unique(['tramite_id', 'relacionado_id'], 'uq_tramite_relacionado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_relacionados');
    }
};
