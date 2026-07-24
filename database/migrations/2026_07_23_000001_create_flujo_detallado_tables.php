<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo detallado del flujo de un proceso.
 *
 * Hasta ahora el flujo TO-BE de una reingeniería vivía entero en la columna JSON
 * `reingenierias.flujo_to_be`, como una lista plana de pasos. Eso alcanza para
 * dibujar una cadena, pero no para lo que un proceso real necesita: agrupar
 * actividades por fase, decir quién hace cada una, y sobre todo BIFURCAR —qué pasa
 * si la revisión sale bien y qué pasa si sale mal, y a qué actividad se regresa—.
 *
 * Reparto entre tablas y JSON:
 *
 *   - En TABLAS lo que hay que consultar, contar o enlazar: participantes, fases,
 *     actividades, rutas y resultados. Son las que permiten preguntar "¿cuántos
 *     procesos pasan por Tesorería?" o "¿qué actividades regresan al inicio?", y las
 *     que necesitan integridad referencial (una ruta apunta a una actividad concreta).
 *
 *   - En JSON, dentro de cada actividad, el detalle que solo se lee cuando se abre
 *     esa actividad: el bloque de pago, la nota y el cambio de estado. Son formas
 *     variables —las condiciones de cobro son una lista de reglas— y darles tablas
 *     propias multiplicaría el esquema sin que nadie las consulte por separado.
 *
 * El pago NO guarda su propio cálculo: referencia los conceptos que ya existen en
 * `tramite_derechos`. Duplicar el importe aquí abriría la puerta a que el catálogo
 * diga una cosa y el diagrama otra, y ambos son documentos oficiales.
 *
 * Todo cuelga de `reingenierias`, que ya enlaza el trámite con la acción de agenda
 * que la originó.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Datos generales del proceso ──
        Schema::table('reingenierias', function (Blueprint $table) {
            $table->string('proceso_nombre', 300)->nullable()->after('estado');

            // Qué produce el proceso, cuando produce algo.
            $table->string('resolutivo_tipo', 40)->nullable()->after('proceso_nombre');
            $table->string('resolutivo_nombre', 300)->nullable()->after('resolutivo_tipo');

            $table->string('inicia_con', 500)->nullable()->after('resolutivo_nombre');
            $table->string('termina_con', 500)->nullable()->after('inicia_con');
        });

        // ── Quién interviene ──
        Schema::create('flujo_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reingenieria_id')->constrained('reingenierias')->cascadeOnDelete();
            $table->string('nombre', 200);

            // solicitante | sistema | dependencia | revisora | tecnica | tesoreria |
            // juridico | otra. Define además el color con que sale en el diagrama.
            $table->string('tipo', 30)->default('otra');

            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['reingenieria_id', 'orden']);
        });

        // ── Etapas en las que se agrupan las actividades ──
        Schema::create('flujo_fases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reingenieria_id')->constrained('reingenierias')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->text('nota')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['reingenieria_id', 'orden']);
        });

        // ── Finales posibles del proceso ──
        Schema::create('flujo_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reingenieria_id')->constrained('reingenierias')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['reingenieria_id', 'orden']);
        });

        // ── Qué se hace, quién y en qué fase ──
        Schema::create('flujo_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('flujo_fases')->cascadeOnDelete();

            // Puede quedar en null si el participante se borra: la actividad sigue
            // existiendo aunque haya que reasignarla.
            $table->foreignId('participante_id')->nullable()
                  ->constrained('flujo_participantes')->nullOnDelete();

            $table->string('descripcion', 500);

            // Cuando la actividad revisa algo, tiene dos salidas en vez de una.
            $table->boolean('tiene_decision')->default(false);
            $table->string('que_revisa', 500)->nullable();

            // Detalle que solo se lee al abrir la actividad: pago, nota y cambio de
            // estado. Ver el docblock de la migración.
            $table->json('detalle')->nullable();

            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['fase_id', 'orden']);
        });

        // ── A dónde sigue el proceso después de una actividad ──
        Schema::create('flujo_rutas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('flujo_actividades')->cascadeOnDelete();

            // siempre    → la actividad no decide nada
            // correcto   → la revisión salió bien
            // incorrecto → la revisión salió mal
            $table->string('condicion', 20)->default('siempre');

            // siguiente | actividad | inicio_fase | inicio_proceso | fin
            $table->string('destino_tipo', 30)->default('siguiente');

            // Solo cuando destino_tipo = actividad. Se anula si esa actividad
            // desaparece, para no dejar una ruta apuntando al vacío.
            $table->foreignId('destino_actividad_id')->nullable()
                  ->constrained('flujo_actividades')->nullOnDelete();

            // Solo cuando destino_tipo = fin: con qué resultado termina.
            $table->foreignId('resultado_id')->nullable()
                  ->constrained('flujo_resultados')->nullOnDelete();

            $table->timestamps();

            $table->index(['actividad_id', 'condicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flujo_rutas');
        Schema::dropIfExists('flujo_actividades');
        Schema::dropIfExists('flujo_resultados');
        Schema::dropIfExists('flujo_fases');
        Schema::dropIfExists('flujo_participantes');

        Schema::table('reingenierias', function (Blueprint $table) {
            $table->dropColumn([
                'proceso_nombre',
                'resolutivo_tipo',
                'resolutivo_nombre',
                'inicia_con',
                'termina_con',
            ]);
        });
    }
};
