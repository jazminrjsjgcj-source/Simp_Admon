<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para cerrar una observación por dos vías:
 *   - validada:   la revisora confirmó que la subsanación quedó bien.
 *   - sobreseida: la revisora aprobó por encima de la observación, con justificación.
 *
 * En ambos casos se registra QUIÉN la cerró y CUÁNDO; en el sobreseimiento, además,
 * el motivo. Así queda trazable para auditoría del proceso regulatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            $table->foreignId('resuelta_por')->nullable()->after('estatus')
                ->constrained('users')->nullOnDelete();
            $table->text('motivo_sobreseimiento')->nullable()->after('resuelta_por');
            $table->timestamp('resuelta_en')->nullable()->after('motivo_sobreseimiento');
        });
    }

    public function down(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resuelta_por');
            $table->dropColumn(['motivo_sobreseimiento', 'resuelta_en']);
        });
    }
};
