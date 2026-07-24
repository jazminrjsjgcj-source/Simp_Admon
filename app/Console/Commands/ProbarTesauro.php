<?php

namespace App\Console\Commands;

use App\Services\TesauroService;
use App\Services\VocabularioCorpusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Enseña QUÉ hace el tesauro con una palabra, y POR QUÉ.
 *
 * ══════════════════════════════════════════════════════════════════════
 * PARA QUÉ EXISTE
 * ══════════════════════════════════════════════════════════════════════
 *
 * El tesauro ahora tiene dos filtros, y ninguno de los dos se ve desde fuera:
 *
 *   1. La CURACIÓN, hecha a mano en el seeder. Decide qué traducciones son correctas.
 *   2. El CORPUS, comprobado en cada búsqueda. Decide qué sinónimos existen de verdad.
 *
 * Y el segundo es invisible. Un sinónimo que no existe en ninguna regulación no da ningún error:
 * simplemente no se usa. Igual que no daba ningún error la búsqueda que devolvía cero por una
 * tilde.
 *
 * Este comando lo hace visible. Enseña, sinónimo por sinónimo, en cuántos artículos aparece y si
 * entra o no entra en la consulta.
 *
 *     php artisan tesauro:probar comprar     ← una palabra
 *     php artisan tesauro:probar             ← el tesauro entero: qué está vivo y qué duerme
 *
 * ── Lo que NO hace ──
 *
 * No dice si el buscador encuentra el artículo correcto. Eso lo dicen buscador:probar y
 * buscador:evaluar. Este comando responde a una sola pregunta: ¿el tesauro está traduciendo lo
 * que yo creo que traduce?
 */
class ProbarTesauro extends Command
{
    protected $signature = 'tesauro:probar
                            {palabra? : La palabra del ciudadano. Sin ella, audita el tesauro entero.}
                            {--sin-cache : Vacía la caché antes de mirar. Úsalo tras subir una regulación.}';

    protected $description = 'Enseña a qué traduce el tesauro una palabra, y qué sinónimos existen de verdad en las regulaciones cargadas.';

    public function __construct(
        private TesauroService $tesauro,
        private VocabularioCorpusService $vocabulario,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('sin-cache')) {
            Cache::flush();
            $this->warn('Caché vaciada: las frecuencias se vuelven a contar contra la base de datos.');
        }

        $this->mostrarElCorpus();

        $palabra = $this->argument('palabra');

        return $palabra === null
            ? $this->auditarTesauroCompleto()
            : $this->explicarUnaPalabra((string) $palabra);
    }

    /**
     * Cuántas regulaciones y cuántos artículos hay cargados AHORA MISMO.
     *
     * Va primero porque es la clave para leer todo lo demás: un sinónimo "muerto" con una sola
     * regulación cargada no está mal escrito — está esperando a que subas la suya.
     */
    private function mostrarElCorpus(): void
    {
        $nodos = DB::table('regulacion_nodos')->whereNull('deleted_at')->count();

        $regulaciones = DB::table('regulaciones')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre');

        $this->newLine();
        $this->line('CORPUS CARGADO: ' . $regulaciones->count() . ' regulaciones, ' . $nodos . ' artículos.');

        foreach ($regulaciones as $nombre) {
            $this->line('  · ' . $nombre);
        }

        $this->line(str_repeat('─', 78));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Una palabra: qué le pasa, paso a paso
    // ═══════════════════════════════════════════════════════════════════════

    private function explicarUnaPalabra(string $palabra): int
    {
        $entrada = DB::table('busqueda_tesauro')
            ->where('termino_ciudadano', $palabra)
            ->first();

        if ($entrada === null) {
            $this->error('«' . $palabra . '» NO está en el tesauro.');
            $this->line('  El buscador la usará tal cual, sin traducir. Si la ley la llama de otra');
            $this->line('  forma, esta búsqueda no encontrará nada — y ese es el caso que hay que');
            $this->line('  añadir a database/seeders/TesauroJuridicoSeeder.php.');

            return self::FAILURE;
        }

        if (! $entrada->activo) {
            $this->warn('«' . $palabra . '» está en el tesauro pero APAGADA (activo = false).');
            $this->line('  Alguien decidió que esta traducción no servía. La nota dice:');
            $this->line('  ' . ($entrada->nota ?? '(sin nota)'));

            return self::SUCCESS;
        }

        $this->line('PALABRA DEL CIUDADANO: ' . $palabra);
        $this->line('LO QUE DICE EL TESAURO: ' . $entrada->terminos_ley);

        if ($entrada->nota !== null) {
            $this->newLine();
            $this->line('  Nota: ' . $entrada->nota);
        }

        $this->newLine();
        $this->line('SINÓNIMO POR SINÓNIMO — ¿existe en las regulaciones cargadas?');
        $this->newLine();

        $filas = [];

        foreach (explode(',', (string) $entrada->terminos_ley) as $sinonimo) {
            foreach (preg_split('/\s+/u', trim($sinonimo)) as $unaPalabra) {
                if ($unaPalabra === '') {
                    continue;
                }

                $frecuencia = $this->vocabulario->frecuencia($unaPalabra);

                $filas[] = [
                    $unaPalabra,
                    $frecuencia,
                    $frecuencia > 0 ? 'SE USA' : 'duerme',
                ];
            }
        }

        $this->table(['palabra de la ley', 'artículos', '¿entra en la búsqueda?'], $filas);

        $this->line('  «duerme» NO es un error. Significa que esa palabra no existe en ninguna');
        $this->line('  regulación cargada todavía. El día que subas la que la usa, entra sola —');
        $this->line('  sin resembrar el tesauro y sin tocar código.');

        // ── Y ahora, lo que de verdad le llega a PostgreSQL ──
        $alternativas = $this->tesauro->expandir([$palabra])[0];

        $this->newLine();
        $this->line(str_repeat('─', 78));
        $this->line('LO QUE EL BUSCADOR VA A BUSCAR DE VERDAD:');
        $this->newLine();
        $this->line('  (' . implode(' | ', array_map(fn ($a) => str_replace(' ', ':* | ', $a) . ':*', $alternativas)) . ')');
        $this->newLine();
        $this->line('  El OR va DENTRO de la palabra. Si la consulta tiene más palabras, el AND');
        $this->line('  entre ellas SE MANTIENE: el artículo tiene que hablar de las dos cosas.');
        $this->newLine();

        // La palabra original SIEMPRE está. Si es lo único que hay, el tesauro no aportó nada.
        if (count($alternativas) === 1) {
            $this->warn('EL TESAURO NO APORTÓ NADA a esta palabra.');
            $this->line('  Todos sus sinónimos duermen: ninguno existe en las regulaciones cargadas.');
            $this->line('  La búsqueda se hará igual que si el tesauro no existiera.');
        }

        return self::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // El tesauro entero: cuánto está vivo y cuánto duerme
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * La foto de conjunto.
     *
     * Con una sola regulación cargada, es NORMAL que muchas entradas duerman: el tesauro se siembra
     * completo, con vocabulario de reglamentos que aún no existen en la base.
     *
     * Lo que hay que vigilar es la última columna. Una entrada con CERO sinónimos vivos no hace
     * nada hoy. Si esperabas que hiciera algo, es que la palabra de la ley está mal escrita.
     */
    private function auditarTesauroCompleto(): int
    {
        $entradas = DB::table('busqueda_tesauro')
            ->where('activo', true)
            ->orderBy('termino_ciudadano')
            ->get();

        if ($entradas->isEmpty()) {
            $this->error('El tesauro está VACÍO. Corre: php artisan db:seed --class=TesauroJuridicoSeeder');

            return self::FAILURE;
        }

        $filas       = [];
        $conAlgoVivo = 0;

        foreach ($entradas as $entrada) {
            $vivos   = [];
            $dormidos = [];

            foreach (explode(',', (string) $entrada->terminos_ley) as $sinonimo) {
                foreach (preg_split('/\s+/u', trim($sinonimo)) as $unaPalabra) {
                    if ($unaPalabra === '') {
                        continue;
                    }

                    if ($this->vocabulario->existe($unaPalabra)) {
                        $vivos[$unaPalabra] = true;
                    } else {
                        $dormidos[$unaPalabra] = true;
                    }
                }
            }

            if ($vivos !== []) {
                $conAlgoVivo++;
            }

            $filas[] = [
                $entrada->termino_ciudadano,
                count($vivos) > 0 ? implode(', ', array_keys($vivos)) : '—',
                count($dormidos),
            ];
        }

        $this->table(
            ['ciudadano dice', 'palabras de la ley que SE USAN hoy', 'dormidas'],
            $filas
        );

        $total = $entradas->count();

        $this->newLine();
        $this->line('RESUMEN');
        $this->line('  Entradas activas en el tesauro: ' . $total);
        $this->line('  Con al menos un sinónimo vivo:  ' . $conAlgoVivo);
        $this->line('  Sin ninguno (duermen enteras):  ' . ($total - $conAlgoVivo));
        $this->newLine();
        $this->line('  Las que duermen NO son un fallo: son vocabulario de regulaciones que todavía');
        $this->line('  no has subido (construcción, panteones, alcoholes). Vuelve a correr esto');
        $this->line('  después de subir una y verás bajar ese número solo.');
        $this->newLine();

        return self::SUCCESS;
    }
}
