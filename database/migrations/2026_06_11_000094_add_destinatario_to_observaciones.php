<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase B: Observaciones por sección.
     *
     * Agrega campo destinatario para que cada observación indique
     * a quién va dirigida (generalmente el enlace que capturó el registro).
     */
    public function up(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            $table->foreignId('destinatario_id')
                ->nullable()
                ->after('realizada_por')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            $table->dropForeign(['destinatario_id']);
            $table->dropColumn('destinatario_id');
        });
    }
};
