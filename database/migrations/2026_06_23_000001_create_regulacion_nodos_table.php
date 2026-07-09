<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Árbol jerárquico del articulado de una regulación (editor del Jurídico).
 *
 * Cada fila es un nodo: capítulo, título, sección, artículo, fracción, inciso o
 * párrafo. La jerarquía se modela con parent_id (auto-referencia) y el orden
 * entre hermanos con la columna `orden`. Un artículo derogado NO se borra: se
 * marca estado = derogado y permanece en su lugar, conservando los huecos de
 * numeración (p. ej. 93 -> 96 cuando 94 y 95 están derogados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulacion_nodos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regulacion_id')->constrained('regulaciones')->cascadeOnDelete();

            // Padre dentro del árbol. Null = nodo raíz de la regulación.
            $table->foreignId('parent_id')->nullable()->constrained('regulacion_nodos')->cascadeOnDelete();

            // Tipo de nodo: capitulo, titulo, seccion, articulo, fraccion, inciso, parrafo.
            $table->string('tipo', 20);

            // Etiqueta del numerador legible: "III", "1", "a", "Capítulo II".
            $table->string('numero', 60)->nullable();

            // Cuerpo del nodo (texto del artículo, de la fracción, del párrafo...).
            $table->text('texto')->nullable();

            // Posición entre hermanos (mismo parent_id). Define el orden de lectura.
            $table->unsignedInteger('orden')->default(0);

            // Estado del nodo: vigente (default) o derogado. El derogado se
            // conserva visible, tachado, en su lugar.
            $table->string('estado', 12)->default('vigente');

            // Referencia de la derogación, si aplica ("Reforma DOF 12/03/2024").
            $table->string('derogado_nota', 255)->nullable();

            $table->timestamps();

            // Recorrer hijos de un nodo en orden es la operación más frecuente.
            $table->index(['regulacion_id', 'parent_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulacion_nodos');
    }
};
