<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién ya vio cada tour guiado.
 *
 * ══════════════════════════════════════════════════════════════════════
 * PARA QUÉ SIRVE
 * ══════════════════════════════════════════════════════════════════════
 *
 * Sin esta tabla, el tour solo se lanza si alguien pulsa el botón "¿Cómo funciona
 * esto?". Y el problema con eso es el de siempre: quien más lo necesita es quien
 * menos va a pulsarlo, porque todavía no sabe que existe.
 *
 * Con la tabla, el tour se lanza SOLO la primera vez que una persona entra a cada
 * pantalla. Después ya no molesta nunca más, pero el botón sigue ahí para
 * repetirlo.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ EN LA BASE Y NO EN localStorage
 * ══════════════════════════════════════════════════════════════════════
 *
 * localStorage habría sido gratis: cero tablas, cero rutas. Y estaría mal por dos
 * razones concretas de este proyecto:
 *
 *   1. Los enlaces de dependencia usan computadoras COMPARTIDAS de oficina. Con
 *      localStorage, el primero que pasa por la pantalla se lleva el tutorial y
 *      los cinco siguientes no lo ven nunca — el navegador no distingue quién se
 *      sentó.
 *
 *   2. El Ayuntamiento va a querer saber quién completó el onboarding. Con
 *      localStorage esa pregunta no tiene respuesta: el dato vive en una máquina
 *      a la que nadie tiene acceso.
 *
 * ══════════════════════════════════════════════════════════════════════
 * SOLO SE REGISTRA SI SE LLEGÓ AL FINAL
 * ══════════════════════════════════════════════════════════════════════
 *
 * tour.js avisa al servidor únicamente cuando el usuario pulsa "Terminar" en el
 * último paso. Cerrar con Escape o con el botón "Salir" NO marca nada, a propósito:
 * quien abandona en el segundo paso no ha aprendido el flujo, y la próxima vez que
 * entre merece que el tutorial vuelva a ofrecerse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours_vistos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /**
             * La clave del tour, que es el NOMBRE DE LA RUTA de Laravel tal como
             * aparece en config/tours.php ('tramites.create', 'agenda.show@revisora').
             *
             * Se guarda la cadena y no un id de catálogo porque los tours viven en un
             * archivo de configuración, no en la base: no hay tabla de tours a la que
             * apuntar. Si mañana se borra un tour del config, su fila aquí queda
             * huérfana y no molesta a nadie.
             *
             * 120 caracteres es holgado: la clave más larga hoy son 20.
             */
            $table->string('tour', 120);

            /** Cuándo llegó al último paso. */
            $table->timestamp('completado_en')->useCurrent();

            /**
             * Un usuario completa un tour UNA vez. Si repite el recorrido con el botón,
             * el servidor recibe el aviso otra vez y el índice único evita duplicar la
             * fila (ver TourController: usa updateOrInsert).
             *
             * Además es el índice que consulta el partial en CADA carga de página para
             * decidir si autolanzar, así que conviene que esté.
             */
            $table->unique(['user_id', 'tour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours_vistos');
    }
};
