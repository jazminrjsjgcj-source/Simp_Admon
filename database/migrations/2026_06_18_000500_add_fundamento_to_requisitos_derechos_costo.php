<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega fundamento jurídico OPCIONAL (de escritura, sin catálogo) a tres
 * elementos: cada requisito, cada concepto de pago de derechos, y el costo
 * del trámite. Tres campos en cada caso:
 *   - fj_norma:    ley o reglamento que lo fundamenta.
 *   - fj_capitulo: capítulo citado.
 *   - fj_articulo: artículo citado.
 *
 * Son nullable: solo se llenan si el usuario activa "tiene fundamento".
 */
return new class extends Migration
{
    private array $tablas = ['requisitos', 'tramite_derechos', 'tramites'];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (!Schema::hasColumn($tabla, 'fj_norma')) {
                    $table->string('fj_norma', 500)->nullable();
                }
                if (!Schema::hasColumn($tabla, 'fj_capitulo')) {
                    $table->string('fj_capitulo', 255)->nullable();
                }
                if (!Schema::hasColumn($tabla, 'fj_articulo')) {
                    $table->string('fj_articulo', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                foreach (['fj_norma', 'fj_capitulo', 'fj_articulo'] as $col) {
                    if (Schema::hasColumn($tabla, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
