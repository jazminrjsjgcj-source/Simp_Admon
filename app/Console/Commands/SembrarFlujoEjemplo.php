<?php

namespace App\Console\Commands;

use App\Models\Flujo\FlujoActividad;
use App\Models\Flujo\FlujoFase;
use App\Models\Flujo\FlujoParticipante;
use App\Models\Flujo\FlujoResultado;
use App\Models\Flujo\FlujoRuta;
use App\Models\Reingenieria;
use App\Models\Tramite;
use App\Services\GeneradorDiagramaFlujoService;
use App\Support\ProcesosEjemplo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Siembra uno de los procesos de ejemplo y dibuja su diagrama.
 *
 * Los procesos viven en App\Support\ProcesosEjemplo y son los del propio sistema
 * —digitalización, alta y baja de usuarios, AIR— más un trámite ciudadano. Sirven
 * para probar la captura y el diagrama con procesos reales, que es donde se ve si
 * el modelo aguanta: decisiones que devuelven, puertas con varias condiciones,
 * ramas en paralelo y varios finales distintos.
 *
 * Uso:
 *   php artisan flujo:sembrar-ejemplo --listar
 *   php artisan flujo:sembrar-ejemplo --proceso=air --crear
 *   php artisan flujo:sembrar-ejemplo --todos --crear
 */
class SembrarFlujoEjemplo extends Command
{
    protected $signature = 'flujo:sembrar-ejemplo
                            {reingenieria? : ID de la reingeniería. Sin esto, la más reciente.}
                            {--proceso=permiso : Cuál sembrar. Ver --listar.}
                            {--todos : Siembra cada proceso en su propia reingeniería.}
                            {--listar : Muestra los procesos disponibles y termina.}
                            {--limpiar : Borra el flujo que ya tuviera antes de sembrar.}
                            {--crear : Crea la reingeniería si hace falta.}
                            {--sin-diagrama : No imprime el diagrama al terminar.}';

    protected $description = 'Siembra un proceso de ejemplo (digitalización, alta o baja de usuario, AIR, trámite) y muestra su diagrama.';

    public function handle(GeneradorDiagramaFlujoService $generador): int
    {
        if ($this->option('listar')) {
            $this->info('Procesos disponibles:');
            foreach (ProcesosEjemplo::disponibles() as $clave => $nombre) {
                $this->line("  {$clave}" . str_repeat(' ', max(1, 18 - strlen($clave))) . $nombre);
            }
            return self::SUCCESS;
        }

        $claves = $this->option('todos')
            ? array_keys(ProcesosEjemplo::disponibles())
            : [$this->option('proceso')];

        foreach ($claves as $clave) {
            if (! $this->sembrarUno($clave, $generador)) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function sembrarUno(string $clave, GeneradorDiagramaFlujoService $generador): bool
    {
        $proceso = ProcesosEjemplo::obtener($clave);

        if (! $proceso) {
            $this->error("No existe el proceso '{$clave}'. Usa --listar para verlos.");
            return false;
        }

        // Con --todos cada proceso necesita su propia reingeniería: si compartieran
        // una, el segundo borraría el flujo del primero.
        $reingenieria = $this->option('todos')
            ? $this->crearDePrueba($proceso['nombre'])
            : $this->resolverReingenieria();

        if (! $reingenieria) {
            return false;
        }

        $this->newLine();
        $this->info("[{$reingenieria->id}] {$proceso['nombre']}");

        if ($this->option('limpiar') || $this->option('todos')) {
            $reingenieria->fases()->delete();
            $reingenieria->participantes()->delete();
            $reingenieria->resultados()->delete();
        }

        if ($reingenieria->tieneFlujoDetallado()) {
            $this->warn('  Ya tiene flujo. Usa --limpiar para reemplazarlo.');
            return true;
        }

        DB::transaction(fn () => $this->construir($reingenieria, $proceso));

        $fases = $reingenieria->fases()->count();
        $acts  = $reingenieria->actividades()->count();
        $this->line("  {$fases} fases, {$acts} actividades.");

        if (! $this->option('sin-diagrama')) {
            $this->newLine();
            $this->line($generador->generar($reingenieria->fresh()));
        }

        return true;
    }

    private function resolverReingenieria(): ?Reingenieria
    {
        $id = $this->argument('reingenieria');

        $reingenieria = $id
            ? Reingenieria::find($id)
            : Reingenieria::latest('id')->first();

        if (! $reingenieria && $this->option('crear')) {
            $reingenieria = $this->crearDePrueba();
        }

        if (! $reingenieria) {
            $this->error('No hay ninguna reingeniería. Repite con --crear para generar una de prueba.');
        }

        return $reingenieria;
    }

    /**
     * Crea una reingeniería mínima para poder probar el modelo.
     *
     * Se salta la puerta habitual —flujo aprobado, reingeniería firmada— porque esto
     * no digitaliza nada: solo necesita un contenedor al que colgarle el flujo. Por
     * eso vive en un comando de consola y no en el controlador.
     */
    private function crearDePrueba(?string $nombre = null): ?Reingenieria
    {
        $tramite = Tramite::orderBy('id')->first();

        if (! $tramite) {
            $this->error('No hay trámites: no hay nada a lo que colgar el flujo.');
            return null;
        }

        return Reingenieria::create([
            'tramite_id'    => $tramite->id,
            'origen'        => 'directa',
            'estado'        => 'en_reingenieria',
            'justificacion' => 'Proceso de ejemplo: ' . ($nombre ?? 'prueba de flujo') . '.',
        ]);
    }

    /**
     * Construye el flujo en la base a partir de la descripción del catálogo.
     *
     * Las rutas se crean en una segunda pasada porque una puede apuntar a una
     * actividad de otra fase, incluso posterior, que hasta ese momento no existía.
     */
    private function construir(Reingenieria $reingenieria, array $proceso): void
    {
        $reingenieria->update([
            'proceso_nombre'    => $proceso['nombre'],
            'resolutivo_tipo'   => $proceso['resolutivo']['tipo']   ?? null,
            'resolutivo_nombre' => $proceso['resolutivo']['nombre'] ?? null,
            'inicia_con'        => $proceso['inicia'],
            'termina_con'       => $proceso['termina'],
        ]);

        $participantes = [];
        foreach ($proceso['participantes'] as $i => $p) {
            $participantes[$p['clave']] = FlujoParticipante::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $p['nombre'],
                'tipo'            => $p['tipo'],
                'orden'           => $i + 1,
            ])->id;
        }

        $resultados = [];
        foreach ($proceso['resultados'] as $i => $r) {
            $resultados[$r['clave']] = FlujoResultado::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $r['nombre'],
                'orden'           => $i + 1,
            ])->id;
        }

        $actividades = [];
        $porFase     = [];

        foreach ($proceso['fases'] as $i => $f) {
            $fase = FlujoFase::create([
                'reingenieria_id' => $reingenieria->id,
                'nombre'          => $f['nombre'],
                'nota'            => $f['nota'] ?? null,
                'orden'           => $i + 1,
            ]);

            foreach ($f['actividades'] as $j => $a) {
                $detalle = [];

                if (! empty($a['pago'])) {
                    $detalle['pago'] = ['acciones' => $a['pago'], 'derecho_id' => null, 'participante_id' => null];
                }
                if (! empty($a['nota'])) {
                    $detalle['nota'] = $a['nota'] + ['aplica' => 'actividad'];
                }
                if (! empty($a['estado'])) {
                    $detalle['estado'] = $a['estado'];
                }

                $actividades[$a['clave']] = FlujoActividad::create([
                    'fase_id'         => $fase->id,
                    'participante_id' => $participantes[$a['quien']] ?? null,
                    'descripcion'     => $a['hace'],
                    'tiene_decision'  => ! empty($a['revisa']),
                    'que_revisa'      => $a['revisa'] ?? null,
                    'detalle'         => $detalle ?: null,
                    'orden'           => $j + 1,
                ])->id;

                $porFase[$a['clave']] = $a;
            }
        }

        foreach ($porFase as $clave => $a) {
            foreach ($a['rutas'] ?? [] as $r) {
                $destino = $r['a'];

                [$tipo, $actividad, $resultado] = match (true) {
                    $destino === 'siguiente'      => [FlujoRuta::DESTINO_SIGUIENTE, null, null],
                    $destino === 'inicio_fase'    => [FlujoRuta::DESTINO_INICIO_FASE, null, null],
                    $destino === 'inicio_proceso' => [FlujoRuta::DESTINO_INICIO_PROCESO, null, null],
                    str_starts_with($destino, 'fin:') => [
                        FlujoRuta::DESTINO_FIN, null, $resultados[substr($destino, 4)] ?? null,
                    ],
                    default => [FlujoRuta::DESTINO_ACTIVIDAD, $actividades[$destino] ?? null, null],
                };

                FlujoRuta::create([
                    'actividad_id'         => $actividades[$clave],
                    'condicion'            => $r['cond'],
                    'destino_tipo'         => $tipo,
                    'destino_actividad_id' => $actividad,
                    'resultado_id'         => $resultado,
                ]);
            }
        }
    }
}
