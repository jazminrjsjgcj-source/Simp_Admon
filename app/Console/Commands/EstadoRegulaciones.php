<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Lista las regulaciones y señala cuáles conviene re-estructurar porque se
 * estructuraron con una versión anterior del pipeline (o desconocida).
 *
 * Es la red contra el fallo silencioso de "cambié el estructurador/detector/
 * extractor y olvidé re-estructurar". Solo INFORMA — no re-estructura nada; la
 * decisión (y el cuándo) son de quien lo corre.
 *
 *     php artisan regulaciones:estado
 */
class EstadoRegulaciones extends Command
{
    protected $signature = 'regulaciones:estado';

    protected $description = 'Lista las regulaciones y marca cuáles necesitan re-estructurar (pipeline desactualizado).';

    public function handle(): int
    {
        $regulaciones = Regulacion::query()
            ->orderBy('id')
            ->get(['id', 'nombre', 'pipeline_version', 'estructurado_en']);

        if ($regulaciones->isEmpty()) {
            $this->info('No hay regulaciones cargadas.');

            return self::SUCCESS;
        }

        $this->line('Versión de pipeline vigente: ' . Regulacion::PIPELINE_VERSION);
        $this->newLine();

        $filas = $regulaciones->map(fn (Regulacion $r) => [
            $r->id,
            Str::limit($r->nombre, 45),
            $r->pipeline_version ?? '—',
            optional($r->estructurado_en)->format('Y-m-d H:i') ?? '—',
            $r->estaDesactualizada() ? '⚠ re-estructurar' : '✓ al día',
        ]);

        $this->table(['id', 'Regulación', 'versión', 'estructurada', 'estado'], $filas);

        $desactualizadas = $regulaciones->filter(fn (Regulacion $r) => $r->estaDesactualizada())->count();

        $this->newLine();
        if ($desactualizadas > 0) {
            $this->warn("{$desactualizadas} regulación(es) con pipeline desactualizado.");
            $this->line('Re-estructúralas desde su ficha (botón «Estructurar articulado») para ponerlas al día.');
        } else {
            $this->info('Todas las regulaciones están al día con el pipeline vigente.');
        }

        return self::SUCCESS;
    }
}
