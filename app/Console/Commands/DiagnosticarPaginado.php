<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionConversorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Diagnostico de un solo uso para el paginador. NO modifica nada: lee el PDF y
 * reporta, probando la MISMA deteccion que usa el paginador (encabezado
 * "Articulo N" a inicio de renglon) y volcando el texto crudo.
 *
 *   php artisan regulaciones:diag-pagina <regulacion> <numero>
 */
class DiagnosticarPaginado extends Command
{
    protected $signature = 'regulaciones:diag-pagina
                            {regulacion : ID o parte del nombre de la regulacion}
                            {numero : Numero de articulo (p. ej. 86)}';

    protected $description = 'Diagnostica por que un articulo se ubica en cierta pagina (solo lee).';

    public function handle(RegulacionConversorService $conversor): int
    {
        $arg = (string) $this->argument('regulacion');
        $regulacion = ctype_digit($arg)
            ? Regulacion::find((int) $arg)
            : Regulacion::where('nombre', 'like', '%' . $arg . '%')->first();

        if ($regulacion === null) {
            $this->error("No se encontro regulacion con: {$arg}");
            return self::FAILURE;
        }

        $this->info("Regulacion [{$regulacion->id}] {$regulacion->nombre}");

        if ($regulacion->extension_original !== 'pdf'
            || empty($regulacion->archivo_original)
            || ! Storage::disk('local')->exists($regulacion->archivo_original)) {
            $this->error('No hay PDF original accesible.');
            return self::FAILURE;
        }

        $paginas = $conversor->extraerTextoPorPagina(
            Storage::disk('local')->path($regulacion->archivo_original)
        );
        if ($paginas === null) {
            $this->error('pdftotext no devolvio texto.');
            return self::FAILURE;
        }
        $total = count($paginas);
        $this->line("Paginas detectadas: {$total}");

        $plano = array_map(fn ($t) => strtolower(Str::ascii($t)), $paginas);
        $norm  = array_map(fn ($t) => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($t))), $paginas);

        $numeroArg = (string) $this->argument('numero');
        $nodo = RegulacionNodo::where('regulacion_id', $regulacion->id)
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->where('numero', $numeroArg)
            ->first();

        $this->newLine();
        if ($nodo === null) {
            $this->warn("No hay nodo articulo con numero EXACTO = '{$numeroArg}'.");
        } else {
            $this->info("Nodo [{$nodo->id}]  numero='{$nodo->numero}'  pagina_en_BD=" . ($nodo->pagina ?? 'NULL'));
            $this->line('texto (120): ' . Str::limit((string) $nodo->texto, 120));
        }

        $num       = trim(strtolower(Str::ascii($numeroArg)));
        $patronNum = preg_replace('/\s+/', '\\s+', preg_quote($num, '/'));
        $regex     = '/^[\s\d]*articulo\s+0*' . $patronNum . '(?![0-9])/m';

        $this->newLine();
        $this->info('[1] Encabezado a inicio de renglon');
        $enc = [];
        for ($p = 1; $p <= $total; $p++) {
            if (preg_match($regex, $plano[$p - 1]) === 1) {
                $enc[] = $p;
            }
        }
        $this->line('Casa en paginas: ' . ($enc === [] ? '(NINGUNA) <-- por esto falla el encabezado' : implode(', ', $enc)));

        $aguja = 'articulo' . $num;
        $any = [];
        for ($p = 1; $p <= $total; $p++) {
            if (str_contains($norm[$p - 1], $aguja)) {
                $any[] = $p;
            }
        }
        $this->info("[2] 'articulo{$num}' en cualquier parte");
        $this->line('Aparece en paginas: ' . ($any === [] ? '(ninguna)' : implode(', ', $any)));

        if ($nodo !== null) {
            $cuerpo = substr(preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii((string) $nodo->texto))), 0, 60);
            $body = [];
            for ($p = 1; $p <= $total; $p++) {
                if ($cuerpo !== '' && str_contains($norm[$p - 1], $cuerpo)) {
                    $body[] = $p;
                }
            }
            $this->info("[3] Cuerpo (60): '{$cuerpo}'");
            $this->line('Aparece en paginas: ' . ($body === [] ? '(ninguna)' : implode(', ', $body)));
        }

        $mostrar = array_slice(array_values(array_unique(array_merge($enc, $any))), 0, 3);
        foreach ($mostrar as $p) {
            $this->newLine();
            $this->line("== Pagina {$p} (texto CRUDO, primeras 14 lineas) ==");
            $lineas = preg_split('/\r?\n/', trim((string) $paginas[$p - 1]));
            foreach (array_slice($lineas, 0, 14) as $ln) {
                $this->line('  | ' . Str::limit(trim($ln), 90));
            }
        }

        $this->newLine();
        $this->info('Fin. Copia y pega TODO esto.');
        return self::SUCCESS;
    }
}
