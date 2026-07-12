<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda si el costo de espera se pudo calcular, y por qué no, cuando no.
 *
 * ── El problema que resuelve ──────────────────────────────────────────
 *
 * El servicio ya sabe cuándo no puede calcular el costo del plazo de resolución
 * (faltan el PIB, la población y la tasa libre de riesgo para las personas físicas; o
 * los datos económicos de la actividad para las personas morales). En esos casos
 * devuelve CERO y una bandera que dice "esto no es un cero de verdad".
 *
 * Pero esa bandera vive solo en memoria, dentro del array que devuelve calcularCostos().
 * No llega a ninguna tabla. Así que la ficha del trámite, que lee de `tramites` y de
 * `tramite_costos_burocraticos`, ve un cero pelado y no tiene forma de distinguirlo de
 * un cero legítimo.
 *
 * Y las dos cosas son muy distintas:
 *
 *   "Este trámite se resuelve en el acto, esperar no cuesta nada."
 *   "No sabemos cuánto cuesta esperar, porque nos faltan datos."
 *
 * Si las dos se pintan igual, el usuario lee la primera cuando la verdad es la segunda.
 * Y el Costo Burocrático Total, el porcentaje del umbral, la clasificación de impacto y
 * el resultado AIR salen todos subestimados sin que nada lo advierta.
 *
 * Es la misma trampa que el bug original —un número que parece bueno y no lo es—, solo
 * que ahora la trampa está en la pantalla en vez de en la fórmula.
 *
 * ── Qué se guarda ─────────────────────────────────────────────────────
 *
 *   resolucion_calculable → false cuando el cero es una laguna, no un hecho.
 *   resolucion_motivo     → qué falta exactamente, en castellano, para que quien lea la
 *                           ficha sepa qué hay que cargar.
 *
 * Se guardan en el SNAPSHOT y no en `tramites` a propósito: el snapshot es la foto de un
 * cálculo concreto, y forma parte de esa foto saber si el cálculo estaba completo. Dentro
 * de un año, mirando un snapshot viejo, se podrá saber si aquel CBT era de fiar.
 *
 * ── El valor por defecto ──────────────────────────────────────────────
 *
 * `true`. Los snapshots que ya existen se calcularon con la fórmula vieja, que siempre
 * producía un número (equivocado, pero un número). Marcarlos como "no calculables" sería
 * mentir de otra manera: sí se calcularon, y mal.
 *
 * En cuanto alguien vuelva a guardar el trámite, el snapshot nuevo dirá la verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_costos_burocraticos', function (Blueprint $table) {
            $table->boolean('resolucion_calculable')->default(true)->after('cbi_resolucion');
            $table->text('resolucion_motivo')->nullable()->after('resolucion_calculable');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_costos_burocraticos', function (Blueprint $table) {
            $table->dropColumn(['resolucion_calculable', 'resolucion_motivo']);
        });
    }
};
