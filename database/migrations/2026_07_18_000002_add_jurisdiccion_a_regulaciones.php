<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Da a cada regulación su jurisdicción: a qué ámbito pertenece y, si aplica,
 * a qué estado y municipio.
 *
 * ── Qué problema resuelve ────────────────────────────────────────────
 *
 * El buscador puede traer, ante la pregunta de un ciudadano de La Paz, una
 * ley de otro estado o municipio. Esa no es una respuesta incompleta: es
 * derecho que NO le aplica, con la misma apariencia de autoridad que la
 * respuesta correcta. Es el mismo tipo de daño que una cita falsa.
 *
 * Para no mezclar jurisdicciones, cada ley necesita saber de dónde es. Estas
 * tres columnas son ese dato.
 *
 * ── Por qué las tres son NULLABLE ────────────────────────────────────
 *
 * Al crear la columna, TODAS las regulaciones existentes quedan sin ámbito.
 * Si el filtro excluyera lo no clasificado, vaciaría el buscador de golpe
 * (Bando, Ley de Hacienda, todo) y tumbaría los casos que hoy funcionan.
 *
 * Por eso NULL es un estado legítimo y transitorio: "todavía sin clasificar".
 * El filtro lo tratará como incluido mientras queden leyes sin ámbito, y solo
 * pasará a excluirlo cuando la clasificación esté completa (última fase).
 *
 * ── Por qué string y no un enum de BD ────────────────────────────────
 *
 * `ambito` solo admite tres valores (federal, estatal, municipal), pero se
 * usa `string` y no un enum de Postgres por coherencia con el resto de la
 * tabla —`estatus` y los demás catálogos cortos ya son string— y porque un
 * enum de BD obliga a una migración para añadir un valor, mientras que un
 * string no. La validación de los tres valores vive en la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->string('ambito', 20)
                ->nullable()
                ->after('estatus')
                ->comment('federal | estatal | municipal. Null = sin clasificar todavía.');

            $table->string('estado', 100)
                ->nullable()
                ->after('ambito')
                ->comment('Estado al que pertenece. Solo si ambito es estatal o municipal.');

            $table->string('municipio', 100)
                ->nullable()
                ->after('estado')
                ->comment('Municipio al que pertenece. Solo si ambito es municipal.');
        });
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropColumn(['ambito', 'estado', 'municipio']);
        });
    }
};
