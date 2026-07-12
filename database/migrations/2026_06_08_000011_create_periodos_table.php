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

        // ── AQUÍ HABÍA UN INSERT, Y CREABA UN BUG ──
        //
        // Esta migración insertaba un periodo llamado "Primer Periodo de Revisión 2026" con
        // estatus 'activo'. Sin especificar el tipo, así que caía al default de la columna:
        // agenda_syd.
        //
        // Y el DatabaseSeeder inserta OTRO periodo activo de agenda_syd ("Periodo SyD
        // Enero-Junio 2026"). Ninguno de los dos sabía del otro.
        //
        // Resultado: cada instalación limpia arrancaba con DOS periodos SyD activos, lo cual
        // rompe la regla central del módulo ("solo uno activo por tipo"). Nadie lo notó nunca,
        // porque no produce ningún error: el sistema simplemente elige uno.
        //
        // El insert está borrado, no movido. Sembrar datos es trabajo del seeder; una
        // migración crea ESTRUCTURA. Que hiciera las dos cosas es justo lo que permitió que el
        // conflicto pasara desapercibido: nadie va a buscar datos dentro de una migración.
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
