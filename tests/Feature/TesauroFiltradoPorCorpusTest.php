<?php

namespace Tests\Feature;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\TesauroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EL TESAURO SE SIEMBRA COMPLETO. EL CORPUS DECIDE QUÉ SE USA.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ SE ESTÁ PROBANDO, Y POR QUÉ IMPORTA
 * ══════════════════════════════════════════════════════════════════════
 *
 * El tesauro tiene entradas de regulaciones que TODAVÍA NO SE HAN SUBIDO: reglamento de
 * construcción, de panteones, de alcoholes.
 *
 *     arquitecto  →  perito responsable, director responsable de obra
 *
 * Hoy, con solo la Ley de Hacienda cargada, "perito responsable" no existe en ningún artículo.
 *
 * ── Y si el tesauro lo metiera igualmente en la consulta ──
 *
 * No daría ningún error. Solo añadiría ramas muertas al tsquery:
 *
 *     (arquitecto:* | perito:* | responsable:* | director:* | obra:*)
 *
 * Y cada rama muerta es una palabra más que PostgreSQL tiene que evaluar para nada. Peor: en un
 * corpus grande, "responsable" o "director" SÍ existirán en otro contexto, y ensuciarían el
 * resultado.
 *
 * ── Lo que se prueba aquí ──
 *
 *   1. Un sinónimo que NO está en ninguna regulación cargada NO se usa.
 *   2. Un sinónimo que SÍ está, se usa.
 *   3. La palabra del ciudadano NUNCA se pierde, exista o no.
 *   4. Al subir una regulación nueva, los sinónimos dormidos DESPIERTAN SOLOS — sin resembrar el
 *      tesauro, sin tocar código, sin que nadie se acuerde de nada.
 *
 * El punto 4 es el que motivó todo esto. La versión anterior filtraba al sembrar, contra el texto
 * de la Ley de Hacienda. Eso construye un tesauro que caduca solo, en silencio, el día que llega
 * la segunda regulación.
 */
class TesauroFiltradoPorCorpusTest extends TestCase
{
    use RefreshDatabase;

    private TesauroService $tesauro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tesauro = app(TesauroService::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. El sinónimo que no existe en el corpus se ignora
    // ═══════════════════════════════════════════════════════════════════════

    public function test_descarta_los_sinonimos_que_no_existen_en_ninguna_regulacion(): void
    {
        $this->cargarArticulo(
            'El impuesto sobre adquisición de bienes inmuebles será el que resulte de aplicar '
            . 'al valor del inmueble la tasa del 3%.'
        );

        $this->sembrarEntrada('comprar', 'adquisicion, hipoteca');

        $palabras = $this->palabrasQueSeVanABuscar('comprar');

        $this->assertContains(
            'adquisicion',
            $palabras,
            '"adquisición" SÍ aparece en el artículo cargado. Tiene que entrar en la búsqueda: es '
            . 'justamente la palabra que el ciudadano no sabe que tiene que escribir.'
        );

        $this->assertNotContains(
            'hipoteca',
            $palabras,
            '"hipoteca" NO aparece en ninguna regulación cargada. Meterla en el tsquery no puede '
            . 'encontrar nada: solo alarga la consulta con una rama muerta.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. La palabra del ciudadano nunca se pierde
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Aunque la ley NO use la palabra del ciudadano, esa palabra se conserva.
     *
     * Quien decide qué hacer con las palabras del ciudadano es el filtro de BuscadorService
     * (descartarPalabrasQueNoDistinguen), no el tesauro. Si el tesauro se pusiera a borrarlas
     * también, habría dos sitios decidiendo lo mismo con criterios distintos — y cuando eso pasa,
     * tarde o temprano se contradicen.
     */
    public function test_la_palabra_original_se_conserva_aunque_la_ley_no_la_use(): void
    {
        $this->cargarArticulo('El impuesto sobre adquisición de bienes inmuebles.');

        $this->sembrarEntrada('comprar', 'adquisicion');

        $this->assertContains(
            'comprar',
            $this->palabrasQueSeVanABuscar('comprar'),
            'La palabra que escribió el ciudadano tiene que seguir ahí. El tesauro AÑADE '
            . 'alternativas; no sustituye la consulta.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. De un término compuesto sobreviven las palabras que existen
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * "traslacion de dominio" con "dominio" ausente del corpus → queda "traslacion".
     *
     * El buscador parte los términos compuestos en palabras sueltas de todas formas (un tsquery no
     * admite frases con ':*'), así que el filtro trabaja palabra por palabra. Tirar el término
     * entero por una palabra ausente perdería la que sí sirve.
     */
    public function test_de_un_termino_compuesto_sobreviven_solo_las_palabras_que_existen(): void
    {
        $this->cargarArticulo('Se causará el impuesto por la traslación de los bienes.');

        $this->sembrarEntrada('vender', 'traslacion de dominio');

        $palabras = $this->palabrasQueSeVanABuscar('vender');

        $this->assertContains(
            'traslacion',
            $palabras,
            '"traslación" existe en el artículo. Aunque venga dentro de un término compuesto, no '
            . 'se puede perder.'
        );

        $this->assertNotContains(
            'dominio',
            $palabras,
            '"dominio" no existe en ninguna regulación cargada. El término compuesto tiene que '
            . 'llegar recortado: solo con las palabras vivas.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. LA PRUEBA QUE MOTIVÓ TODO: el tesauro despierta solo
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Se siembra "arquitecto → perito responsable" HOY, cuando no existe el reglamento que lo usa.
     * Duerme. Se sube el reglamento. Empieza a funcionar. Nadie tocó el tesauro.
     *
     * Si esta prueba falla, el tesauro ha vuelto a ser un archivo que caduca solo.
     */
    public function test_un_sinonimo_dormido_despierta_al_subir_la_regulacion_que_lo_usa(): void
    {
        $this->cargarArticulo('Por la expedición de licencias de construcción se pagarán los derechos.');

        $this->sembrarEntrada('arquitecto', 'perito responsable');

        $this->assertNotContains(
            'perito',
            $this->palabrasQueSeVanABuscar('arquitecto'),
            'Hoy no hay Reglamento de Construcción cargado: "perito" no existe en ningún artículo '
            . 'y no debe usarse.'
        );

        // ── Llega el Reglamento de Construcción ──
        $this->cargarArticulo(
            'Toda obra requerirá la responsiva de un perito responsable inscrito en el padrón.',
            'Reglamento de Construcción'
        );

        // La caché de frecuencias tiene una hora de vida. En producción, subir una regulación la
        // invalida (o expira sola). En la prueba se limpia a mano para medir el comportamiento y
        // no la caché.
        Cache::flush();

        $this->assertContains(
            'perito',
            $this->palabrasQueSeVanABuscar('arquitecto'),
            'Ya existe un artículo que dice "perito responsable". El sinónimo tiene que despertar '
            . 'SOLO, sin resembrar el tesauro y sin tocar una línea de código. Si no lo hace, '
            . 'hemos vuelto al filtro estático: un tesauro que caduca en cuanto llega la segunda '
            . 'regulación.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. LA PALABRA QUE LA LEY NO USA NO SE PUEDE TIRAR: ES LA RAZÓN DEL TESAURO
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * "comprar" tiene frecuencia CERO en la Ley de Hacienda. La ley dice "adquisición".
     *
     * Y el filtro de BuscadorService (descartarPalabrasQueNoDistinguen) tira las palabras de
     * frecuencia cero, con una razón impecable: exigir en un AND una palabra que no está en ningún
     * artículo garantiza cero resultados.
     *
     * Impecable SIN tesauro. Con tesauro, catastrófico: la expansión ocurre DESPUÉS del filtro, así
     * que el tesauro nunca llegaba a ver "comprar". Y el tesauro existe EXACTAMENTE para eso.
     *
     * Resultado real: "cuánto se paga por comprar una casa" quedaba en ['casa'], la consulta era un
     * OR suelto —(casa | habitacion | predio | inmueble)— y devolvía TREINTA artículos sobre
     * predios, con el artículo 38 (el del 3%) hundido entre ellos.
     *
     * No devolvía cero. Devolvía RUIDO. Que es igual de inútil y encima parece que funciona.
     */
    public function test_el_buscador_encuentra_el_articulo_aunque_la_ley_no_use_la_palabra(): void
    {
        // El artículo 38, literal. No dice "comprar". No dice "casa".
        $this->cargarArticulo(
            'El impuesto sobre adquisición de bienes inmuebles será el que resulte de aplicar '
            . 'al valor del inmueble la tasa del 3%.'
        );

        $this->sembrarEntrada('comprar', 'adquisicion, enajenacion');
        $this->sembrarEntrada('casa', 'habitacion, predio, inmueble');

        $resultado = app(\App\Services\BuscadorService::class)
            ->buscar('cuanto se paga por comprar una casa');

        // La clave es 'fragmento', no 'texto'. buscarEnArticulado() devuelve el texto del nodo
        // recortado a 250 caracteres bajo ese nombre. Leer 'texto' devuelve una colección de
        // nulos, y la prueba falla SIEMPRE — diga lo que diga el buscador.
        $textos = collect($resultado['resultados'] ?? [])
            ->pluck('fragmento')
            ->implode(' ');

        $this->assertStringContainsString(
            '3%',
            $textos,
            'El artículo que responde la pregunta NO llegó a los resultados.' . "\n\n"
            . 'La ley nunca dice "comprar": dice "adquisición". Por eso "comprar" tiene frecuencia '
            . 'CERO, y por eso está en el tesauro. Si el filtro de palabras la tira ANTES de que el '
            . 'tesauro pueda traducirla, el tesauro no sirve para nada — y este caso, que es el que '
            . 'lo motivó, seguirá fallando por mucho que se amplíe la tabla.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Las palabras que DE VERDAD acaban dentro del tsquery.
     *
     * expandir() devuelve TÉRMINOS del tesauro, y un término puede ser compuesto:
     *
     *     ['arquitecto', 'perito responsable']
     *                     └───────┬────────┘
     *                       UN elemento, con un espacio en medio
     *
     * Pero eso NO es lo que se busca. PostgreSQL no admite frases con ':*', así que
     * BuscadorService::prepararConsultaFulltext() parte los términos por los espacios:
     *
     *     'perito responsable'   →   perito:* | responsable:*
     *
     * La primera versión de esta prueba comparaba contra los TÉRMINOS, y fallaba:
     *
     *     assertContains('perito', ['arquitecto', 'perito responsable'])   →  FALLA
     *
     * PHPUnit compara con ===, y 'perito responsable' !== 'perito'. El sinónimo estaba
     * perfectamente vivo: lo que estaba mal era la prueba. Y el caso de "traslacion de dominio"
     * PASABA por casualidad, porque ahí sobrevivía una sola palabra y el término quedaba de una
     * pieza.
     *
     *     SE COMPRUEBA LO QUE SE BUSCA. NO LO QUE SE GUARDA.
     */
    private function palabrasQueSeVanABuscar(string $consulta): array
    {
        $terminos = $this->tesauro->expandir([$consulta])[0];

        return collect($terminos)
            ->flatMap(fn (string $termino) => preg_split('/\s+/u', $termino))
            ->filter()
            ->values()
            ->all();
    }

    private function cargarArticulo(string $texto, string $regulacion = 'Ley de Hacienda'): void
    {
        $ley = Regulacion::factory()->create(['nombre' => $regulacion]);

        RegulacionNodo::create([
            'regulacion_id' => $ley->id,
            'tipo'          => RegulacionNodo::TIPO_ARTICULO,
            'numero'        => '1',
            'texto'         => $texto,
            'contexto'      => null,
            'orden'         => 1,
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
        ]);
    }

    private function sembrarEntrada(string $terminoCiudadano, string $terminosLey): void
    {
        DB::table('busqueda_tesauro')->insert([
            'termino_ciudadano' => $terminoCiudadano,
            'terminos_ley'      => $terminosLey,
            'origen'            => 'inicial',
            'activo'            => true,
            'nota'              => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // El tesauro se cachea una hora. Sin esto, la prueba leería la tabla vacía de la prueba
        // anterior y fallaría por una razón que no tiene nada que ver con lo que se está probando.
        Cache::forget('tesauro:completo');
    }
}
