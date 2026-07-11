<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 12. Acciones de agenda SyD
        Schema::create('acciones_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->nullable()->constrained('tramites');
            $table->string('tipo', 30);
            $table->text('descripcion')->nullable();
            $table->string('meta', 500)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_compromiso')->nullable();
            $table->string('responsable')->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->string('indicador', 500)->nullable();
            $table->string('estatus', 30)->default('borrador');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 13. Propuestas regulatorias
        Schema::create('propuestas_regulatorias', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->nullable()->unique();
            $table->string('nombre', 500);
            $table->string('tipo_regulacion', 100)->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->foreignId('sector_id')->nullable()->constrained('sectores_scian');
            $table->foreignId('subsector_id')->nullable()->constrained('subsectores_scian');
            $table->date('fecha_tentativa')->nullable();
            $table->text('justificacion')->nullable();
            $table->decimal('costo_burocratico', 14, 2)->nullable();
            $table->string('poblacion_afectada')->nullable();
            $table->string('determinacion_air', 30)->default('pendiente');
            $table->string('estatus', 30)->default('borrador');
            $table->string('archivo_propuesta', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 14. Análisis de Impacto Regulatorio
        Schema::create('analisis_impacto_regulatorio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propuesta_id')->constrained('propuestas_regulatorias');
            $table->string('folio', 50)->nullable()->unique();
            $table->text('problematica')->nullable();
            $table->text('objetivos')->nullable();
            $table->text('alternativas')->nullable();
            $table->text('costos_implementacion')->nullable();
            $table->text('beneficios')->nullable();
            $table->text('impacto_estimado')->nullable();
            $table->boolean('impacta_tramites')->nullable()->default(false);
            $table->string('sector_scian')->nullable();
            $table->string('subsector_scian')->nullable();
            $table->string('poblacion_volumen')->nullable();
            $table->string('ambito_aplicacion', 100)->nullable();
            $table->text('consulta_publica')->nullable();
            $table->text('acciones_derivadas')->nullable();
            $table->text('anexos')->nullable();
            $table->string('estatus', 30)->default('borrador');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 15. Exenciones AIR
        Schema::create('exenciones_air', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propuesta_id')->constrained('propuestas_regulatorias');
            $table->string('supuesto')->nullable();
            $table->text('justificacion')->nullable();
            $table->decimal('costos_estimados', 14, 2)->nullable();
            $table->string('estatus', 30)->default('solicitada');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 16. Dictámenes AIR
        Schema::create('dictamenes_air', function (Blueprint $table) {
            $table->id();
            $table->foreignId('air_id')->constrained('analisis_impacto_regulatorio');
            $table->string('estatus', 30)->default('pendiente');
            $table->date('fecha')->nullable();
            $table->string('archivo_firmado', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('responsable')->nullable();
            $table->timestamps();
        });

        // 17. Calendario eventos
        Schema::create('calendario_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->string('titulo', 500);
            $table->string('accion', 500)->nullable();
            $table->string('meta')->nullable();
            $table->date('fecha');
            $table->string('estatus', 30)->default('pendiente');
            $table->unsignedTinyInteger('avance')->nullable()->default(0);
            $table->string('responsable')->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->boolean('evidencia')->default(false);
            $table->nullableMorphs('eventable');
            $table->timestamps();
            $table->index('fecha');
            $table->index('tipo');
        });

        // 18-22. Tablas polimórficas
        Schema::create('observaciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('observable');
            $table->string('seccion', 100)->nullable();
            $table->text('texto');
            $table->foreignId('realizada_por')->constrained('users');
            $table->boolean('atendida')->default(false);
            $table->timestamps();
        });

        Schema::create('correcciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('corregible');
            $table->json('datos_corregidos');
            $table->foreignId('enviada_por')->constrained('users');
            $table->timestamps();
        });

        Schema::create('firmas', function (Blueprint $table) {
            $table->id();
            $table->morphs('firmable');
            $table->string('tipo', 30);
            $table->foreignId('firmante_id')->constrained('users');
            $table->timestamp('fecha')->nullable();
            $table->string('hash_acuse', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('tipo', 30);
            $table->string('nombre', 500)->nullable();
            $table->string('archivo', 500)->nullable();
            $table->string('hash_verificacion', 128)->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('auditable');
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->string('modulo', 100);
            $table->string('tipo', 100);
            $table->string('accion', 500);
            $table->text('detalle')->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('modulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('firmas');
        Schema::dropIfExists('correcciones');
        Schema::dropIfExists('observaciones');
        Schema::dropIfExists('calendario_eventos');
        Schema::dropIfExists('dictamenes_air');
        Schema::dropIfExists('exenciones_air');
        Schema::dropIfExists('analisis_impacto_regulatorio');
        Schema::dropIfExists('propuestas_regulatorias');
        Schema::dropIfExists('acciones_agenda');
    }
};
