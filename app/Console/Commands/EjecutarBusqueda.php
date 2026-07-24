<?php

namespace App\Console\Commands;

use App\Services\BuscadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Lanza una búsqueda COMPLETA desde la terminal, tal como la haría el ciudadano en la web.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EN QUÉ SE DIFERENCIA DE buscador:diagnosticar
 * ══════════════════════════════════════════════════════════════════════
 *
 *   buscador:diagnosticar  →  abre las TRIPAS: qué palabras sobreviven, qué tsquery sale. Es para
 *                             entender POR QUÉ el buscador hace lo que hace.
 *
 *   buscador:buscar        →  ejecuta la búsqueda ENTERA y enseña lo que VE EL CIUDADANO: la
 *                             respuesta del asistente y la lista de resultados. Es para comprobar
 *                             el RESULTADO, sin abrir el navegador.
 *
 * Los dos llaman al mismo BuscadorService::buscar(). Este no reimplementa nada: pregunta y pinta.
 *
 *     php artisan buscador:buscar "cuanto cuesta la multa por extension de la via publica"
 *     php artisan buscador:buscar "..." --sin-cache
 */
class EjecutarBusqueda extends Command
{
    protected $signature = 'buscador:buscar
                            {pregunta : La pregunta, tal como la escribiría un ciudadano.}
                            {--sin-cache : Vacía la caché antes. Imprescindible tras cambiar el tesauro o el reformulador.}';

    protected $description = 'Ejecuta una búsqueda completa desde la terminal y enseña la respuesta y los resultados, como los vería el ciudadano.';

    public function __construct(private BuscadorService $buscador)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('sin-cache')) {
            Cache::flush();
            $this->warn('Caché vaciada: el reformulador y las frecuencias se recalculan.');
        }

        $pregunta = (string) $this->argument('pregunta');

        $this->newLine();
        $this->line('PREGUNTA: ' . $pregunta);
        $this->line(str_repeat('═', 78));

        $inicio    = microtime(true);
        $resultado = $this->buscador->buscar($pregunta);
        $ms        = round((microtime(true) - $inicio) * 1000);

        // ── La respuesta del asistente (lo primero que lee el ciudadano) ──
        $destacada = $resultado['respuesta_destacada'] ?? null;

        $this->newLine();
        $this->line('RESPUESTA DEL ASISTENTE:');
        $this->newLine();

        if ($destacada === null) {
            $this->warn('  (ninguna — el asistente no produjo respuesta destacada)');
        } else {
            $this->line('  ' . wordwrap((string) ($destacada['definicion'] ?? ''), 74, "\n  ", true));
            $this->newLine();
            $this->line('  confianza: ' . ($destacada['confianza'] ?? '—')
                . '   |   ' . ($this->confianzaSignifica($destacada['confianza'] ?? null)));
        }

        // ── La lista de resultados ──
        $resultados = collect($resultado['resultados'] ?? []);

        $this->newLine();
        $this->line(str_repeat('─', 78));
        $this->line('RESULTADOS: ' . $resultados->count()
            . '   |   modo: ' . ($resultado['modo'] ?? '?')
            . '   |   ' . $ms . ' ms');
        $this->newLine();

        if ($resultados->isEmpty()) {
            $this->warn('  (sin resultados)');

            return self::SUCCESS;
        }

        foreach ($resultados->values() as $i => $r) {
            $this->line('  ' . str_pad((string) ($i + 1), 2, ' ', STR_PAD_LEFT) . '. '
                . ($r['titulo'] ?? '?')
                . '  [' . ($r['tipo'] ?? '') . ']');
            $this->line('      ' . mb_substr((string) ($r['fragmento'] ?? ''), 0, 100));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function confianzaSignifica(?string $confianza): string
    {
        return match ($confianza) {
            'generada'    => 'el asistente RESPONDIÓ la pregunta',
            'relacionada' => 'NO la respondió; solo contó qué dicen las fuentes del tema (rendición)',
            default       => 'definición curada del diccionario jurídico',
        };
    }
}
