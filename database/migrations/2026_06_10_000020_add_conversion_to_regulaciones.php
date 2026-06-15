<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->string('archivo_original', 500)->nullable()->after('archivo_pdf');
            $table->string('archivo_markdown', 500)->nullable()->after('archivo_original');
            $table->enum('conversion_estatus', ['pendiente', 'procesando', 'listo', 'error'])
                  ->default('pendiente')->after('archivo_markdown');
            $table->text('conversion_error')->nullable()->after('conversion_estatus');
            $table->string('extension_original', 10)->nullable()->after('conversion_error');
        });
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_original',
                'archivo_markdown',
                'conversion_estatus',
                'conversion_error',
                'extension_original',
            ]);
        });
    }
};
