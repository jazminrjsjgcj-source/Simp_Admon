<?php

namespace App\Services;

use App\Models\Flujo\FlujoActividad;
use App\Models\Flujo\FlujoRuta;
use App\Models\Reingenieria;

/**
 * Dibuja el diagrama de un proceso a partir de su flujo detallado.
 *
 * Produce Mermaid, que es lo que la pantalla de digitalización ya renderiza. La
 * diferencia con el generador anterior es que aquel encadenaba los pasos en línea
 * recta: aunque un paso fuera una decisión, dibujaba el rombo con una sola salida.
 * Aquí las decisiones se bifurcan de verdad, los retornos se ven, y las fases
 * agrupan sus actividades.
 *
 * Convenciones del dibujo, para que un diagrama se lea igual que otro:
 *
 *   - Cada fase es un `subgraph`, y las actividades van dentro.
 *   - Una actividad normal es un rectángulo; una que revisa, un rombo.
 *   - Las actividades se numeran de forma corrida en todo el proceso (1, 2, 3...),
 *     no por fase: es lo que permite decir "el paso 37" en una reunión.
 *   - Los retornos no se dibujan como una flecha hacia atrás que cruza medio
 *     diagrama, sino con un nodo LOOP y una línea punteada. Una flecha larga hacia
 *     arriba vuelve el diagrama ilegible en cuanto hay más de dos.
 *   - Las notas cuelgan con línea punteada y no interrumpen la secuencia.
 *   - El color lo pone el TIPO de participante, nunca la persona que captura: así
 *     Tesorería sale del mismo color en todos los procesos del municipio.
 */
class GeneradorDiagramaFlujoService
{
    /** Actividades indexadas por id, con su número corrido y su identificador. */
    private array $indice = [];

    /** Identificadores agrupados por tipo de participante, para pintar al final. */
    private array $porTipo = [];

    /** Fases en orden, para poder resolver el salto a la fase siguiente. */
    private $fases;

    /** Identificadores de los nodos de nota, para pintarlos al final. */
    private array $notas = [];

    /** Nodos LOOP creados, para no repetirlos. */
    private array $loops = [];

    /**
     * Flechas ya dibujadas.
     *
     * Si alguien captura una ruta "continúa a la siguiente" y además una ruta
     * explícita hacia esa misma actividad, saldría la flecha por duplicado y Mermaid
     * dibuja una línea más gruesa sin decir por qué. Se ignora la repetida.
     */
    private array $flechas = [];

    private int $contadorLoop = 0;

    public function generar(Reingenieria $reingenieria): string
    {
        $this->indice  = [];
        $this->porTipo = [];
        $this->loops   = [];
        $this->notas   = [];
        $this->flechas = [];
        $this->contadorLoop = 0;

        $fases = $reingenieria->fases()->with(['actividades.participante', 'actividades.rutas'])->get();
        $this->fases = $fases;

        if ($fases->isEmpty()) {
            return "flowchart TB\n    VACIO([\"Este proceso todavía no tiene actividades\"])";
        }

        $this->indexar($fases);

        $lineas = ['flowchart TB', '', '    INICIO([INICIO])', ''];

        foreach ($fases as $n => $fase) {
            $lineas = array_merge($lineas, $this->dibujarFase($fase, $n + 1));
        }

        $lineas = array_merge($lineas, $this->dibujarConexiones($fases, $reingenieria));
        $lineas = array_merge($lineas, $this->dibujarColores());

        return implode("\n", $lineas);
    }

    /**
     * Numera las actividades de corrido y les asigna identificador.
     *
     * Se hace antes de dibujar nada porque una ruta puede apuntar a una actividad de
     * otra fase, incluso posterior: para escribir esa flecha hay que conocer ya su
     * identificador.
     */
    private function indexar($fases): void
    {
        $numero = 0;

        foreach ($fases as $fase) {
            foreach ($fase->actividades as $actividad) {
                $numero++;
                $this->indice[$actividad->id] = [
                    'numero' => $numero,
                    'id'     => ($actividad->tiene_decision ? 'D' : 'P') . $numero,
                    'fase'   => $fase->id,
                ];
            }
        }
    }

    /** @return array<int, string> */
    private function dibujarFase($fase, int $numeroFase): array
    {
        $lineas = [
            '    %% ' . str_repeat('=', 50),
            "    subgraph F{$numeroFase}[\"FASE {$numeroFase} — {$this->texto($fase->nombre)}\"]",
        ];

        // Nodos
        foreach ($fase->actividades as $actividad) {
            $lineas[] = '        ' . $this->nodoActividad($actividad);
            $this->registrarColor($actividad);

            if ($nota = $this->nodoNotaActividad($actividad)) {
                $lineas[] = '        ' . $nota;
            }
        }

        if ($fase->nota) {
            $this->notas[] = "NF{$fase->id}";
            $lineas[] = '        ' . $this->nodoNota("NF{$fase->id}", 'NOTA — ' . $fase->nombre, $fase->nota);
        }

        $lineas[] = '';

        // Flechas dentro de la fase
        foreach ($fase->actividades as $actividad) {
            $lineas = array_merge($lineas, $this->flechasDe($actividad, $fase));
        }

        $lineas[] = '    end';
        $lineas[] = '';

        return $lineas;
    }

    private function nodoActividad(FlujoActividad $actividad): string
    {
        $ref    = $this->indice[$actividad->id];
        $actor  = $actividad->participante?->nombre ?? 'Sin asignar';
        $cuerpo = $actividad->tiene_decision
            ? ($actividad->que_revisa ?: $actividad->descripcion)
            : $actividad->descripcion;

        $etiqueta = "{$ref['numero']}. {$this->texto($actor)}<br/>{$this->texto($cuerpo)}";

        // Rombo si revisa, rectángulo si solo ejecuta.
        return $actividad->tiene_decision
            ? "{$ref['id']}{\"{$etiqueta}\"}"
            : "{$ref['id']}[\"{$etiqueta}\"]";
    }

    private function nodoNotaActividad(FlujoActividad $actividad): ?string
    {
        $nota = $actividad->detalle['nota'] ?? null;

        if (empty($nota['texto'])) {
            return null;
        }

        $ref = $this->indice[$actividad->id];

        $this->notas[] = 'N' . $ref['numero'];

        return $this->nodoNota(
            'N' . $ref['numero'],
            $nota['titulo'] ?? 'Nota',
            $nota['texto']
        );
    }

    private function nodoNota(string $id, string $titulo, string $texto): string
    {
        return "{$id}([\"{$this->texto($titulo)}<br/>{$this->texto($texto)}\"])";
    }

    /**
     * Flechas que salen de una actividad.
     *
     * @return array<int, string>
     */
    private function flechasDe(FlujoActividad $actividad, $fase): array
    {
        $lineas = [];
        $origen = $this->indice[$actividad->id]['id'];
        $rutas  = $actividad->rutas;

        // Sin rutas capturadas se asume la secuencia natural: la siguiente actividad
        // de la fase. Es lo que espera quien captura un proceso lineal sin pensar en
        // rutas, y evita que el diagrama salga desconectado.
        if ($rutas->isEmpty()) {
            if ($siguiente = $this->siguienteEnFase($actividad, $fase)) {
                $this->flecha($lineas, $origen, $siguiente);
            }
            return $lineas;
        }

        foreach ($rutas as $ruta) {
            $etiqueta = match ($ruta->condicion) {
                FlujoRuta::CONDICION_CORRECTO   => '|Sí|',
                FlujoRuta::CONDICION_INCORRECTO => '|No|',
                default                         => '',
            };

            $destino = $this->destinoDe($ruta, $actividad, $fase);

            if ($destino === null) {
                continue;
            }

            // Los retornos van punteados y pasan por un nodo LOOP, para no cruzar el
            // diagrama con una flecha larga hacia atrás.
            if ($ruta->esRetorno() && $ruta->destino_tipo !== FlujoRuta::DESTINO_FIN) {
                [$idLoop, $lineaLoop] = $this->nodoLoop($ruta, $destino);
                $lineas[] = '        ' . $lineaLoop;
                $this->flecha($lineas, $origen, $idLoop, $etiqueta);
                $this->flecha($lineas, $idLoop, $destino, '', true);
                continue;
            }

            $this->flecha($lineas, $origen, $destino, $etiqueta);
        }

        // La nota cuelga aparte, sin interrumpir la secuencia.
        if (! empty($actividad->detalle['nota']['texto'])) {
            $this->flecha($lineas, $origen, 'N' . $this->indice[$actividad->id]['numero'], '', true);
        }

        return $lineas;
    }

    /**
     * Emite una flecha, si no se había emitido ya.
     *
     * @param  array<int, string>  $lineas  se modifica en sitio
     */
    private function flecha(array &$lineas, string $origen, string $destino, string $etiqueta = '', bool $punteada = false): void
    {
        $clave = $origen . $etiqueta . ($punteada ? '-.->' : '-->') . $destino;

        if (isset($this->flechas[$clave])) {
            return;
        }

        $this->flechas[$clave] = true;
        $trazo = $punteada ? '-.->' : '-->';

        $lineas[] = "        {$origen} {$trazo}{$etiqueta} {$destino}";
    }

    /** Identificador del nodo al que apunta una ruta, o null si no se puede resolver. */
    private function destinoDe(FlujoRuta $ruta, FlujoActividad $actividad, $fase): ?string
    {
        return match ($ruta->destino_tipo) {
            FlujoRuta::DESTINO_SIGUIENTE => $this->siguienteEnFase($actividad, $fase),

            FlujoRuta::DESTINO_ACTIVIDAD => $ruta->destino_actividad_id
                ? ($this->indice[$ruta->destino_actividad_id]['id'] ?? null)
                : null,

            FlujoRuta::DESTINO_INICIO_FASE    => $this->primeraDeLaFase($fase),
            FlujoRuta::DESTINO_INICIO_PROCESO => 'INICIO',
            FlujoRuta::DESTINO_FIN            => 'FIN' . ($ruta->resultado_id ?? 0),

            default => null,
        };
    }

    /**
     * La actividad que viene después.
     *
     * Si la actividad cierra su fase, "la siguiente" es la primera de la fase
     * siguiente, no nada. Resolverlo dentro de la fase dejaba sin flecha la salida de
     * toda decisión que cerrara una etapa —el camino "Sí" desaparecía— y el diagrama
     * salía partido en trozos sin avisar.
     */
    private function siguienteEnFase(FlujoActividad $actividad, $fase): ?string
    {
        $siguiente = $fase->actividades
            ->where('orden', '>', $actividad->orden)
            ->sortBy('orden')
            ->first();

        if ($siguiente) {
            return $this->indice[$siguiente->id]['id'] ?? null;
        }

        return $this->primeraDeLaFaseSiguiente($fase);
    }

    /** Primera actividad de la fase que va después de esta, si la hay. */
    private function primeraDeLaFaseSiguiente($fase): ?string
    {
        $posicion = $this->fases->search(fn ($f) => $f->id === $fase->id);

        if ($posicion === false) {
            return null;
        }

        $siguienteFase = $this->fases[$posicion + 1] ?? null;

        return $siguienteFase ? $this->primeraDeLaFase($siguienteFase) : null;
    }

    private function primeraDeLaFase($fase): ?string
    {
        $primera = $fase->actividades->sortBy('orden')->first();

        return $primera ? ($this->indice[$primera->id]['id'] ?? null) : null;
    }

    /** @return array{0: string, 1: string} identificador y declaración del nodo LOOP */
    private function nodoLoop(FlujoRuta $ruta, string $destino): array
    {
        $clave = $ruta->destino_tipo . ':' . $destino;

        if (! isset($this->loops[$clave])) {
            $this->contadorLoop++;
            $id = 'L' . $this->contadorLoop;
            $this->loops[$clave] = [
                $id,
                "{$id}([\"LOOP {$this->contadorLoop}<br/>{$this->texto($ruta->destinoLabel())}\"])",
            ];
        }

        return $this->loops[$clave];
    }

    /**
     * Conexiones entre fases y nodos de fin.
     *
     * @return array<int, string>
     */
    private function dibujarConexiones($fases, Reingenieria $reingenieria): array
    {
        $lineas = ['    %% ' . str_repeat('=', 50)];

        // Arranque
        if ($primera = $this->primeraDeLaFase($fases->first())) {
            $lineas[] = "    INICIO --> {$primera}";
        }

        // Encadenar cada fase con la siguiente, salvo que la última actividad ya
        // tenga rutas propias: en ese caso ella decide a dónde va.
        foreach ($fases as $i => $fase) {
            $siguienteFase = $fases[$i + 1] ?? null;
            if (! $siguienteFase) {
                continue;
            }

            $ultima = $fase->actividades->sortByDesc('orden')->first();
            if (! $ultima || $ultima->rutas->isNotEmpty()) {
                continue;
            }

            $destino = $this->primeraDeLaFase($siguienteFase);
            if ($destino && isset($this->indice[$ultima->id])) {
                $lineas[] = "    {$this->indice[$ultima->id]['id']} --> {$destino}";
            }
        }

        // Un nodo por cada final posible que alguien use.
        foreach ($reingenieria->resultados as $resultado) {
            $lineas[] = "    FIN{$resultado->id}([\"FIN — {$this->texto($resultado->nombre)}\"])";
        }

        $lineas[] = '';

        return $lineas;
    }

    /**
     * Estilos. El color sale del tipo de participante declarado en config, así que
     * el mismo área se ve igual en todos los procesos sin que nadie elija colores.
     *
     * @return array<int, string>
     */
    private function dibujarColores(): array
    {
        $lineas = ['    %% ' . str_repeat('=', 50)];

        foreach (config('punta.flujo.tipos_participante', []) as $tipo => $datos) {
            $color = $datos['color'] ?? '#475569';
            $lineas[] = "    classDef {$tipo} fill:{$color}22,stroke:{$color},stroke-width:2px;";
        }

        $lineas[] = '    classDef nota fill:#FFF8DE,stroke:#B38B00,stroke-width:2px,stroke-dasharray:6 4;';
        $lineas[] = '    classDef loop fill:#FFE3E3,stroke:#C62828,stroke-width:3px;';
        $lineas[] = '    classDef fin  fill:#ECEFF1,stroke:#455A64,stroke-width:3px;';
        $lineas[] = '';

        foreach ($this->porTipo as $tipo => $ids) {
            $lineas[] = '    class ' . implode(',', $ids) . ' ' . $tipo . ';';
        }

        if ($this->loops) {
            $lineas[] = '    class ' . implode(',', array_column($this->loops, 0)) . ' loop;';
        }

        if ($this->notas) {
            $lineas[] = '    class ' . implode(',', $this->notas) . ' nota;';
        }

        return $lineas;
    }

    private function registrarColor(FlujoActividad $actividad): void
    {
        $tipo = $actividad->participante?->tipo ?? 'otra';
        $this->porTipo[$tipo][] = $this->indice[$actividad->id]['id'];
    }

    /**
     * Deja el texto en algo que Mermaid pueda meter dentro de una etiqueta.
     *
     * Las comillas dobles cierran la etiqueta antes de tiempo y rompen el diagrama
     * entero, así que se cambian por simples. Los saltos de línea se convierten en
     * <br/>, que es como Mermaid parte una etiqueta.
     */
    private function texto(?string $valor): string
    {
        $limpio = str_replace('"', "'", trim((string) $valor));

        return preg_replace('/\s*\n\s*/', '<br/>', $limpio);
    }
}
