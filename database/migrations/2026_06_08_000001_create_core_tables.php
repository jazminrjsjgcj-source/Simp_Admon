<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dependencias
        Schema::create('dependencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
            $table->timestamps();
        });

        // 2. Unidades administrativas
        Schema::create('unidades_administrativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias');
            $table->string('codigo', 10);
            $table->string('nombre');
            $table->timestamps();
            $table->unique(['dependencia_id', 'codigo']);
        });

        // 3. Sectores SCIAN
        Schema::create('sectores_scian', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
        });

        Schema::create('subsectores_scian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained('sectores_scian');
            $table->string('codigo', 10)->unique();
            $table->string('nombre');
        });

        // 4. Users — agregar campos PUNTA
        Schema::table('users', function (Blueprint $table) {
            $table->string('cargo')->nullable();
            $table->string('rol', 30)->default('enlace');
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->foreignId('unidad_id')->nullable()->constrained('unidades_administrativas');
            $table->boolean('activo')->default(true);
        });

        // 5. Configuración del sistema
        Schema::create('configuracion_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 6. Regulaciones
        Schema::create('regulaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 500);
            $table->string('tipo', 100)->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias');
            $table->date('fecha_publicacion')->nullable();
            $table->date('fecha_vigencia')->nullable();
            $table->string('estatus', 30)->default('vigente');
            $table->string('archivo_pdf', 500)->nullable();
            $table->text('resumen')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulaciones');
        Schema::dropIfExists('configuracion_sistema');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['dependencia_id']);
            $table->dropForeign(['unidad_id']);
            $table->dropColumn(['cargo', 'rol', 'dependencia_id', 'unidad_id', 'activo']);
        });
        Schema::dropIfExists('subsectores_scian');
        Schema::dropIfExists('sectores_scian');
        Schema::dropIfExists('unidades_administrativas');
        Schema::dropIfExists('dependencias');
    }
};
