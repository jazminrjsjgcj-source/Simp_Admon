<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\DetectorCatalogosService;
use App\Services\RegulacionConversorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Recupera las tablas-catálogo de una regulación en PDF y las vuelca, legibles,
 * en el nodo-catálogo que el detector marcó.
 *
 * ── Por qué es un comando aparte ─────────────────────────────────────
 *
 * La misma reparación corre dentro de EstructurarRegulacionJob, pero ahí queda
 * atada a que el detector (una llamada de IA por artículo, lento y no
 * determinista) acierte en esa misma corrida. Este comando la desacopla: marca
 * primero con detectar-catalogos, repara después con esto, rápido y fiable, sin
 * re-estructurar la ley entera.
 *
 * Uso (el orden importa: primero marcar, luego reparar):
 *     php artisan regulaciones:detectar-catalogos "Bando" --limpiar
 *     php artisan regulaciones:reparar-tablas "Bando"
 */
class RepararTablasCatalogo extends Command
{
    protected $signature = 'regulaciones:reparar-tablas
                            {regulacion : Nombre (o parte) de la regulación, p. ej. "Bando".}';

    protected $description = 'Extrae las tablas-catálogo de un PDF y las vuelca en el nodo-catálogo ya marcado, para que el asistente reciba el cruce "artículo → clase".';

    public function handle(RegulacionConversorService $conversor, DetectorCatalogosService $detector): int
    {
        $nombre = (string) $this->argument('regulacion');

        $regulacion = Regulacion::where('nombre', 'like', "%{$nombre}%")->first();

        if (! $regulacion) {
            $this->error("No encontré ninguna regulación que contenga «{$nombre}» en el nombre.");

            return self::FAILURE;
        }

        $this->line("Regulación: {$regulacion->nombre} (id {$regulacion->id})");
        $this->newLine();

        // Debe haber un nodo-catálogo marcado; si no, no hay dónde volcar la tabla.
        $tieneCatalogo = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo_referencia', DetectorCatalogosService::TIPO_CATALOGO)
            ->exists();

        if (! $tieneCatalogo) {
            $this->error('No hay ningún nodo marcado como «catalogo_clasificacion» en esta regulación.');
            $this->line('Marca primero el catálogo:');
            $this->line("  php artisan regulaciones:detectar-catalogos \"{$nombre}\" --limpiar");

            return self::FAILURE;
        }

        // El PDF original.
        $rutaOriginal = (string) $regulacion->archivo_original;
        if (! str_ends_with(strtolower($rutaOriginal), '.pdf')) {
            $this->error('La regulación no tiene un PDF original del que extraer tablas.');

            return self::FAILURE;
        }

        $rutaPdf = Storage::disk('local')->path($rutaOriginal);

        $this->line('Extrayendo tablas del PDF con pdfplumber…');
        $pares = $conversor->extraerTablasCatalogo($rutaPdf);

        if ($pares === []) {
            $this->warn('El script no devolvió pares. ¿Está pdfplumber instalado y el PDF trae tablas?');
            $this->line("Pruébalo directo: python3 scripts/extraer_tablas.py {$rutaPdf}");

            return self::FAILURE;
        }

        $this->info(count($pares) . ' par(es) artículo→clase extraídos.');

        $reparados = $detector->repararCatalogoConTabla($regulacion, $pares);

        $this->newLine();
        if ($reparados > 0) {
            $this->info("Tabla volcada en {$reparados} nodo-catálogo. El cruce ya acompañará las respuestas.");
        } else {
            $this->warn('El nodo-catálogo ya tenía la tabla; no se cambió nada.');
        }

        return self::SUCCESS;
    }
}
