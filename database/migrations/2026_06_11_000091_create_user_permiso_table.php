<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote para asignar permisos directamente a un usuario,
 * independiente de sus roles. Permite personalización por usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permiso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
            $table->unsignedBigInteger('asignado_por')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'permiso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permiso');
    }
};
