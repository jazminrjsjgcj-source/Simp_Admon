<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una configuración de búsqueda que quita los acentos ANTES de aplicar el stemmer.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL BUG DE FONDO DEL BUSCADOR. LLEVABA AHÍ DESDE EL DÍA UNO.
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta: "¿Qué tasa de impuesto predial corresponde a una casa habitación?"
 *
 * Y el buscador devuelve CERO resultados. Mientras tanto, el artículo 31 fracción I dice
 * literalmente:
 *
 *     "A razón de 2 al millar anual sobre el valor catastral de los predios destinados
 *      totalmente por el contribuyente para su propia CASA HABITACIÓN."
 *
 * Está ahí. Con esas palabras. Y no lo encuentra.
 *
 * ── Por qué ──
 *
 * PostgreSQL indexa ese texto reduciendo cada palabra a su raíz:
 *
 *     to_tsvector('spanish', '...casa habitación')  →  'cas':23  'habit':24
 *
 * "habitación" (CON tilde) → raíz "habit". Perfecto.
 *
 * Pero SearchQueryNormalizer QUITA LAS TILDES de la consulta del ciudadano. Así que lo que
 * llega al buscador es "habitacion", sin tilde. Y entonces:
 *
 *     to_tsquery('spanish', 'habitacion:*')  →  'habitacion':*
 *
 * ¡Entera! El stemmer español NO RECONOCE "habitacion" sin tilde como una palabra española, así
 * que no la reduce a nada: la deja tal cual.
 *
 * Y "habitacion" NO es prefijo de "habit". Son cosas distintas.
 *
 *     BUSCAMOS 'habitacion'  →  ESTÁ INDEXADO COMO 'habit'  →  CERO RESULTADOS
 *
 * ── Lo mismo con "predial" ──
 *
 *     El texto dice "predios"   →  se indexa como 'predi'
 *     El ciudadano dice "predial" →  se busca como 'predial'
 *
 * Tampoco coinciden.
 *
 * ── El absurdo, resumido ──
 *
 *     ESTÁBAMOS QUITANDO EL ACENTO Y LUEGO PIDIÉNDOLE A POSTGRESQL QUE ENTENDIERA
 *     UNA PALABRA QUE YA NO ERA ESPAÑOLA.
 *
 * El texto de la ley tiene tildes y se indexa bien. La consulta del ciudadano no las tiene y se
 * busca mal. Los dos lados hablaban idiomas distintos, y nadie lo notó porque el sistema NO DA
 * NINGÚN ERROR: simplemente no encuentra nada, y "no hay resultados" parece una respuesta
 * normal.
 *
 * Es el bug número quince, y el mismo patrón que todos los anteriores.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA SOLUCIÓN: QUE LOS DOS LADOS HABLEN IGUAL
 * ══════════════════════════════════════════════════════════════════════
 *
 * Se crea una configuración de búsqueda que hace DOS cosas, en este orden:
 *
 *     1. unaccent      → quita los acentos:  "habitación" → "habitacion"
 *     2. spanish_stem  → aplica el stemmer:  "habitacion" → "habit"
 *
 * Y se usa la MISMA configuración para indexar el texto Y para procesar la consulta. Los dos
 * lados pasan por el mismo camino, así que llegan al mismo sitio:
 *
 *     TEXTO:    "casa habitación"  →  unaccent →  "casa habitacion"  →  stem →  'cas' 'habit'
 *     CONSULTA: "casa habitacion"  →  unaccent →  "casa habitacion"  →  stem →  'cas' 'habit'
 *                                                                                    ↑ COINCIDEN
 *
 * Da igual si el ciudadano escribe con tilde o sin ella. Y da igual si la ley la lleva. Los dos
 * acaban en la misma raíz.
 *
 * ── Por qué el orden importa ──
 *
 * unaccent PRIMERO, stemmer DESPUÉS. Si fuera al revés, el stemmer recibiría "habitación" con
 * tilde, la reduciría a "habit", y unaccent no tendría nada que hacer — pero la consulta sin
 * tilde seguiría llegando como "habitacion". Volveríamos al mismo problema.
 *
 * ── Qué hay que hacer después de esta migración ──
 *
 * TODAS las llamadas a to_tsvector() y to_tsquery() del sistema tienen que usar
 * 'spanish_unaccent' en vez de 'spanish'. Están en BuscadorService (seis consultas) y en el
 * índice GIN.
 *
 * Si una sola se queda con 'spanish', esa fuente seguirá con el bug — y en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La extensión unaccent viene con PostgreSQL, pero no está activa por defecto.
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        // Se parte de la configuración española estándar y se le añade el paso de unaccent.
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_ts_config WHERE cfgname = 'spanish_unaccent'
                ) THEN
                    CREATE TEXT SEARCH CONFIGURATION spanish_unaccent (COPY = spanish);

                    ALTER TEXT SEARCH CONFIGURATION spanish_unaccent
                        ALTER MAPPING FOR hword, hword_part, word
                        WITH unaccent, spanish_stem;
                END IF;
            END
            $$;
        ");

        // El índice GIN tiene que reconstruirse con la configuración nueva. El anterior indexaba
        // con 'spanish', así que sus raíces llevan acento y no sirven.
        //
        // Si no se recrea, el índice existe pero NO SE USA (PostgreSQL solo aprovecha un índice
        // cuya expresión coincida exactamente con la de la consulta). Y entonces cada búsqueda
        // recalcularía el tsvector de los miles de nodos, en cada consulta.
        //
        // Seguiría funcionando. Y sería lentísimo, sin que nada avisara.
        DB::statement('DROP INDEX IF EXISTS regulacion_nodos_busqueda_idx');

        DB::statement("
            CREATE INDEX regulacion_nodos_busqueda_idx ON regulacion_nodos
            USING GIN (
                to_tsvector('spanish_unaccent', coalesce(texto, '') || ' ' || coalesce(contexto, ''))
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS regulacion_nodos_busqueda_idx');

        DB::statement("
            CREATE INDEX regulacion_nodos_busqueda_idx ON regulacion_nodos
            USING GIN (
                to_tsvector('spanish', coalesce(texto, '') || ' ' || coalesce(contexto, ''))
            )
        ");

        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS spanish_unaccent');
    }
};
