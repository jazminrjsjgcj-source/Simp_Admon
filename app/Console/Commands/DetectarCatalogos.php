<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\DetectorCatalogosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detecta con IA los artículos-catálogo de una regulación y los etiqueta.
 *
 * Es la pieza que hace REPLICABLE el cruce de cadenas: en vez de frases hardcodeadas de una ley
 * concreta, la IA entiende qué es un catálogo en CUALQUIER ley. Corre una vez, al cargar (o a
 * mano con este comando para leyes ya cargadas).
 *
 * Uso:
 *     php artisan regulaciones:detectar-catalogos "Bando"          detecta y marca
 *     php artisan regulaciones:detectar-catalogos "Bando" --dry    enseña qué HAY marcado, sin llamar a la IA
 *     php artisan regulaciones:detectar-catalogos "Bando" --limpiar  borra el marcado y vuelve a detectar
 *
 * Ver docs/arquitectura-jurisdiccion-y-catalogos.md
 */
class DetectarCatalogos extends Command
{
    protected $signature = 'regulaciones:detectar-catalogos
                            {regulacion : Nombre (o parte) de la regulación, p. ej. "Bando".}
                            {--dry : Solo enseña qué artículos están marcados ahora, sin llamar a la IA.}
                            {--limpiar : Borra el marcado existente antes de volver a detectar.}';

    protected $description = 'Detecta con IA los artículos-catálogo (escalas, tarifas, definiciones) de una regulación y los etiqueta, para que acompañen a toda respuesta sobre ella.';

    public function handle(DetectorCatalogosService $detector): int
    {
        $nombre = (string) $this->argument('regulacion');

        $regulacion = Regulacion::where('nombre', 'like', "%{$nombre}%")->first();

        if (! $regulacion) {
            $this->error("No encontré ninguna regulación que contenga «{$nombre}» en el nombre.");

            return self::FAILURE;
        }

        $this->line("Regulación: {$regulacion->nombre} (id {$regulacion->id})");
        $this->newLine();

        // --dry: solo enseñar lo que ya está marcado, sin tocar la IA.
        if ($this->option('dry')) {
            $this->mostrarMarcados($regulacion);

            return self::SUCCESS;
        }

        // --limpiar: borrar el marcado previo para empezar de cero.
        if ($this->option('limpiar')) {
            $borrados = RegulacionNodo::where('regulacion_id', $regulacion->id)
                ->whereNotNull('tipo_referencia')
                ->update(['tipo_referencia' => null]);
            $this->warn("Marcado anterior borrado: {$borrados} artículo(s).");

            // --limpiar es "empezar de cero", así que también se vacía la caché de la
            // IA: sin esto, la re-detección reusaría las respuestas cacheadas y no sería
            // un forzado real. (La caché es compartida por contenido; en un corpus
            // pequeño, vaciarla entera es inofensivo: solo obliga a re-preguntar.)
            $cache = DB::table('clasificaciones_ia')->delete();
            $this->warn("Caché de clasificaciones vaciada: {$cache} entrada(s).");
            $this->newLine();
        }

        $this->line('Consultando a la IA artículo por artículo… (puede tardar en leyes grandes)');

        $marcados = $detector->detectarYMarcar($regulacion);

        $this->newLine();
        $this->info("Detección completa: {$marcados} artículo(s) marcados como referencia.");
        $this->newLine();
        $this->mostrarMarcados($regulacion);

        $this->newLine();
        $this->line('Estos artículos ahora acompañarán a cualquier respuesta del asistente sobre esta regulación.');
        $this->line('Si alguno está mal, córrelo a mano y vuelve a probar con buscador:buscar.');

        return self::SUCCESS;
    }

    private function mostrarMarcados(Regulacion $regulacion): void
    {
        $marcados = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->whereNotNull('tipo_referencia')
            ->get(['numero', 'tipo_referencia', 'texto']);

        if ($marcados->isEmpty()) {
            $this->warn('No hay artículos marcados como referencia en esta regulación.');

            return;
        }

        $this->line('Artículos de referencia marcados:');
        foreach ($marcados as $nodo) {
            $this->line(sprintf(
                '  [Art. %-5s] %-24s %s…',
                $nodo->numero,
                $nodo->tipo_referencia,
                mb_substr(trim($nodo->texto), 0, 60)
            ));
        }
    }
}
