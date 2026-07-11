<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración del módulo evolucionado de Costo Burocrático.
 *
 * Crea 4 tablas:
 *   - parametros_costo_burocratico: constantes configurables (salario hora, etc)
 *   - unidades_valor_referencia: UMA, salario mínimo, UDI por año
 *   - umbrales_configurados: umbrales por sector/subsector con vigencia
 *   - tramite_costos_burocraticos: snapshot del cálculo para auditoría
 *
 * Agrega columnas a `tramites`:
 *   - monto_requisitos_con_costo: suma de costos directos de requisitos
 *   - cbi_requisitos: tiempo invertido en juntar requisitos
 *   - cbi_resolucion: tiempo de espera de resolución
 *   - impacto: bajo|medio|alto|critico|no_determinado
 *   - resultado_air: no_determinado|no_activa_automaticamente|puede_requerir_air
 *
 * Las columnas viejas (cbd_directo, cbi_indirecto, cbu_unitario, cbt_total)
 * se conservan para compatibilidad. El servicio nuevo las sigue llenando.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Parámetros configurables del cálculo
        Schema::create('parametros_costo_burocratico', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->decimal('valor', 12, 4);
            $table->string('unidad', 50);
            $table->string('fuente', 255)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('activo');
        });

        // 2. Valor de UMA, salario mínimo, UDI por año
        Schema::create('unidades_valor_referencia', function (Blueprint $table) {
            $table->id();
            $table->string('unidad', 30);
            $table->decimal('valor_pesos', 14, 4);
            $table->unsignedSmallInteger('anio');
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->string('fuente', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['unidad', 'anio']);
            $table->index('activo');
        });

        // 3. Umbrales configurados (por sector/subsector/año)
        Schema::create('umbrales_configurados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->nullable()->constrained('sectores_scian');
            $table->foreignId('subsector_id')->nullable()->constrained('subsectores_scian');
            $table->decimal('monto_base', 16, 4);
            $table->string('unidad_base', 30);
            $table->decimal('monto_pesos', 16, 4);
            $table->decimal('monto_uma', 16, 4)->nullable();
            $table->decimal('monto_salario_minimo', 16, 4)->nullable();
            $table->decimal('monto_udis', 16, 4)->nullable();
            $table->unsignedSmallInteger('anio');
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->string('estatus', 30)->default('activo');
            $table->string('fuente', 500)->nullable();
            $table->date('fecha_fuente')->nullable();
            $table->date('fecha_carga')->nullable();
            $table->foreignId('cargado_por')->nullable()->constrained('users');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['sector_id', 'estatus']);
            $table->index(['subsector_id', 'estatus']);
        });

        // 4. Snapshot del cálculo (auditoría e historial)
        Schema::create('tramite_costos_burocraticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            // CBD - desglose
            $table->decimal('monto_derechos',  14, 2)->default(0);
            $table->unsignedInteger('numero_copias')->default(0);
            $table->decimal('precio_copia',     8, 2)->default(0);
            $table->decimal('monto_copias',    14, 2)->default(0);
            $table->decimal('monto_requisitos',14, 2)->default(0);
            $table->decimal('cbd_unitario',    14, 2)->default(0);
            // CBI - desglose
            $table->decimal('cbi_requisitos',  14, 2)->default(0);
            $table->decimal('cbi_resolucion',  14, 2)->default(0);
            $table->decimal('cbi_unitario',    14, 2)->default(0);
            // CBU y CBT
            $table->decimal('cbu_unitario',    14, 2)->default(0);
            $table->unsignedInteger('volumen_anual')->default(0);
            $table->decimal('cbt_total_anual', 16, 2)->default(0);
            // Umbral (snapshot)
            $table->foreignId('umbral_id')->nullable()->constrained('umbrales_configurados');
            $table->decimal('umbral_monto_pesos',          16, 2)->nullable();
            $table->decimal('umbral_monto_uma',            16, 4)->nullable();
            $table->decimal('umbral_monto_salario_minimo', 16, 4)->nullable();
            $table->decimal('porcentaje_umbral',            8, 2)->nullable();
            // Resultado
            $table->string('impacto', 30)->default('no_determinado');
            $table->string('resultado_air', 40)->default('no_determinado');
            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();
            $table->index(['tramite_id', 'calculado_en']);
        });

        // 5. Columnas nuevas en `tramites`
        Schema::table('tramites', function (Blueprint $table) {
            $table->decimal('monto_requisitos_con_costo', 14, 2)->nullable()->default(0);
            $table->decimal('cbi_requisitos', 14, 2)->nullable();
            $table->decimal('cbi_resolucion', 14, 2)->nullable();
            $table->string('impacto', 30)->default('no_determinado');
            $table->string('resultado_air', 40)->default('no_determinado');
        });

        // 6. Columna `requiere_tercero` y `tiene_costo` en requisitos (el resto ya existe)
        Schema::table('requisitos', function (Blueprint $table) {
            $table->boolean('tiene_costo')->default(false);
            $table->boolean('requiere_tercero')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('requisitos', function (Blueprint $table) {
            $table->dropColumn(['tiene_costo', 'requiere_tercero']);
        });

        Schema::table('tramites', function (Blueprint $table) {
            $table->dropColumn(['monto_requisitos_con_costo', 'cbi_requisitos', 'cbi_resolucion', 'impacto', 'resultado_air']);
        });

        Schema::dropIfExists('tramite_costos_burocraticos');
        Schema::dropIfExists('umbrales_configurados');
        Schema::dropIfExists('unidades_valor_referencia');
        Schema::dropIfExists('parametros_costo_burocratico');
    }
};
