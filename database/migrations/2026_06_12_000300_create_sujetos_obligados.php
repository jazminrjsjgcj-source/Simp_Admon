<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `sujetos_obligados`.
 *
 * Un sujeto obligado es la persona titular/responsable de una dependencia
 * (su director o cabeza). Cada dependencia tiene un solo sujeto obligado
 * vigente. En el formulario de propuesta regulatoria reemplaza al antiguo
 * campo "Enlace de Simplificación": ahora se muestra automáticamente el
 * titular de la dependencia del usuario.
 *
 * La relación es por dependencia_id. Se guarda en tabla separada (en vez
 * de columnas en `dependencias`) para poder llevar historial de titulares
 * a futuro: dar de baja el activo y dar de alta el nuevo sin perder el
 * registro anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sujetos_obligados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('cargo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Acelera la búsqueda del titular activo de una dependencia.
            $table->index(['dependencia_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sujetos_obligados');
    }
};
