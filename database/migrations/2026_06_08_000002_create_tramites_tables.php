<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 7. Trámites
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            // Identificación
            $table->string('nombre_oficial', 500);
            $table->foreignId('dependencia_id')->constrained('dependencias');
            $table->foreignId('unidad_id')->nullable()->constrained('unidades_administrativas');
            $table->string('servidor_publico')->nullable();
            $table->boolean('tiene_homoclave')->default(false);
            $table->string('homoclave', 50)->nullable()->unique();
            $table->foreignId('sector_id')->nullable()->constrained('sectores_scian');
            $table->foreignId('subsector_id')->nullable()->constrained('subsectores_scian');
            // Información general
            $table->text('objetivo')->nullable();
            $table->string('poblacion_objetivo', 100)->nullable();
            $table->string('dirigido_a', 30)->default('ambas');
            $table->string('grupo_prioritario', 100)->nullable();
            $table->string('frecuencia', 50)->nullable();
            $table->unsignedInteger('volumen_anual')->nullable();
            $table->unsignedInteger('plazo_resolucion_cantidad')->nullable();
            $table->string('plazo_resolucion_unidad', 30)->nullable()->default('habiles');
            // Operación y costos (ATDT)
            $table->unsignedTinyInteger('num_areas')->nullable();
            $table->string('areas_participantes', 500)->nullable();
            $table->unsignedTinyInteger('visitas_requeridas')->nullable();
            $table->unsignedTinyInteger('tiempo_traslado_horas')->nullable()->default(0);
            $table->unsignedTinyInteger('tiempo_traslado_min')->nullable()->default(0);
            $table->unsignedTinyInteger('tiempo_espera_horas')->nullable()->default(0);
            $table->unsignedTinyInteger('tiempo_espera_min')->nullable()->default(0);
            $table->unsignedTinyInteger('tiempo_atencion_horas')->nullable()->default(0);
            $table->unsignedTinyInteger('tiempo_atencion_min')->nullable()->default(0);
            $table->decimal('monto_derechos', 12, 2)->nullable()->default(0);
            $table->unsignedInteger('copias_cantidad')->nullable()->default(0);
            $table->decimal('copias_precio', 8, 2)->nullable()->default(1.50);
            $table->decimal('salario_hora_w', 10, 2)->nullable()->default(68.20);
            $table->unsignedTinyInteger('nivel_digitalizacion')->nullable()->default(1);
            // Costos calculados
            $table->decimal('cbd_directo', 14, 2)->nullable();
            $table->decimal('cbi_indirecto', 14, 2)->nullable();
            $table->decimal('cbu_unitario', 14, 2)->nullable();
            $table->decimal('cbt_total', 14, 2)->nullable();
            // Estado
            $table->string('estatus', 30)->default('borrador');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 8. Requisitos
        Schema::create('requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden')->default(1);
            $table->string('nombre', 500);
            $table->boolean('original')->default(false);
            $table->boolean('copia')->default(false);
            // Guarda multiselección como lista separada por comas ("original,copia"),
            // por eso es holgado. Los valores válidos los valida Laravel.
            $table->string('tipo_presentacion', 150)->nullable();            $table->unsignedInteger('horas_estimadas')->nullable()->default(0);
            $table->unsignedInteger('minutos_estimados')->nullable()->default(0);
            $table->decimal('tiempo_homologado_hrs', 10, 2)->nullable();
            $table->decimal('costo_requisito', 12, 2)->nullable();
            $table->string('id_automatico', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('es_producto_tramite')->default(false);
            $table->string('tramite_origen')->nullable();
            $table->string('documento_origen')->nullable();
            $table->timestamps();
        });

        // 9. Proceso de atención
        Schema::create('proceso_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('tipo', 30)->default('atencion');
            $table->unsignedTinyInteger('paso');
            $table->string('accion', 500)->nullable();
            $table->string('detalle', 500)->nullable();
            $table->timestamps();
        });

        // 10. Fundamento jurídico
        Schema::create('fundamento_juridico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('regulacion_id')->nullable()->constrained('regulaciones');
            $table->string('normativa_nombre', 500)->nullable();
            $table->string('tipo_normativa', 100)->nullable();
            $table->string('articulo_fraccion')->nullable();
            $table->text('resumen')->nullable();
            $table->timestamps();
        });

        // 11. Ficha portal
        Schema::create('ficha_portal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->cascadeOnDelete();
            $table->string('nombre_ciudadano', 500)->nullable();
            $table->string('tipo', 100)->nullable();
            $table->string('homoclave_publica', 50)->nullable();
            $table->string('documento_obtiene')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('casos_realizarse')->nullable();
            $table->string('modalidad', 100)->nullable();
            $table->string('canal_principal')->nullable();
            $table->boolean('requiere_cita')->nullable()->default(false);
            $table->string('enlace_cita', 500)->nullable();
            $table->string('costo_publico', 100)->nullable();
            $table->string('forma_pago', 100)->nullable();
            $table->string('resultado', 100)->nullable();
            $table->string('doc_resultado')->nullable();
            $table->string('medio_entrega', 100)->nullable();
            $table->string('vigencia', 100)->nullable();
            $table->string('oficina')->nullable();
            $table->string('horario', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estatus_validacion', 50)->nullable();
            $table->date('fecha_validacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_portal');
        Schema::dropIfExists('fundamento_juridico');
        Schema::dropIfExists('proceso_atencion');
        Schema::dropIfExists('requisitos');
        Schema::dropIfExists('tramites');
    }
};
