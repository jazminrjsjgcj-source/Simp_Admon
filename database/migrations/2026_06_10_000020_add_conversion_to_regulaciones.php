<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->string('archivo_original', 500)->nullable();
            $table->string('archivo_markdown', 500)->nullable();
            $table->string('conversion_estatus', 30)
                  ->default('pendiente');
            $table->text('conversion_error')->nullable();
            $table->string('extension_original', 10)->nullable();
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
