<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diccionario de conceptos jurídico-administrativos de PUNTA.
 *
 * A diferencia de un glosario legal genérico, cada concepto aquí apunta a
 * una TABLA PROPIA de PUNTA (tabla_preferente) donde ya existe el dato real
 * — no a una ley externa específica. Esto es intencional: PUNTA no depende
 * de que ninguna ley en particular esté cargada en el sistema. Si el
 * usuario pregunta por "costo", el diccionario le dice al buscador que
 * consulte primero la tabla `requisitos` (que ya tiene columnas de costo),
 * sin importar cuáles regulaciones existan en ese momento.
 *
 * Esta tabla se puebla con DiccionarioJuridicoSeeder, con un catálogo
 * inicial pequeño (7 conceptos) que se puede ampliar después sin tocar
 * código — solo agregando filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busqueda_diccionario_juridico', function (Blueprint $table) {
            $table->id();

            // Término normalizado (minúsculas, sin acentos) para comparar
            // contra la consulta ya normalizada del usuario.
            $table->string('termino', 100)->unique();

            // Clasificación del concepto: determina cómo se interpreta.
            //   concepto    -> se busca su definición (ej. "servicio", "trámite")
            //   dato        -> se busca un valor estructurado (ej. "costo", "plazo")
            //   herramienta -> se busca un módulo o proceso (ej. "agenda")
            //   rol         -> se busca un puesto o responsable (ej. "enlace")
            $table->string('tipo_concepto', 30);

            // Nombre de la tabla propia de PUNTA donde conviene buscar
            // primero este concepto. No es una ley externa — es una tabla
            // real de este proyecto (requisitos, tramites, fundamento_juridico,
            // definiciones_legales, acciones_agenda).
            $table->string('tabla_preferente', 100);

            // Términos relacionados, para que la búsqueda los tenga en cuenta
            // como contexto (no como sinónimos exactos — esa capa es la
            // Fase 2 de la especificación, todavía no se construye aquí).
            $table->json('relacionados')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busqueda_diccionario_juridico');
    }
};
