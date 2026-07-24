<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca qué artículos son "de referencia": catálogos, escalas, tarifas o definiciones que otros
 * artículos necesitan para entenderse.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ ESTA COLUMNA EXISTE
 * ══════════════════════════════════════════════════════════════════════
 *
 * La respuesta a "¿cuánto es la multa por obstruir la banqueta?" vive en TRES artículos del
 * Bando que se remiten entre sí: el 65 (la conducta), el 105 (el catálogo "art. 65 = Clase D") y
 * el 104 (la escala "Clase D = 31-100 UMA"). El buscador encuentra el 65 pero no los otros dos.
 *
 * Esta columna marca el 104 y el 105 como artículos de referencia. El asistente los inyecta
 * SIEMPRE que responde sobre el Bando, y así completa la cadena en vez de inventar la cifra.
 *
 * ── Por qué guarda el TIPO y no un simple sí/no ──
 *
 * Guardar el tipo (escala/catálogo/tarifa/definiciones) permite, más adelante, inyectar solo el
 * tipo relevante a cada pregunta. Un sí/no obligaría a inyectar todo siempre. El tipo lo pone la
 * IA al cargar la ley (ver DetectorCatalogosService), de una LISTA CERRADA.
 *
 * ── Por qué la IA y no reglas fijas ──
 *
 * Detectar por frases ("las infracciones se clasifican") ataría el sistema a la redacción del
 * Bando de La Paz. La IA generaliza a cualquier ley de cualquier jurisdicción. Es la pieza que
 * hace esto REPLICABLE: el mismo código sirve para leyes de otro estado o país.
 *
 * Ver docs/arquitectura-jurisdiccion-y-catalogos.md para el diseño completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->string('tipo_referencia', 40)
                ->nullable()
                ->after('contexto')
                ->comment('Si no es null, este artículo es una tabla de referencia (escala_sancion, '
                    . 'catalogo_clasificacion, tarifa, definiciones) que acompaña a toda respuesta sobre '
                    . 'su regulación. Lo etiqueta la IA al cargar. Ver DetectorCatalogosService.');
        });

        // Índice parcial: solo indexa las filas marcadas (poquísimas). Buscar "los artículos de
        // referencia de la regulación X" debe ser instantáneo, y como casi ningún nodo lleva
        // etiqueta, el índice es diminuto.
        DB::statement(
            'CREATE INDEX regulacion_nodos_tipo_referencia_idx
             ON regulacion_nodos (regulacion_id)
             WHERE tipo_referencia IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS regulacion_nodos_tipo_referencia_idx');

        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->dropColumn('tipo_referencia');
        });
    }
};
