<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada nodo del articulado guarda el TEXTO DE SUS ANCESTROS.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG QUE ESTO ARREGLA, Y ES EL MÁS PROFUNDO DEL BUSCADOR
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta: "¿cuáles son los impuestos aplicables al patrimonio?"
 *
 * Y el buscador devuelve DOS resultados, ninguno correcto: un artículo sobre recuperaciones
 * de capital y otro sobre fideicomisos. Los dos usan la palabra "patrimonio" en otro sentido.
 *
 * Mientras tanto, la Ley de Hacienda tiene EXACTAMENTE esto:
 *
 *     CAPÍTULO II — IMPUESTOS SOBRE EL PATRIMONIO
 *          SECCIÓN I    — Impuesto Predial
 *          SECCIÓN II   — Impuesto sobre Adquisición de Bienes Inmuebles
 *          SECCIÓN III  — Impuesto sobre Urbanización
 *
 * La respuesta está ahí, con el título del capítulo diciéndolo literalmente.
 *
 * ── Por qué no la encuentra ──
 *
 * Porque el artículo 26 dice:
 *
 *     "Son objeto del Impuesto Predial, la propiedad, usufructo, goce, uso y posesión..."
 *
 * Y NUNCA dice "patrimonio". No le hace falta: ya está DENTRO del capítulo que lo dice.
 *
 * El buscador solo mira el texto del nodo. Ignora el capítulo al que pertenece. Para él, cada
 * artículo es una isla sin contexto.
 *
 * Un abogado que lee el artículo 26 SABE que es un impuesto al patrimonio, porque ve el
 * encabezado tres líneas más arriba. El buscador, no.
 *
 * ── Y es el mismo bug que descartaba el artículo 65 ──
 *
 * Aquel decía "que genere el ESPECTÁCULO que corresponda" — singular, sin "públicos" — porque
 * ya estaba dentro del capítulo "IMPUESTO SOBRE ESPECTÁCULOS PÚBLICOS".
 *
 * No son dos bugs. Es EL MISMO, dos veces.
 *
 * ── La causa raíz ──
 *
 * En toda ley bien redactada, UN ARTÍCULO NO REPITE EL TÍTULO DE SU CAPÍTULO. Sería
 * redundante. El contexto lo da la estructura, no la frase.
 *
 * Cuanto mejor escrito está un artículo, MENOS palabras del tema repite. Y más invisible se
 * vuelve para un buscador que solo mira texto plano.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA SOLUCIÓN
 * ══════════════════════════════════════════════════════════════════════
 *
 * Cada nodo guarda, en una columna nueva, el texto de sus ancestros:
 *
 *     Artículo 26 → contexto: "IMPUESTOS SOBRE EL PATRIMONIO. IMPUESTO PREDIAL."
 *     Artículo 65 → contexto: "IMPUESTOS SOBRE LOS INGRESOS. IMPUESTOS SOBRE ESPECTÁCULOS PÚBLICOS."
 *
 * Y el buscador busca sobre TEXTO + CONTEXTO. Entonces:
 *
 *     · "patrimonio"            encuentra el artículo 26, el 38 y toda la sección predial.
 *     · "espectáculos públicos" encuentra el artículo 65, aunque diga "espectáculo" a secas.
 *     · Y todos los artículos de un capítulo salen JUNTOS, como debe ser.
 *
 * ── Por qué una COLUMNA y no un JOIN recursivo ──
 *
 * El árbol ya existe (parent_id). Se podría subir por él en cada búsqueda con un CTE
 * recursivo. Pero eso es un recorrido del árbol POR CADA NODO Y POR CADA BÚSQUEDA, y el
 * articulado de una ley tiene miles de nodos.
 *
 * El contexto de un nodo solo cambia cuando se reestructura la regulación — es decir, casi
 * nunca. Calcularlo una vez y guardarlo es gratis en tiempo de consulta.
 *
 * Es la misma lógica que congelar los catálogos al firmar: un dato que no cambia no se
 * recalcula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->text('contexto')
                ->nullable()
                ->after('texto')
                ->comment('Texto de los ancestros (título, capítulo, sección). Se busca junto con el texto.');
        });

        // Índice full-text sobre texto + contexto, que es como se va a buscar.
        //
        // Sin él, cada búsqueda tendría que calcular el tsvector de los dos campos concatenados
        // para CADA nodo de la tabla, en cada consulta. Con el índice, PostgreSQL lo hace una vez
        // y lo reutiliza.
        DB::statement(
            "CREATE INDEX regulacion_nodos_busqueda_idx ON regulacion_nodos
             USING GIN (to_tsvector('spanish', coalesce(texto, '') || ' ' || coalesce(contexto, '')))"
        );

        $this->rellenarContextoDeLosNodosExistentes();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS regulacion_nodos_busqueda_idx');

        Schema::table('regulacion_nodos', function (Blueprint $table) {
            $table->dropColumn('contexto');
        });
    }

    /**
     * Rellena el contexto de los nodos que ya están cargados.
     *
     * Sin esto, las regulaciones existentes seguirían siendo invisibles hasta que alguien las
     * reestructurara a mano. Y nadie reestructura una ley "porque sí": el bug seguiría ahí,
     * silencioso, hasta que un ciudadano preguntara por el patrimonio y no encontrara nada.
     *
     * Es exactamente el patrón que este proyecto lleva persiguiendo: un arreglo que solo
     * funciona para los datos nuevos deja el problema intacto para los que ya están.
     */
    private function rellenarContextoDeLosNodosExistentes(): void
    {
        // Solo los ancestros ESTRUCTURALES aportan contexto: título, capítulo, sección.
        //
        // Un artículo padre no: si una fracción cuelga del artículo 26, el texto del 26 ya es
        // demasiado largo y específico para servir de contexto — metería ruido, no señal.
        $tiposEstructurales = ['titulo', 'capitulo', 'seccion'];

        // Se recorre regulación por regulación para no cargar toda la tabla en memoria.
        DB::table('regulaciones')->orderBy('id')->pluck('id')->each(function ($regulacionId) use ($tiposEstructurales) {

            $nodos = DB::table('regulacion_nodos')
                ->where('regulacion_id', $regulacionId)
                ->get(['id', 'parent_id', 'tipo', 'numero', 'texto'])
                ->keyBy('id');

            if ($nodos->isEmpty()) {
                return;
            }

            foreach ($nodos as $nodo) {
                $ancestros = [];
                $actual    = $nodo;
                $vueltas   = 0;

                // Se sube por el árbol hasta la raíz.
                //
                // El límite de vueltas es una red de seguridad contra un ciclo en parent_id. No
                // debería existir —el estructurador construye un árbol, no un grafo— pero un
                // bucle infinito dentro de una migración deja la base a medias, y eso es mucho
                // peor que un contexto incompleto.
                while ($actual->parent_id !== null && $vueltas++ < 20) {
                    $padre = $nodos->get($actual->parent_id);

                    if (! $padre) {
                        break;
                    }

                    if (in_array($padre->tipo, $tiposEstructurales, true)) {
                        $etiqueta = trim(($padre->numero ? $padre->numero . ' ' : '') . ($padre->texto ?? ''));

                        if ($etiqueta !== '') {
                            array_unshift($ancestros, $etiqueta);
                        }
                    }

                    $actual = $padre;
                }

                if ($ancestros === []) {
                    continue;
                }

                DB::table('regulacion_nodos')
                    ->where('id', $nodo->id)
                    ->update(['contexto' => implode('. ', $ancestros)]);
            }
        });
    }
};
