<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de notificaciones en-app (canal 'database' de Laravel).
 *
 * Es el esquema estándar de Laravel para notificaciones de base de datos:
 *  - id (UUID): identificador único de cada notificación.
 *  - type: la clase de notificación (ej. App\Notifications\NuevaObservacion).
 *  - notifiable: relación polimórfica al destinatario (un User).
 *  - data: el contenido de la notificación en JSON (título, enlace, etc.).
 *  - read_at: null si no se ha leído; fecha en que se leyó.
 *
 * Con esto, $user->notifications devuelve sus notificaciones y
 * $user->unreadNotifications las no leídas (para el contador de la campanita).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
