<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo oficial de Unidades Responsables (UR) del H. Ayuntamiento
 * de La Paz, B.C.S. Cada UR tiene un código jerárquico de 14 dígitos
 * que codifica: Poder → Dirección General → Dirección → Subdirección
 * → Departamento → Unidad.
 *
 * La tabla `unidades_administrativas` se mantiene para compatibilidad
 * pero los registros nuevos deben usar `unidad_responsable_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_responsables', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 14)->unique();
            $table->string('nombre', 255);
            $table->unsignedTinyInteger('poder');           // 01-06
            $table->string('nivel', 30)->nullable();         // direccion_general, direccion, subdireccion, departamento, etc
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('poder');
            $table->index('activo');
        });

        // Referencia opcional en trámites a la UR oficial
        Schema::table('tramites', function (Blueprint $table) {
            $table->foreignId('unidad_responsable_id')->nullable()->after('unidad_id')->constrained('unidades_responsables');
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->dropForeign(['unidad_responsable_id']);
            $table->dropColumn('unidad_responsable_id');
        });
        Schema::dropIfExists('unidades_responsables');
    }
};
