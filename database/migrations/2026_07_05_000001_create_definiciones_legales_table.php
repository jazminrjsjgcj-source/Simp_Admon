<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definiciones legales extraídas automáticamente del articulado ya
 * estructurado en PUNTA (tabla regulacion_nodos).
 *
 * Esta tabla NO se llena a mano ni depende de una ley específica. Se puebla
 * ejecutando DefinitionExtractorService sobre TODAS las regulaciones que ya
 * estén estructuradas: el servicio detecta automáticamente los artículos que
 * dicen "se entenderá por", "para efectos de", etc., y extrae cada término
 * con su definición desde las fracciones hijas de ese artículo.
 *
 * Cada fila apunta siempre a un regulacion_id y nodo_id REALES que ya
 * existen en la base de datos — nunca se copia texto de una fuente externa.
 * Esto permite enlazar directo al artículo original y mostrar el texto
 * exacto de donde salió la definición.
 *
 * No hay restricción de unicidad sobre `termino`: dos regulaciones distintas
 * pueden definir la misma palabra de forma diferente (por ejemplo, "Enlace"
 * puede definirse distinto en dos reglamentos separados). Guardar ambas
 * definiciones por separado, en vez de forzar una sola, permite que el
 * buscador muestre todas las fuentes reales que existan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definiciones_legales', function (Blueprint $table) {
            $table->id();

            // Término tal como aparece en la regulación (con mayúsculas y
            // acentos originales, para mostrarlo). La búsqueda por término
            // se hace normalizando en el servicio, no en la base de datos.
            $table->string('termino', 200);

            // Texto completo de la definición, tal como aparece en la
            // regulación de origen.
            $table->text('definicion');

            // De dónde salió: apunta a una regulación y un nodo reales.
            $table->foreignId('regulacion_id')->constrained('regulaciones')->cascadeOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('regulacion_nodos')->nullOnDelete();

            // Identificadores legibles para mostrar la fuente sin tener que
            // recorrer el árbol de nodos cada vez (ej. "Artículo 4", "II").
            $table->string('articulo', 60)->nullable();
            $table->string('fraccion', 60)->nullable();

            // Nombre de la regulación al momento de extraer, guardado aquí
            // para no depender de un JOIN en cada búsqueda. Es una copia de
            // conveniencia, no la fuente de verdad (esa es regulacion_id).
            $table->string('fuente', 500);

            // Permite desactivar una definición mal extraída (por ejemplo,
            // si el patrón "Término: definición" se detectó donde no debía)
            // sin necesidad de borrarla. Un jurídico puede revisar y corregir.
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Búsqueda por término es la operación más frecuente de esta tabla.
            $table->index('termino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('definiciones_legales');
    }
};
