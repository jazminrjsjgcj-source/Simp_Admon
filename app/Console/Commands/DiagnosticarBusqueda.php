<?php

namespace App\Console\Commands;

use App\Services\SearchQueryNormalizer;
use App\Services\TesauroService;
use App\Services\VocabularioCorpusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Abre el buscador en canal y enseña qué le pasa a cada palabra de la pregunta.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ EXISTE
 * ══════════════════════════════════════════════════════════════════════
 *
 * "cuánto se paga por comprar una casa" devolvía 30 resultados y el artículo 38 no estaba entre
 * ellos. Y para averiguar por qué, había que adivinar entre cuatro sospechosos:
 *
 *   · ¿El normalizador se comió una palabra? (a "pagar" se la come, y hace bien)
 *   · ¿El filtro de palabras comunes tiró la palabra clave?
 *   · ¿El tesauro tradujo, o ni la vio?
 *   · ¿El tsquery final es el que creo que es?
 *
 * Cada uno de esos pasos es correcto por separado y ninguno da error. El fallo aparece en la
 * COSTURA entre ellos — y las costuras no se ven.
 *
 * Este comando las enseña, una a una:
 *
 *     php artisan buscador:diagnosticar "cuanto se paga por comprar una casa"
 *     php artisan buscador:diagnosticar "..." --contiene="3%"
 *
 * Con --contiene, además busca de verdad y dice si el artículo que responde llegó, en qué PUESTO
 * quedó y con qué score. Que es la pregunta que de verdad importa: no si aparece, sino si aparece
 * ANTES que el ruido.
 */
class DiagnosticarBusqueda extends Command
{
    protected $signature = 'buscador:diagnosticar
                            {pregunta : La pregunta, tal como la escribiría un ciudadano.}
                            {--contiene= : Un texto que DEBE aparecer en algún resultado (p. ej. "3%").}
                            {--sin-cache : Vacía la caché antes. Úsalo tras resembrar el tesauro.}';

    protected $description = 'Enseña, paso a paso, qué le pasa a cada palabra de una pregunta y qué consulta acaba ejecutando PostgreSQL.';

    public function __construct(
        private SearchQueryNormalizer $normalizador,
        private TesauroService $tesauro,
        private VocabularioCorpusService $vocabulario,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('sin-cache')) {
            Cache::flush();
            $this->warn('Caché vaciada.');
        }

        $pregunta = (string) $this->argument('pregunta');

        $this->newLine();
        $this->line('PREGUNTA: ' . $pregunta);
        $this->line(str_repeat('═', 78));

        // ── PASO 1: el normalizador ──────────────────────────────────────
        $normalizado = $this->normalizador->normalizar($pregunta);
        $palabras    = $normalizado['palabras'];

        $this->newLine();
        $this->line('PASO 1 — EL NORMALIZADOR quita las palabras que describen la FORMA de la');
        $this->line('         pregunta ("cuánto", "se paga", "necesito") y deja el TEMA.');
        $this->newLine();
        $this->line('  quedan: [' . implode(', ', $palabras) . ']');

        if ($palabras === []) {
            $this->error('  No quedó NINGUNA palabra. La búsqueda no puede acotar nada.');

            return self::FAILURE;
        }

        // ── PASO 2: el filtro de palabras ────────────────────────────────
        $totalNodos = (int) DB::table('regulacion_nodos')->whereNull('deleted_at')->count();
        $umbral     = max(20, (int) ceil($totalNodos * 0.05));

        $this->newLine();
        $this->line('PASO 2 — EL FILTRO tira las palabras que NO EXISTEN en el corpus (buscarlas en');
        $this->line('         un AND garantiza cero) y las DEMASIADO COMUNES (no acotan nada).');
        $this->newLine();
        $this->line('  Corpus: ' . $totalNodos . ' artículos. Umbral de "demasiado común": ' . $umbral . '.');
        $this->newLine();

        $filas       = [];
        $sobreviven  = [];

        foreach ($palabras as $palabra) {
            $frecuencia = $this->vocabulario->frecuencia($palabra);
            $traducible = $this->tesauro->tieneTraduccionUtil($palabra);

            if ($frecuencia === 0 && $traducible) {
                $veredicto = 'SE QUEDA (rescatada por el tesauro)';
                $sobreviven[] = $palabra;
            } elseif ($frecuencia === 0) {
                $veredicto = 'SE TIRA (no está en la ley y el tesauro no sabe traducirla)';
            } elseif ($frecuencia > $umbral) {
                $veredicto = 'SE TIRA (demasiado común: no distingue nada)';
            } else {
                $veredicto = 'SE QUEDA';
                $sobreviven[] = $palabra;
            }

            $filas[] = [$palabra, $frecuencia, $traducible ? 'sí' : 'no', $veredicto];
        }

        $this->table(['palabra', 'artículos', '¿el tesauro la traduce?', 'veredicto'], $filas);

        if ($sobreviven === []) {
            $this->warn('  El filtro se lo llevó TODO. El buscador usará las palabras originales como respaldo.');
            $sobreviven = $palabras;
        }

        // ── PASO 3: el tesauro ───────────────────────────────────────────
        $this->newLine();
        $this->line('PASO 3 — EL TESAURO traduce cada palabra al vocabulario de la ley. El OR va');
        $this->line('         DENTRO de cada palabra; el AND ENTRE ellas SE MANTIENE.');
        $this->newLine();

        $expandidas = $this->tesauro->expandir($sobreviven);
        $brazos     = [];

        foreach ($sobreviven as $i => $palabra) {
            $alternativas = $expandidas[$i] ?? [$palabra];

            $variantes = [];
            foreach ($alternativas as $termino) {
                foreach (preg_split('/\s+/u', $termino) as $trozo) {
                    $limpia = preg_replace('/[&|!():*\'"]/', '', trim($trozo));
                    if (mb_strlen($limpia) >= 3) {
                        $variantes[$limpia . ':*'] = true;
                    }
                }
            }

            $variantes = array_keys($variantes);
            $brazos[]  = count($variantes) === 1 ? $variantes[0] : '(' . implode(' | ', $variantes) . ')';

            $this->line('  ' . $palabra . '  →  ' . implode(', ', $alternativas));
        }

        // ── PASO 4: el tsquery ───────────────────────────────────────────
        $tsquery = implode(' & ', $brazos);

        $this->newLine();
        $this->line('PASO 4 — LO QUE POSTGRESQL EJECUTA DE VERDAD:');
        $this->newLine();
        $this->line('  ' . $tsquery);
        $this->newLine();

        if (count($brazos) === 1) {
            $this->warn('  ¡SOLO HAY UN BRAZO! No hay AND que acote nada: esto es un OR suelto, y en una');
            $this->warn('  ley eso devuelve cientos de artículos. Mira el PASO 2: alguna palabra se tiró.');
            $this->newLine();
        }

        // ── PASO 5: ¿llega el artículo que responde? ──────────────────────
        $buscado = $this->option('contiene');

        if ($buscado === null) {
            $this->line('  (usa --contiene="3%" para comprobar si el artículo que responde llega, y en qué puesto)');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line('PASO 5 — ¿LLEGA EL ARTÍCULO QUE RESPONDE, Y EN QUÉ PUESTO?');
        $this->newLine();

        // ══════════════════════════════════════════════════════════════════
        // AQUÍ SE LLAMA AL BUSCADOR DE VERDAD. NO SE IMITA.
        // ══════════════════════════════════════════════════════════════════
        //
        // La primera versión de este comando montaba su propia consulta con ts_rank a secas. Y el
        // buscador real usa ts_rank(..., 2) —normalizado por longitud—, además de descartar los
        // nodos estructurales y los rótulos.
        //
        // Resultado: el diagnóstico decía "ENCONTRADO en el puesto 1, el caso funciona" mientras
        // buscador:evaluar decía que no aparecía. Los dos con razón, midiendo cosas distintas.
        //
        // Una herramienta de diagnóstico que no mide LO MISMO que el sistema no diagnostica nada:
        // miente con autoridad. Los pasos 1 a 4 pueden reconstruirse (son visibilidad); el
        // resultado final, NO.
        $resultado = app(\App\Services\BuscadorService::class)->buscar($pregunta);

        $resultados = collect($resultado['resultados'] ?? []);

        $this->line('  Modo de búsqueda: ' . ($resultado['modo'] ?? '?'));
        $this->line('  Resultados que devuelve el buscador: ' . $resultados->count());
        $this->newLine();

        $puesto = null;

        foreach ($resultados->values() as $i => $r) {
            if (str_contains((string) ($r['fragmento'] ?? ''), (string) $buscado)) {
                $puesto = $i + 1;
                break;
            }
        }

        if ($puesto !== null) {
            $this->info('  ENCONTRADO en el puesto ' . $puesto . ' de ' . $resultados->count() . '.');
            $this->line('  ' . ($resultados[$puesto - 1]['titulo'] ?? '') . ': '
                . mb_substr((string) ($resultados[$puesto - 1]['fragmento'] ?? ''), 0, 90) . '…');
            $this->newLine();
            $this->info('  EL CASO FUNCIONA.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->error('  NO ESTÁ entre lo que devuelve el buscador.');
        $this->newLine();
        $this->line('  Los ' . min(8, $resultados->count()) . ' primeros que SÍ devuelve:');

        foreach ($resultados->take(8)->values() as $i => $r) {
            $this->line('   ' . ($i + 1) . '. ' . ($r['titulo'] ?? '?') . ' — '
                . mb_substr((string) ($r['fragmento'] ?? ''), 0, 60) . '…');
        }

        $this->newLine();
        $this->line('  Contrástalo con el PASO 4: si el tsquery tiene un solo brazo, no hay AND que');
        $this->line('  acote y el artículo bueno compite contra media ley. Si tiene dos y aun así no');
        $this->line('  llega, el problema es de RANKING, no de vocabulario.');
        $this->newLine();

        return self::FAILURE;
    }
}
