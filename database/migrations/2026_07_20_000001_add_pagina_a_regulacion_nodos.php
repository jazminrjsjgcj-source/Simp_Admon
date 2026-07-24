<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade la columna `pagina` a regulacion_nodos: en qué página del PDF ORIGINAL
 * aparece el artículo.
 *
 * ── Por qué existe ───────────────────────────────────────────────────
 *
 * El buscador sabe QUÉ artículo arrojó un resultado, pero no en qué página del
 * PDF está. Sin ese dato no se puede abrir el visor del navegador en el lugar
 * correcto (#page=N). Esta columna guarda ese número.
 *
 * ── Por qué es NULLABLE ──────────────────────────────────────────────
 *
 * No toda fila puede tener página, y eso es correcto, no un error:
 *   - Las filas que ya existían antes de esta migración.
 *   - Las regulaciones cuyo original es Word (no hay PDF con paginación oficial).
 *   - Los nodos que no son artículos, o cuyo texto no se pudo emparejar con una
 *     página concreta.
 *
 * En todos esos casos `pagina` queda NULL y el buscador cae limpio a abrir el
 * PDF en la primera página (o al articulado estructurado). NULL nunca rompe nada.
 *
 * La rellena RegulacionPaginadorService después de construir el árbol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            // unsignedSmallInteger llega hasta 65 535 páginas: de sobra para
            // cualquier ley municipal, y ocupa la mitad que un entero normal.
            // Se coloca después de `texto` por legibilidad al inspeccionar la tabla.
            $table->unsignedSmallInteger('pagina')->nullable()->after('texto');
        });
    }

    public function down(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->dropColumn('pagina');
        });
    }
};
