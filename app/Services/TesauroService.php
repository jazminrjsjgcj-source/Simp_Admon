<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expande la consulta con el vocabulario de la ley.
 *
 * El ciudadano dice "comprar una casa" y la ley dice "adquisición de bienes
 * inmuebles". El tesauro traduce cada palabra a los términos que la ley sí usa:
 *
 *     comprar:* & casa:*
 *       →  (comprar:* | adquisicion:* | enajenacion:*) & (casa:* | predio:* | inmueble:*)
 *
 * Lo importante es dónde van los paréntesis: el OR va DENTRO de cada término y el
 * AND se mantiene entre términos. Así el artículo tiene que hablar de comprar Y de
 * casas, no basta con que mencione una de las ocho palabras. Un OR entre todo
 * devolvería cualquier artículo que diga "predio", que en una ley de hacienda son
 * cientos. La palabra original siempre se conserva, por si la ley sí la usa.
 *
 * El tesauro se siembra completo, con vocabulario de regulaciones que aún no se han
 * cargado, y es el corpus quien decide qué está disponible: antes de meter un
 * sinónimo en la consulta se comprueba que exista en alguna regulación de la base.
 * Así una entrada como "panteón → nicho, fosa" aporta lo que hoy existe y empieza a
 * aportar el resto en cuanto se suba el reglamento correspondiente, sin tocar nada.
 */
class TesauroService
{
    public function __construct(
        private VocabularioCorpusService $vocabulario,
    ) {}

    /**
     * Expande una lista de palabras con sus equivalentes en la ley.
     *
     * Solo se devuelven los sinónimos que EXISTEN en las regulaciones cargadas. Un sinónimo que
     * no aparece en ningún artículo no puede encontrar nada: solo alarga el tsquery.
     *
     * @param  array<string> $palabras  Las palabras limpias de la consulta.
     * @return array<array<string>>     Para cada palabra, la lista de alternativas (incluyéndola).
     *
     * Ejemplo:
     *     ['comprar', 'casa']
     *     →
     *     [
     *       ['comprar', 'adquisicion', 'adquirir', 'enajenacion'],
     *       ['casa', 'casa habitacion', 'predio urbano', 'bien inmueble'],
     *     ]
     */
    public function expandir(array $palabras): array
    {
        if ($palabras === []) {
            return [];
        }

        $tesauro = $this->tesauro();

        return collect($palabras)
            ->map(function (string $palabra) use ($tesauro) {
                $clave = $this->normalizar($palabra);

                // La palabra original SIEMPRE se conserva, exista o no en el corpus. Si la ley sí
                // la usa —y muchas veces la usa— no se pierde nada por traducirla. Y si no la usa,
                // de eso ya se ocupa el filtro de palabras del buscador, que es quien manda sobre
                // las palabras del ciudadano.
                $alternativas = [$palabra];

                foreach ($tesauro[$clave] ?? [] as $sinonimo) {
                    $utiles = $this->palabrasQueExistenEnElCorpus($sinonimo);

                    if ($utiles !== '') {
                        $alternativas[] = $utiles;
                    }
                }

                return array_values(array_unique($alternativas));
            })
            ->all();
    }

    /**
     * ¿Aporta algo el tesauro a esta consulta?
     *
     * Sirve para no hacer trabajo de más: si ninguna palabra está en el tesauro, la expansión
     * devolvería exactamente lo mismo que entró, y el buscador puede seguir por el camino normal.
     */
    public function aportaAlgo(array $palabras): bool
    {
        $tesauro = $this->tesauro();

        foreach ($palabras as $palabra) {
            if (isset($tesauro[$this->normalizar($palabra)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Tiene esta palabra una traducción VIVA en el tesauro?
     *
     * "Viva" = el tesauro la conoce, Y al menos uno de sus sinónimos existe en las regulaciones
     * cargadas. No basta con estar en la tabla: "arquitecto" está en el tesauro, pero mientras no
     * se suba el Reglamento de Construcción, todos sus sinónimos duermen y la entrada no sirve
     * para nada.
     *
     * ══════════════════════════════════════════════════════════════════════
     * PARA QUÉ EXISTE, Y POR QUÉ NO ES UN DETALLE
     * ══════════════════════════════════════════════════════════════════════
     *
     * BuscadorService tira las palabras que NO EXISTEN en la ley (frecuencia cero), con una razón
     * impecable: exigir en un AND una palabra que no aparece en ningún artículo garantiza cero
     * resultados.
     *
     * Y esa regla, sin tesauro, es correcta. Con tesauro, es catastrófica.
     *
     * Porque el tesauro existe EXACTAMENTE para las palabras que la ley no usa:
     *
     *     comprar   →  frecuencia CERO en la Ley de Hacienda. Nunca dice "comprar".
     *                  Y por eso está en el tesauro, que la traduce a "adquisicion".
     *
     * Si el filtro la tira ANTES de que el tesauro la vea, la traducción no llega a ocurrir. El
     * filtro estaba matando precisamente a los pacientes que el tesauro venía a curar.
     *
     * Con este método, el buscador puede preguntar antes de tirar: "esta palabra no está en la
     * ley... pero ¿sabes traducirla?". Y si la respuesta es sí, la palabra se conserva, porque su
     * expansión SÍ va a encontrar algo.
     */
    public function tieneTraduccionUtil(string $palabra): bool
    {
        $alternativas = $this->expandir([$palabra])[0] ?? [];

        // expandir() SIEMPRE devuelve la palabra original. Si devuelve solo eso, el tesauro no
        // aportó nada: o no la conoce, o todos sus sinónimos duermen.
        return count($alternativas) > 1;
    }

    /**
     * Se queda con las palabras del sinónimo que EXISTEN en alguna regulación cargada.
     *
     * Un término del tesauro puede ser compuesto: "traslacion de dominio", "casa habitacion".
     * El buscador los parte en palabras sueltas de todas formas (un tsquery no admite frases con
     * ':*'), así que el filtro trabaja palabra por palabra:
     *
     *     "traslacion de dominio"   con "dominio" ausente del corpus   →  "traslacion"
     *     "perito responsable"      con las dos ausentes               →  ""  (se descarta entero)
     *
     * Devuelve cadena vacía si no sobrevive ninguna palabra. Quien llama la descarta.
     */
    private function palabrasQueExistenEnElCorpus(string $termino): string
    {
        $vivas = collect(preg_split('/\s+/u', trim($termino)))
            ->filter(fn (string $palabra) => $this->vocabulario->existe($palabra))
            ->all();

        return implode(' ', $vivas);
    }

    /**
     * El tesauro entero, en memoria.
     *
     * Se cachea una hora: son unas decenas de filas y no cambian cada minuto. Consultar la tabla
     * en cada búsqueda sería una consulta más por cada palabra de cada consulta de cada ciudadano.
     *
     * OJO: lo que se cachea es la TABLA, no el filtro contra el corpus. El filtro tiene su propia
     * caché (en VocabularioCorpusService, también de una hora), y por eso subir una regulación
     * nueva empieza a surtir efecto sin resembrar nada.
     */
    private function tesauro(): array
    {
        return Cache::remember('tesauro:completo', now()->addHour(), function () {
            return DB::table('busqueda_tesauro')
                ->where('activo', true)
                ->pluck('terminos_ley', 'termino_ciudadano')
                ->map(fn ($terminos) => collect(explode(',', (string) $terminos))
                    ->map(fn ($t) => trim($t))
                    ->filter()
                    ->values()
                    ->all())
                ->all();
        });
    }

    /**
     * Minúsculas y sin acentos, para comparar contra el tesauro.
     *
     * El tesauro se siembra ya normalizado ('adquisicion', no 'adquisición') y la consulta del
     * ciudadano llega normalizada por SearchQueryNormalizer. Se normaliza igualmente aquí por si
     * alguien añade una entrada a mano con acentos.
     *
     * Es el mismo principio que arreglamos en el buscador: LOS DOS LADOS TIENEN QUE HABLAR EL
     * MISMO IDIOMA. Un tesauro con acentos y una consulta sin ellos no se encuentran nunca, y no
     * dan ningún error: simplemente no traducen nada.
     */
    private function normalizar(string $palabra): string
    {
        return mb_strtolower(Str::ascii(trim($palabra)));
    }
}
