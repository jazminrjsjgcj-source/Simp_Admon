<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionPaginadorService;
use Illuminate\Console\Command;

/**
 * Rellena la columna `pagina` de los artículos de regulaciones PDF que YA
 * estaban estructuradas antes de que existiera la detección de página.
 *
 * ── Por qué es un comando aparte ─────────────────────────────────────
 *
 * A partir de ahora, estructurar (o re-estructurar) una regulación PDF llena la
 * página de sus artículos automáticamente, dentro de EstructurarRegulacionJob.
 * Pero las leyes cargadas ANTES de ese cambio ya están estructuradas y no se van
 * a re-estructurar solas: su columna `pagina` está en NULL. Este comando las
 * rellena en bloque, sin tener que re-estructurar cada una a mano (lo que además
 * dispararía snapshots, avisos a trámites y llamadas de IA innecesarias).
 *
 * No tiene lógica propia de emparejamiento: reutiliza RegulacionPaginadorService,
 * el mismo que corre en el Job. Es idempotente: volver a correrlo simplemente
 * recalcula las páginas, sin efectos secundarios.
 *
 * Uso:
 *     php artisan regulaciones:paginar                 # todas las PDF estructuradas
 *     php artisan regulaciones:paginar 42              # solo la regulación con id 42
 *     php artisan regulaciones:paginar "Bando"         # las que contengan "Bando" en el nombre
 *     php artisan regulaciones:paginar --solo-faltantes # solo las que aún no tienen página
 */
class PaginarRegulaciones extends Command
{
    protected $signature = 'regulaciones:paginar
                            {regulacion? : ID o parte del nombre de UNA regulación. Sin esto, corre sobre TODAS las PDF estructuradas.}
                            {--solo-faltantes : Solo las regulaciones que aún no tienen ninguna página guardada.}';

    protected $description = 'Rellena la página del PDF original de cada artículo, para que los resultados del buscador abran el PDF en el lugar correcto (#page=N).';

    public function handle(RegulacionPaginadorService $paginador): int
    {
        $regulaciones = $this->regulacionesObjetivo();

        if ($regulaciones->isEmpty()) {
            $this->warn('No hay regulaciones PDF estructuradas que paginar con esos criterios.');

            return self::SUCCESS;
        }

        $this->info("Regulaciones a paginar: {$regulaciones->count()}");
        $this->newLine();

        $totalExactas = 0;

        foreach ($regulaciones as $regulacion) {
            $this->line("• [{$regulacion->id}] {$regulacion->nombre}");

            // El servicio ya se protege solo: si no es PDF o no encuentra el
            // archivo, devuelve 0 sin lanzar. Aquí solo reportamos el resultado.
            $exactas = $paginador->detectarPaginas($regulacion);
            $totalExactas += $exactas;

            $totalArticulos = RegulacionNodo::where('regulacion_id', $regulacion->id)
                ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
                ->count();

            $this->line("    {$exactas} de {$totalArticulos} artículos con página exacta.");
        }

        $this->newLine();
        $this->info("Listo. {$totalExactas} artículos quedaron con página exacta en total.");

        return self::SUCCESS;
    }

    /**
     * Arma la lista de regulaciones sobre las que trabajar, según el argumento y
     * la bandera. Siempre se limita a PDFs estructurados: son las únicas donde
     * paginar tiene sentido (hay un PDF con páginas y hay artículos que ubicar).
     *
     * @return \Illuminate\Support\Collection<int, Regulacion>
     */
    private function regulacionesObjetivo()
    {
        $query = Regulacion::query()
            ->where('extension_original', 'pdf')
            ->where('estructurada', true);

        // Argumento: una regulación concreta, por ID (si es numérico) o por nombre.
        $arg = $this->argument('regulacion');
        if ($arg !== null) {
            if (ctype_digit((string) $arg)) {
                $query->where('id', (int) $arg);
            } else {
                $query->where('nombre', 'like', '%' . $arg . '%');
            }
        }

        // Bandera: solo las que aún no tienen NINGÚN artículo con página. Sirve
        // para el relleno inicial, o para retomar una corrida interrumpida sin
        // rehacer las que ya se completaron.
        if ($this->option('solo-faltantes')) {
            $query->whereHas('nodos', fn ($q) => $q->where('tipo', RegulacionNodo::TIPO_ARTICULO))
                  ->whereDoesntHave('nodos', fn ($q) => $q->where('tipo', RegulacionNodo::TIPO_ARTICULO)
                                                          ->whereNotNull('pagina'));
        }

        return $query->orderBy('id')->get();
    }
}
