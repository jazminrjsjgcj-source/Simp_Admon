<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 'original' y 'copia' al ENUM de requisitos.tipo_presentacion.
 *
 * El dropdown "Tipo de presentación" del trámite guarda 'original', 'copia'
 * o 'digital'. La columna solo permitía ['documento, formato, comprobante,
 * producto_tramite, digital'], por lo que 'original'/'copia' provocaban
 * "Data truncated". Se conservan los valores viejos para no romper seeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `requisitos` MODIFY `tipo_presentacion` "
            . "ENUM('original','copia','digital','documento','formato','comprobante','producto_tramite') NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `requisitos` MODIFY `tipo_presentacion` "
            . "ENUM('documento','formato','comprobante','producto_tramite','digital') NULL"
        );
    }
};
