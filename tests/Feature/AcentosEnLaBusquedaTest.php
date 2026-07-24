<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\BuscadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LOS ACENTOS. El bug de fondo del buscador, y llevaba ahí desde el día uno.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL CASO REAL
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta:
 *
 *     "¿Qué tasa de impuesto predial corresponde a una casa habitación?"
 *
 * Y el buscador devuelve CERO resultados.
 *
 * Mientras tanto, el artículo 31 fracción I de la Ley de Hacienda dice, palabra por palabra:
 *
 *     "A razón de 2 al millar anual sobre el valor catastral de los predios destinados
 *      totalmente por el contribuyente para su propia CASA HABITACIÓN."
 *
 * Está ahí. Con esas palabras exactas. Y no lo encuentra.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA CAUSA
 * ══════════════════════════════════════════════════════════════════════
 *
 * PostgreSQL indexa el texto reduciendo cada palabra a su raíz:
 *
 *     to_tsvector('spanish', '...casa habitación')  →  'cas':23  'habit':24
 *
 * "habitación" CON TILDE → raíz "habit". Perfecto.
 *
 * Pero SearchQueryNormalizer QUITA LAS TILDES de la consulta del ciudadano. Y entonces:
 *
 *     to_tsquery('spanish', 'habitacion:*')  →  'habitacion':*
 *
 * ¡ENTERA! El stemmer español NO RECONOCE "habitacion" sin tilde como palabra española. No la
 * reduce a nada: la deja tal cual.
 *
 * Y 'habitacion' NO es prefijo de 'habit'. Son cosas distintas.
 *
 *     BUSCAMOS 'habitacion'  →  ESTÁ INDEXADO COMO 'habit'  →  CERO RESULTADOS
 *
 * ── El absurdo, en una frase ──
 *
 *     ESTÁBAMOS QUITANDO EL ACENTO Y LUEGO PIDIÉNDOLE A POSTGRESQL QUE ENTENDIERA
 *     UNA PALABRA QUE YA NO ERA ESPAÑOLA.
 *
 * El texto de la ley tiene tildes y se indexa bien. La consulta del ciudadano no las tiene y se
 * busca mal. Los dos lados hablaban idiomas distintos.
 *
 * ── Y por qué nadie lo vio ──
 *
 * Porque NO DA NINGÚN ERROR. "No se encontraron resultados" parece una respuesta perfectamente
 * normal. Nadie sospecharía que el sistema tiene el artículo delante y lo está descartando por
 * una tilde.
 *
 * Es el mismo patrón que los catorce bugs anteriores de este proyecto: el sistema funciona, no
 * falla, no avisa — y no hace lo que tiene que hacer.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA SOLUCIÓN
 * ══════════════════════════════════════════════════════════════════════
 *
 * Una configuración de búsqueda que quita los acentos ANTES del stemmer, y que se usa para las
 * DOS cosas: indexar el texto y procesar la consulta.
 *
 *     TEXTO:    "casa habitación"  →  unaccent  →  stem  →  'cas' 'habit'
 *     CONSULTA: "casa habitacion"  →  unaccent  →  stem  →  'cas' 'habit'
 *                                                                ↑ COINCIDEN
 *
 * Da igual si el ciudadano escribe con tilde o sin ella. Los dos acaban en la misma raíz.
 */
class AcentosEnLaBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private BuscadorService $buscador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buscador = app(BuscadorService::class);
    }

    /**
     * La fracción REAL del artículo 31, con su texto literal y su contexto.
     *
     * El texto lleva TILDES, como la ley de verdad. Y ahí está la gracia: sin tildes, esta prueba
     * pasaría con la configuración rota y no demostraría nada.
     */
    private function cargarLaFraccionDelPredial(): void
    {
        $ley = Regulacion::factory()->create(['nombre' => 'Ley de Hacienda']);

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_FRACCION,
            'numero'        => 'I',
            'texto'         => 'A razón de 2 al millar anual sobre el valor catastral de los predios '
                             . 'destinados totalmente por el contribuyente para su propia casa habitación.',
            'contexto'      => 'SEGUNDO IMPUESTOS. II IMPUESTOS SOBRE EL PATRIMONIO. I IMPUESTO PREDIAL',
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. La configuración existe y hace lo que debe
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PRUEBA MÁS DIRECTA DE TODAS.
     *
     * Con o sin tilde, "habitación" tiene que reducirse a LA MISMA RAÍZ.
     *
     * Esto es el bug entero, en una sola aserción. Si esto falla, todo lo demás también.
     */
    public function test_con_tilde_y_sin_tilde_dan_la_misma_raiz(): void
    {
        $conTilde = DB::selectOne(
            "SELECT to_tsquery('spanish_unaccent', ?) AS q",
            ['habitación:*']
        )->q;

        $sinTilde = DB::selectOne(
            "SELECT to_tsquery('spanish_unaccent', ?) AS q",
            ['habitacion:*']
        )->q;

        $this->assertSame(
            $conTilde,
            $sinTilde,
            "«habitación» y «habitacion» dan raíces DISTINTAS:\n"
            . "  con tilde: {$conTilde}\n"
            . "  sin tilde: {$sinTilde}\n\n"
            . 'Con la configuración "spanish" a secas, el stemmer no reconoce "habitacion" sin '
            . 'tilde como palabra española y la deja ENTERA, mientras que el texto de la ley '
            . '—que sí lleva tilde— se indexa como "habit". No coinciden, y la búsqueda devuelve '
            . "cero.\n\n"
            . 'Comprueba que la migración crear_configuracion_busqueda_sin_acentos se haya '
            . 'ejecutado, y que BuscadorService use spanish_unaccent en TODAS sus consultas.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. El caso real
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * LA PREGUNTA QUE DESTAPÓ TODO ESTO.
     *
     * "¿Qué tasa de impuesto predial corresponde a una casa habitación?" tiene que encontrar la
     * fracción que dice "2 al millar... para su propia casa habitación".
     */
    public function test_la_pregunta_del_predial_encuentra_la_fraccion(): void
    {
        $this->cargarLaFraccionDelPredial();

        $resultados = $this->buscador
            ->buscar('Qué tasa de impuesto predial corresponde a una casa habitación')['resultados'];

        $this->assertStringContainsString(
            '2 al millar',
            $resultados->pluck('fragmento')->implode(' '),
            'No encontró la fracción que responde LITERALMENTE la pregunta. Su texto dice "para su '
            . 'propia casa habitación" y su contexto dice "IMPUESTO PREDIAL". Están todas las '
            . 'palabras. Si no aparece, es que la consulta y el índice usan configuraciones '
            . 'distintas y sus raíces no coinciden.'
        );
    }

    /**
     * Y también SIN TILDES, que es como escribe el 90% de la gente.
     *
     * Un ciudadano en el móvil no pone tildes. Y la ley sí las tiene. El buscador tiene que
     * salvar esa distancia — para eso existe unaccent.
     */
    public function test_la_misma_pregunta_sin_tildes_encuentra_lo_mismo(): void
    {
        $this->cargarLaFraccionDelPredial();

        $resultados = $this->buscador
            ->buscar('que tasa de impuesto predial corresponde a una casa habitacion')['resultados'];

        $this->assertStringContainsString(
            '2 al millar',
            $resultados->pluck('fragmento')->implode(' '),
            'Sin tildes no encuentra nada. Y así es como escribe casi todo el mundo en el móvil.'
        );
    }

    /**
     * Buscar "razon" (sin tilde) encuentra "razón" (con tilde).
     *
     * Es el caso mínimo, y prueba el mecanismo desnudo: la palabra está en el texto CON tilde, se
     * busca SIN ella, y tiene que encontrarse.
     */
    public function test_una_palabra_sin_tilde_encuentra_la_misma_con_tilde(): void
    {
        $this->cargarLaFraccionDelPredial();

        $this->assertGreaterThan(
            0,
            $this->buscador->buscar('razon millar catastral')['resultados']->count(),
            'El texto dice "A RAZÓN de 2 al millar", con tilde. Buscar "razon" sin ella no lo '
            . 'encuentra: los dos lados usan configuraciones distintas.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. Que no se rompa lo que ya funcionaba
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * El stemmer SIGUE funcionando: singular y plural encuentran lo mismo.
     *
     * unaccent quita los acentos, pero el stemmer español TIENE QUE SEGUIR ACTUANDO después. Si
     * la configuración solo quitara acentos y no aplicara spanish_stem, "predios" y "predio"
     * serían palabras distintas — y perderíamos algo que sí funcionaba.
     *
     * Es la mitad que impide que el arreglo rompa otra cosa.
     */
    public function test_el_stemmer_sigue_funcionando(): void
    {
        $this->cargarLaFraccionDelPredial();

        // El texto dice "predios" (plural). Buscar "predio" (singular) debe encontrarlo.
        $this->assertGreaterThan(
            0,
            $this->buscador->buscar('predio catastral millar')['resultados']->count(),
            'El texto dice "predios" en plural y buscar "predio" en singular no lo encuentra. '
            . 'La configuración quita los acentos pero ya no aplica el stemmer español '
            . '(spanish_stem). Hay que hacer LAS DOS COSAS, y en ese orden.'
        );
    }
}
