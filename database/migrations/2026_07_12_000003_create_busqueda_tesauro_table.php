<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El TESAURO: traduce el vocabulario del ciudadano al de la ley.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ HACE FALTA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta: "¿cuánto se paga por comprar una casa?"
 *
 * Y el artículo 38 de la Ley de Hacienda responde exactamente eso:
 *
 *     "El impuesto sobre ADQUISICIÓN de BIENES INMUEBLES será el que resulte de aplicar al
 *      valor del inmueble la tasa del 3%."
 *
 * Pero NO DICE "comprar". NO DICE "casa".
 *
 *     El ciudadano dice        La ley dice
 *     ─────────────────        ────────────────────────────
 *     comprar                  adquisición, adquirir
 *     casa                     bien inmueble, predio
 *     permiso                  derecho, cuota, licencia
 *     basura                   residuos sólidos, recolección
 *     terreno vacío            predio no edificado, baldío
 *
 * El buscador exige coincidencia exacta. Sin traducción, esa pregunta no encuentra nada — o
 * encuentra treinta resultados irrelevantes, que es peor.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ UNA TABLA Y NO SOLO LA IA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Ya existe un reformulador con IA que hace esto mismo. Y funciona.
 *
 * Pero el tesauro tiene tres ventajas que la IA no puede dar:
 *
 *   1. ES INSTANTÁNEO. Una consulta a una tabla, no una llamada HTTP de cinco segundos. El
 *      ciudadano está esperando con la página en blanco.
 *
 *   2. ES GRATIS. La IA se paga por uso. El tesauro no.
 *
 *   3. ES DETERMINISTA Y AUDITABLE. Si alguien pregunta por qué "comprar" busca "adquisición",
 *      la respuesta está en una fila de una tabla, con su fecha y su autor. La IA no puede
 *      explicar por qué eligió lo que eligió.
 *
 * ── Y una que importa más que las tres ──
 *
 * EL TESAURO NO PUEDE CAMBIAR DE TEMA.
 *
 * El riesgo grave del reformulador con IA es que traduzca "basura" a "impuesto predial" — una
 * traducción plausible que produciría una respuesta perfectamente citada sobre el impuesto
 * equivocado.
 *
 * Una tabla nunca hace eso. Dice exactamente lo que alguien escribió que dijera.
 *
 * ══════════════════════════════════════════════════════════════════════
 * Y POR QUÉ LA IA SIGUE HACIENDO FALTA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Porque un tesauro NUNCA está completo. Siempre falta una palabra.
 *
 * Hoy mismo, escribiendo la lista de verbos del normalizador, se olvidó "pagar" (estaba "paga",
 * "pagan", "pago"... y faltaba el infinitivo). Y nadie lo notó hasta que una prueba falló.
 *
 * El reparto queda así:
 *
 *     TESAURO  →  lo conocido, lo frecuente, lo que se repite.  Instantáneo y gratis.
 *     IA       →  lo que el tesauro no cubre.                   La red de seguridad.
 *
 * Lo barato primero. Como siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busqueda_tesauro', function (Blueprint $table) {
            $table->id();

            /**
             * La palabra que escribe el CIUDADANO.
             *
             * Se guarda en minúsculas y sin acentos: la comparación se hace contra la consulta ya
             * normalizada, que también viene así.
             */
            $table->string('termino_ciudadano', 60)->index();

            /**
             * Las palabras que usa LA LEY. Separadas por comas.
             *
             *     comprar  →  "adquisicion, adquirir, enajenacion"
             *
             * Se añaden TODAS a la búsqueda, con OR entre ellas. Buscar "comprar" pasa a buscar
             * (comprar | adquisicion | adquirir | enajenacion), y el AND con el resto de la
             * consulta se mantiene.
             *
             * Es decir: se relaja UNA palabra, no la consulta entera.
             */
            $table->text('terminos_ley');

            /**
             * De dónde salió esta entrada. Para poder auditarla dentro de dos años.
             *
             *   'inicial'    → sembrada al crear el tesauro
             *   'reportada'  → alguien reportó que su búsqueda no funcionaba
             *   'ia'         → la propuso el reformulador y un humano la aprobó
             */
            $table->string('origen', 20)->default('inicial');

            /**
             * Un interruptor por fila.
             *
             * Si una traducción resulta ser mala —lleva a resultados equivocados— se apaga sin
             * borrarla, y queda constancia de que se probó y no funcionó.
             */
            $table->boolean('activo')->default(true);

            $table->text('nota')->nullable();

            $table->timestamps();

            $table->unique('termino_ciudadano');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busqueda_tesauro');
    }
};
