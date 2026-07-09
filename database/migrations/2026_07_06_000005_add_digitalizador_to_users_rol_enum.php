<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 'digitalizador' al ENUM del campo `rol` en la tabla `users`.
 *
 * El ENUM original: enlace, sujeto, revisora, juridico, admin
 * Nuevo ENUM:       enlace, sujeto, revisora, juridico, admin, digitalizador
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('enlace','sujeto','revisora','juridico','admin','digitalizador') DEFAULT 'enlace'");
    }

    public function down(): void
    {
        // Solo revierte si no hay usuarios con rol digitalizador
        $tiene = DB::table('users')->where('rol', 'digitalizador')->exists();
        if (!$tiene) {
            DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('enlace','sujeto','revisora','juridico','admin') DEFAULT 'enlace'");
        }
    }
};
