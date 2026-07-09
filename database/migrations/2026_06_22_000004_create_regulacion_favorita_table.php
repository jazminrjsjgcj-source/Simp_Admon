<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot de regulaciones favoritas por usuario.
 *
 * Relación muchos-a-muchos: un usuario puede marcar varias regulaciones como
 * favoritas, y una regulación puede ser favorita de varios usuarios. Sigue la
 * misma convención que user_permiso (id, foráneas con cascada, timestamps,
 * unique compuesto para no duplicar el mismo favorito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulacion_favorita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('regulacion_id')->constrained('regulaciones')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'regulacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulacion_favorita');
    }
};
