<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estatus', 30)->default('proximo');
            $table->text('descripcion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::table('periodos')->insert([
            'nombre'      => 'Primer Periodo de Revisión 2026',
            'fecha_inicio'=> '2026-06-01',
            'fecha_fin'   => '2026-06-30',
            'estatus'     => 'activo',
            'descripcion' => 'Periodo inicial de carga y revisión de trámites y agenda.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
