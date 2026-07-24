<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caché de las clasificaciones de la IA del detector de catálogos, por hash del
 * contenido del artículo.
 *
 * ── Qué problema resuelve ────────────────────────────────────────────
 *
 * El detector pregunta a la IA artículo por artículo. Al re-estructurar una ley,
 * los nodos se recrean desde cero y el detector volvía a preguntarle TODO a la
 * IA: lento (una corrida tardó casi dos horas) y no determinista (una ley marcó
 * bien a mano y mal dentro del job).
 *
 * Con esta caché, la clasificación se guarda por hash del texto del artículo. Si
 * el texto no cambió, se reusa la respuesta anterior sin llamar a la IA: rápido y
 * determinista. Solo los artículos nuevos o modificados pagan una llamada.
 *
 * ── Por qué por hash y no por nodo ───────────────────────────────────
 *
 * El id del nodo cambia en cada re-estructuración (se recrean); el hash del texto
 * no. Y dos artículos con el mismo texto merecen la misma clasificación, así que
 * compartir la entrada es correcto.
 *
 * `tipo` es nullable a propósito: una fila con tipo NULL significa "la IA ya lo
 * miró y NO es un catálogo" —un negativo cacheado, para no volver a preguntarlo—.
 * La AUSENCIA de fila significa "nunca preguntado". Un fallo de la IA no se cachea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clasificaciones_ia', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 32)->unique()->comment('md5 del texto del artículo (con hijos).');
            $table->string('tipo', 40)->nullable()->comment('tipo_referencia, o NULL si la IA dijo que no es catálogo.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificaciones_ia');
    }
};
