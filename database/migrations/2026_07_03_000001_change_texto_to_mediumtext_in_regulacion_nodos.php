<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la columna `texto` de regulacion_nodos de TEXT (64 KB) a
 * MEDIUMTEXT (16 MB). El TEXT original desbordaba cuando el
 * estructurador creaba un nodo párrafo con el texto completo de los
 * artículos transitorios concatenados (regulaciones largas como leyes
 * de hacienda con 100+ artículos pueden superar los 65 KB).
 *
 * Error original:
 * SQLSTATE[22001]: String data, right truncated: 1406
 * Data too long for column 'texto' at row 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->mediumText('texto')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->text('texto')->nullable()->change();
        });
    }
};
