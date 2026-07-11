<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda una copia de los catálogos tal como estaban AL FIRMAR.
 *
 * ── El problema ──────────────────────────────────────────────────────
 *
 * Un trámite firmado dice, por ejemplo, que lo tramita la "Dirección General de
 * Gobierno Digital". Si mañana esa dependencia se renombra, el trámite —que muestra
 * el nombre leyéndolo del catálogo vivo— pasaría a decir otra cosa.
 *
 * Es decir: se estaría cambiando lo que dice un documento ya firmado, sin que nadie
 * lo firmara de nuevo. Para un acto administrativo eso no es aceptable.
 *
 * ── La solución ──────────────────────────────────────────────────────
 *
 * Al firmarse, el registro guarda una FOTO de los nombres de catálogo que usó
 * (dependencia, unidad, sector, tipo de trámite, sujeto obligado...). El documento
 * firmado se muestra siempre con esa foto: dice lo que decía cuando se firmó.
 *
 * Y como la foto se puede comparar contra el catálogo actual, el sistema puede
 * AVISAR: "la dependencia cambió de nombre desde que esto se firmó; conviene
 * revisarlo". El aviso invita a revisar, pero no altera nada por su cuenta.
 *
 * Se guarda como JSON en una sola columna, y no como una columna por catálogo,
 * porque así agregar un catálogo nuevo no obliga a otra migración.
 */
return new class extends Migration
{
    /** Las tablas cuyos registros se firman. */
    private array $tablas = [
        'tramites',
        'acciones_agenda',
        'propuestas_regulatorias',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->json('catalogos_al_firmar')
                    ->nullable()
                    ->comment('Foto de los nombres de catálogo en el momento de la firma. Null si aún no se ha firmado.');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('catalogos_al_firmar');
            });
        }
    }
};
