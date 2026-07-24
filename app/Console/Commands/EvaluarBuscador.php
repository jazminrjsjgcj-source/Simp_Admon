<?php

namespace App\Console\Commands;

use App\Services\BuscadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Evalúa el buscador contra un banco de preguntas reales.
 *
 * ══════════════════════════════════════════════════════════════════════
 * PARA QUÉ EXISTE ESTE COMANDO
 * ══════════════════════════════════════════════════════════════════════
 *
 * Para convertir "parece que mejoró" en UN NÚMERO.
 *
 * ── Lo que pasó sin él ──
 *
 * En una sola sesión, este buscador pasó por OCHO rediseños:
 *
 *     1. Quitar las palabras vacías del full-text
 *     2. Quitar los verbos de acción (el ciudadano dice "calculan", la ley dice "pagarán")
 *     3. Normalizar el rank por longitud
 *     4. Subir el límite del articulado de 10 a 30
 *     5. Filtrar los rótulos de sección
 *     6. Heredar el contexto del capítulo
 *     7. Poner un suelo al umbral de palabras comunes
 *     8. Arreglar los acentos (spanish_unaccent)
 *
 * Y CADA UNO ARREGLABA UNA PREGUNTA Y ROMPÍA OTRA SIN QUE NADIE SE ENTERARA.
 *
 * Lo descubríamos por casualidad, probando a mano, horas después. Un cambio que hacía funcionar
 * "espectáculos" rompía "ambulantes", y no lo sabíamos hasta que alguien lo probaba.
 *
 * ── Lo que pasa con él ──
 *
 *     php artisan buscador:evaluar
 *     → 6 de 8 casos pasan.
 *
 * Haces un cambio. Lo vuelves a correr. Si baja, lo has roto. Si sube, ha mejorado.
 *
 * Y sabes CUÁL se rompió.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LO QUE MÁS IMPORTA: EL "no_debe_contener"
 * ══════════════════════════════════════════════════════════════════════
 *
 * Cada caso dice no solo qué DEBE encontrar, sino qué NO PUEDE devolver.
 *
 * Y esa es la mitad que nadie escribe.
 *
 * Cuando el buscador devolvía "sanciones por desacato al Bando de Policía" para una pregunta
 * sobre el COSTO de un permiso, ENCONTRABA RESULTADOS. Una evaluación que solo preguntara
 * "¿encontró algo?" habría dicho que iba bien.
 *
 *     UN RESULTADO EQUIVOCADO ES PEOR QUE NINGUNO.
 *
 * El primero se cierra con un "no hay nada". El segundo hace perder el tiempo y da una falsa
 * sensación de haber buscado.
 *
 * ══════════════════════════════════════════════════════════════════════
 * CÓMO SE USA
 * ══════════════════════════════════════════════════════════════════════
 *
 *     php artisan buscador:evaluar
 *     php artisan buscador:evaluar --detalle    (enseña los resultados de cada caso)
 *
 * El banco está en database/banco_preguntas.json.
 *
 * ── Y cómo se amplía ──
 *
 * Cada vez que alguien reporte una búsqueda que no funciona, se añade AL BANCO ANTES DE
 * ARREGLARLA.
 *
 * Así el arreglo se puede medir, y nadie puede romperlo después sin enterarse.
 */
class EvaluarBuscador extends Command
{
    protected $signature = 'buscador:evaluar
                            {--detalle : Enseña los resultados que devolvió cada caso}
                            {--caso=   : Evalúa un solo caso, por su id}';

    protected $description = 'Evalúa el buscador contra el banco de preguntas reales.';

    public function handle(BuscadorService $buscador): int
    {
        $ruta = database_path('banco_preguntas.json');

        if (! file_exists($ruta)) {
            $this->error("No encuentro el banco de preguntas en {$ruta}");

            return self::FAILURE;
        }

        $banco = json_decode(file_get_contents($ruta), true);

        if (! is_array($banco) || empty($banco['casos'])) {
            $this->error('El banco de preguntas está vacío o mal formado.');

            return self::FAILURE;
        }

        $casos = collect($banco['casos']);

        if ($id = $this->option('caso')) {
            $casos = $casos->where('id', $id);

            if ($casos->isEmpty()) {
                $this->error("No existe ningún caso con el id «{$id}».");

                return self::FAILURE;
            }
        }

        // Sin caché: se evalúa el buscador de verdad, no lo que respondió la última vez.
        Cache::flush();

        $this->line('');
        $this->line('══════════════════════════════════════════════════════════════════════');
        $this->line('  EVALUACIÓN DEL BUSCADOR');
        $this->line('══════════════════════════════════════════════════════════════════════');
        $this->line('');

        $pasan   = 0;
        $fallan  = [];
        $limites = [];

        foreach ($casos as $caso) {
            $resultado = $this->evaluarCaso($buscador, $caso);

            $esLimite = ! empty($caso['limite_conocido']);

            if ($resultado['pasa']) {
                if ($esLimite) {
                    // Un límite conocido que AHORA PASA es una BUENA noticia, y hay que decirlo.
                    $this->line("  <fg=green>✓</> <fg=yellow>{$caso['id']}</> (era un límite conocido, ¡y ahora pasa!)");
                    $this->line("      Quita 'limite_conocido' del banco: alguien lo resolvió.");
                } else {
                    $this->line("  <fg=green>✓</> {$caso['id']}");
                }

                $pasan++;
            } elseif ($esLimite) {
                $this->line("  <fg=yellow>−</> {$caso['id']} <fg=gray>(límite conocido, no cuenta)</>");
                $limites[] = $caso;
            } else {
                $this->line("  <fg=red>✗</> {$caso['id']}");
                $this->line("      <fg=red>{$resultado['motivo']}</>");
                $fallan[] = ['caso' => $caso, 'motivo' => $resultado['motivo']];
            }

            if ($this->option('detalle')) {
                $this->mostrarDetalle($caso, $resultado);
            }
        }

        // ── El número ──

        $evaluables = $casos->count() - count($limites);

        $this->line('');
        $this->line('══════════════════════════════════════════════════════════════════════');
        $this->line("  <fg=cyan>{$pasan} de {$evaluables} casos pasan.</>");

        if ($limites !== []) {
            $this->line('  ' . count($limites) . ' límite(s) conocido(s), documentados y fuera de la cuenta.');
        }

        $this->line('══════════════════════════════════════════════════════════════════════');
        $this->line('');

        if ($fallan === []) {
            $this->line('  Todos los casos evaluables pasan.');
            $this->line('');
            $this->line('  Antes de añadir cualquier capa nueva al buscador (embeddings, reranking,');
            $this->line('  tesauro...), AÑADE PRIMERO EL CASO QUE VIENE A RESOLVER. Si no, no habrá');
            $this->line('  forma de saber si mejoró o solo lo parece.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line('  <fg=red>CASOS QUE FALLAN:</>');
        $this->line('');

        foreach ($fallan as $f) {
            $this->line("  <fg=red>·</> <fg=yellow>{$f['caso']['id']}</>: \"{$f['caso']['pregunta']}\"");
            $this->line("      {$f['motivo']}");

            if (! empty($f['caso']['por_que'])) {
                $this->line('      <fg=gray>' . wordwrap($f['caso']['por_que'], 68, "\n      ") . '</>');
            }

            $this->line('');
        }

        return self::FAILURE;
    }

    /**
     * Evalúa un caso. Y comprueba las DOS cosas: lo que debe encontrar y lo que no.
     */
    private function evaluarCaso(BuscadorService $buscador, array $caso): array
    {
        $resultado = $buscador->buscar($caso['pregunta']);

        // ══════════════════════════════════════════════════════════════════════
        // DÓNDE SE COMPRUEBA: en los RESULTADOS o en la RESPUESTA
        // ══════════════════════════════════════════════════════════════════════
        //
        // Por defecto, en los resultados del buscador. Pero algunos casos se miden sobre lo que
        // el asistente RESPONDE. Y hay una razón de peso.
        //
        // ── El caso que lo destapó ──
        //
        // "¿Cuáles son los impuestos aplicables al patrimonio?" El asistente responde
        // PERFECTAMENTE:
        //
        //     "Los impuestos sobre el patrimonio incluyen el Impuesto Predial y el Impuesto
        //      sobre Adquisición de Bienes Inmuebles."
        //
        // Y el caso FALLABA. ¿Por qué? Porque entre los 30 resultados del buscador salía el
        // artículo 148 (fideicomisos), que usa la palabra "patrimonio" en otro sentido. Mi
        // "no_debe_contener" lo detectaba y suspendía el caso.
        //
        // ── Y estaba midiendo el eslabón equivocado ──
        //
        // El buscador trae MUCHOS resultados A PROPÓSITO. Ese es el diseño: no tiene que acertar,
        // solo NO PERDERSE la respuesta. Filtrar es trabajo del asistente, que sabe LEER.
        //
        // Y el asistente lo hizo bien: descartó el fideicomiso y citó los artículos correctos.
        //
        // Suspender el caso por el ruido de la lista es como suspender a un buscador porque su
        // segunda página tiene resultados peores que la primera. LO QUE LE LLEGA AL CIUDADANO ES
        // LA RESPUESTA.
        //
        // ── Cuándo usar cada uno ──
        //
        //   'resultados'  → cuando lo que se prueba es que el BUSCADOR encuentre el artículo.
        //                   (Es lo normal: si el artículo no llega, la IA no puede elegirlo.)
        //
        //   'respuesta'   → cuando lo que se prueba es que el ASISTENTE responda bien pese al
        //                   ruido. Son los casos donde la respuesta sale de la ESTRUCTURA y no
        //                   de un artículo concreto.
        $dondeComprobar = $caso['comprobar_en'] ?? 'resultados';

        $respuesta = $resultado['respuesta_destacada']['definicion'] ?? '';

        if ($dondeComprobar === 'respuesta') {
            if ($respuesta === '') {
                return [
                    'pasa'       => false,
                    'motivo'     => 'El asistente no respondió nada. Resultados: '
                                  . $resultado['resultados']->count(),
                    'resultados' => $resultado['resultados'],
                    'destacada'  => $resultado['respuesta_destacada'] ?? null,
                ];
            }

            $textos = $respuesta;
        } else {
            // Se mira el fragmento de los resultados Y la respuesta: el dato puede estar en
            // cualquiera de los dos, y lo que importa es que el ciudadano lo vea.
            $textos = $resultado['resultados']->pluck('fragmento')->implode(' ') . ' ' . $respuesta;
        }

        // ── 1. ¿Encontró lo que tenía que encontrar? ──
        $debe = $caso['debe_contener'] ?? null;

        if ($debe !== null && ! Str_contains_ci($textos, $debe)) {
            return [
                'pasa'       => false,
                'motivo'     => "No encontró «{$debe}». Resultados: " . $resultado['resultados']->count(),
                'resultados' => $resultado['resultados'],
                'destacada'  => $resultado['respuesta_destacada'] ?? null,
            ];
        }

        // ── 2. ¿Devolvió basura que NO debía? ──
        //
        // Esta es la mitad que de verdad protege. Un buscador que devuelve el artículo correcto
        // ENTRE OTROS DIEZ IRRELEVANTES no está funcionando: está inundando al ciudadano.
        foreach (($caso['no_debe_contener'] ?? []) as $prohibido) {
            if (Str_contains_ci($textos, $prohibido)) {
                return [
                    'pasa'       => false,
                    'motivo'     => "Devolvió basura: «{$prohibido}». Un resultado equivocado es peor que ninguno.",
                    'resultados' => $resultado['resultados'],
                    'destacada'  => $resultado['respuesta_destacada'] ?? null,
                ];
            }
        }

        return [
            'pasa'       => true,
            'motivo'     => '',
            'resultados' => $resultado['resultados'],
            'destacada'  => $resultado['respuesta_destacada'] ?? null,
        ];
    }

    private function mostrarDetalle(array $caso, array $resultado): void
    {
        $this->line("      <fg=gray>\"{$caso['pregunta']}\"</>");

        foreach ($resultado['resultados']->take(5) as $r) {
            $titulo = $r['titulo'] ?? '?';
            $texto  = \Illuminate\Support\Str::limit(trim((string) ($r['fragmento'] ?? '')), 70);
            $this->line("        <fg=gray>· {$titulo}: {$texto}</>");
        }

        if (! empty($resultado['destacada']['definicion'])) {
            $this->line('        <fg=magenta>RESPUESTA: '
                . \Illuminate\Support\Str::limit($resultado['destacada']['definicion'], 90) . '</>');
        }

        $this->line('');
    }
}

/**
 * Comparación sin distinguir mayúsculas ni acentos.
 *
 * Hace falta porque el banco puede decir "2 al millar" y el texto "2 AL MILLAR", o el banco
 * "habitacion" y el texto "habitación".
 *
 * Comparar sin normalizar haría fallar casos que en realidad pasan — y una evaluación que da
 * falsos negativos es peor que no tenerla: te manda a arreglar algo que no está roto.
 */
function Str_contains_ci(string $texto, string $buscado): bool
{
    $limpiar = fn (string $s) => mb_strtolower(
        \Illuminate\Support\Str::ascii($s)
    );

    return str_contains($limpiar($texto), $limpiar($buscado));
}
