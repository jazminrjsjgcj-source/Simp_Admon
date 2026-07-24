<?php

namespace App\Console\Commands;

use App\Services\BuscadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Prueba el asistente del buscador contra la API REAL, desde la terminal.
 *
 * ── Para qué sirve ───────────────────────────────────────────────────
 *
 * Las pruebas automáticas (AsistenteRespuestaTest) usan Http::fake(): simulan lo que DeepSeek
 * respondería. Comprueban que el CÓDIGO hace lo correcto —que no acepta respuestas sin citas,
 * que no revienta si la API se cae—, pero NO comprueban si las respuestas son BUENAS.
 *
 * Eso no se puede automatizar. Una respuesta puede ser técnicamente válida —bien citada, bien
 * formada— y aun así ser inútil, o confusa, o estar mal enfocada. Eso lo juzga una persona
 * leyéndola.
 *
 * Este comando existe para eso: para que puedas leer respuestas de verdad, con datos de verdad,
 * ANTES de enseñárselas a un ciudadano.
 *
 * ── Por qué desde la terminal y no desde la pantalla ─────────────────
 *
 * Porque la pantalla todavía no distingue una respuesta REDACTADA POR UNA MÁQUINA de una
 * definición legal curada por una persona. Mientras no lo haga, encender el asistente en
 * producción sería dejar que alguien lea un texto generado creyendo que es la palabra oficial
 * del Ayuntamiento.
 *
 * Aquí no hay ese riesgo: quien lee eres tú, y sabes lo que estás leyendo.
 *
 * ── Uso ──────────────────────────────────────────────────────────────
 *
 *     php artisan buscador:probar "¿cuánto cuesta la licencia de funcionamiento?"
 *     php artisan buscador:probar "qué es una regulación" --sin-cache
 *
 * El asistente tiene que estar encendido (ASISTENTE_ACTIVO=true) y con su clave puesta. Si no
 * lo está, el comando te lo dice en vez de fallar en silencio.
 */
class ProbarAsistente extends Command
{
    protected $signature = 'buscador:probar
                            {consulta   : Lo que escribiría un ciudadano en el buscador}
                            {--sin-cache : Ignora la caché y vuelve a preguntarle al modelo}';

    protected $description = 'Prueba el asistente del buscador contra la API real, sin tocar la interfaz.';

    public function handle(BuscadorService $buscador): int
    {
        $consulta = (string) $this->argument('consulta');

        // ── Comprobaciones previas ──
        //
        // Si el asistente está apagado o sin clave, este comando devolvería una búsqueda normal
        // sin respuesta destacada... y parecería que "el asistente no encontró nada", cuando lo
        // que pasa es que ni siquiera se llamó.
        //
        // Es el patrón que llevamos toda la sesión evitando: un fallo que se disfraza de
        // resultado. Mejor decirlo claro.
        if (! config('punta.asistente.activo')) {
            $this->error('El asistente está APAGADO (ASISTENTE_ACTIVO=false).');
            $this->line('');
            $this->line('  Enciéndelo solo para esta prueba:');
            $this->line('    docker compose exec app php artisan buscador:probar "..." --env=local');
            $this->line('');
            $this->line('  O pon ASISTENTE_ACTIVO=true en el .env y corre config:clear.');
            $this->line('  Acuérdate de volver a apagarlo si no vas a usarlo.');

            return self::FAILURE;
        }

        if (empty(config('punta.asistente.api_key'))) {
            $this->error('No hay DEEPSEEK_API_KEY en el .env.');
            $this->line('  Créala en platform.deepseek.com → API keys, y carga saldo en Top up.');

            return self::FAILURE;
        }

        if ($this->option('sin-cache')) {
            Cache::flush();
            $this->comment('Caché limpiada: se preguntará al modelo aunque ya se hubiera preguntado antes.');
        }

        // ── La búsqueda ──

        $this->line('');
        $this->line("<fg=cyan>PREGUNTA:</> {$consulta}");
        $this->line(str_repeat('─', 78));

        $inicio    = microtime(true);
        $resultado = $buscador->buscar($consulta);
        $segundos  = round(microtime(true) - $inicio, 2);

        $this->line("Modo de búsqueda: <fg=yellow>{$resultado['modo']}</>");
        $this->line('Resultados encontrados: ' . $resultado['resultados']->count());
        $this->line("Tardó: {$segundos}s");
        $this->line('');

        // ── Lo que el buscador encontró (las fuentes que se le pasaron al modelo) ──
        //
        // Esto es lo MÁS IMPORTANTE de todo el comando, y por eso se enseña ANTES de la
        // respuesta.
        //
        // La regla del asistente es que solo puede redactar lo que hay en estas fuentes. Si la
        // respuesta dice algo que NO está aquí, se lo inventó — y eso es un fallo grave que hay
        // que ver, no un detalle.
        //
        // Leer la respuesta sin ver las fuentes es leerla sin poder juzgarla.
        if ($resultado['resultados']->isEmpty()) {
            $this->warn('El buscador no encontró NADA.');
            $this->line('');
            $this->line('  El asistente ni siquiera se llamó, y es correcto: sin fuentes no puede');
            $this->line('  saber nada. Pedirle que responda igualmente sería pedirle que se lo invente.');
            $this->line('');

            // ── QUÉ PROPUSO EL REFORMULADOR ──
            //
            // Sin esto, un "cero resultados" tiene TRES causas posibles y todas se ven igual:
            //
            //   · El modelo propuso frases en vez de términos → se descartaron.
            //   · El modelo cambió de tema                    → se descartaron.
            //   · Se buscó con las propuestas y no había nada.
            //
            // Y no se puede arreglar lo que no se puede ver.
            $this->mostrarReformulaciones($consulta);

            return self::SUCCESS;
        }

        $this->line('<fg=cyan>FUENTES QUE SE LE PASARON AL MODELO:</>');
        $this->line('(el asistente SOLO puede usar esto. Si su respuesta dice algo que no está');
        $this->line(' aquí abajo, se lo inventó — y eso es un fallo, no una virtud)');
        $this->line('');

        $max = (int) config('punta.asistente.max_fuentes', 8);

        foreach ($resultado['resultados']->take($max) as $i => $r) {
            $n = $i + 1;

            // Las claves reales que devuelve BuscadorService son:
            //   tipo, icono, titulo, subtitulo, fragmento, score, url, meta
            //
            // La primera versión de este comando leía 'texto' y 'descripcion', que NO EXISTEN.
            // Por eso la lista de fuentes salía con los títulos y sin una sola línea de texto:
            //   [3] Artículo 1
            //   [4] Artículo 2
            //
            // Y ese era exactamente el problema que el comando tenía que ayudar a diagnosticar.
            // Una herramienta de diagnóstico con el mismo bug que el sistema que diagnostica no
            // sirve de nada: enseñaba la avería y la hacía parecer normal.
            $titulo    = $r['titulo']    ?? '(sin título)';
            $subtitulo = $r['subtitulo'] ?? '';
            $texto     = \Illuminate\Support\Str::limit(trim((string) ($r['fragmento'] ?? '')), 150);

            $this->line("  <fg=yellow>[{$n}]</> {$titulo}" . ($subtitulo ? " · {$subtitulo}" : ''));

            if ($texto !== '') {
                $this->line("      {$texto}");
            } else {
                $this->line('      <fg=red>(SIN TEXTO — esta fuente no le sirve de nada al modelo)</>');
            }
        }

        $this->line('');
        $this->line(str_repeat('─', 78));

        // ── La respuesta ──

        $destacada = $resultado['respuesta_destacada'];

        if ($destacada === null) {
            $this->warn('NO HAY RESPUESTA DESTACADA.');
            $this->line('');
            $this->line('  Puede ser por varios motivos, y todos son comportamientos correctos:');
            $this->line('');
            $this->line('   · El modelo dijo que las fuentes NO le bastaban para responder.');
            $this->line('     (Eso es lo que queremos: prefiere callar a inventar.)');
            $this->line('   · El modelo respondió sin citar ninguna fuente → se descartó.');
            $this->line('   · El modelo citó una fuente que no existe → se descartó ENTERA.');
            $this->line('   · La API falló o tardó más de ' . config('punta.asistente.timeout', 8) . 's.');
            $this->line('');
            $this->line('  Mira storage/logs/laravel.log para saber cuál de los cuatro fue.');

            return self::SUCCESS;
        }

        $confianza = $destacada['confianza'] ?? '?';

        $color = match ($confianza) {
            'alta'        => 'green',
            'media'       => 'yellow',
            'generada'    => 'magenta',
            'relacionada' => 'yellow',
            default       => 'white',
        };

        $this->line("<fg={$color}>RESPUESTA (confianza: {$confianza})</>");
        $this->line('');
        $this->line('  ' . wordwrap($destacada['definicion'] ?? '', 74, "\n  "));
        $this->line('');

        // ── OJO: 'relacionada' TAMBIÉN la redacta la IA ──
        //
        // La primera versión de este comando solo comprobaba `=== 'generada'`. Con una respuesta
        // de tipo 'relacionada' —que la IA también escribe— imprimía:
        //
        //     "Esto NO lo redactó la IA. Salió del diccionario curado."
        //
        // Justo lo contrario de lo que pasaba.
        //
        // Una herramienta de diagnóstico que miente sobre lo que diagnostica es peor que no
        // tenerla: te da confianza en una lectura falsa. Y estuvo mintiendo durante toda una
        // sesión de depuración.
        if (in_array($confianza, ['generada', 'relacionada'], true)) {
            $esParcial = $confianza === 'relacionada';

            $this->line('  <fg=magenta>↑ Esto lo REDACTÓ LA IA.</>'
                . ($esParcial ? ' Y es una respuesta A MEDIAS: no encontró lo que se preguntaba,' : ''));

            if ($esParcial) {
                $this->line('    pero cuenta qué SÍ dicen las fuentes sobre el tema.');
            }

            $this->line('    Compruébalo contra las fuentes de arriba: ¿dice algo que no esté ahí?');
            $this->line('    ¿Alguna cifra, algún plazo, algún requisito? Si es así, se lo inventó.');
        } else {
            $this->line('  <fg=green>↑ Esto NO lo redactó la IA.</> Salió del diccionario curado o de las');
            $this->line('    definiciones extraídas del articulado. El asistente ni se llamó, y es');
            $this->line('    correcto: una definición curada por una persona vale más.');
        }

        $this->line('');
        $this->line('  <fg=cyan>Fuente citada:</> ' . ($destacada['fuente'] ?? '—')
            . ($destacada['articulo'] ? ', artículo ' . $destacada['articulo'] : ''));

        if (! empty($destacada['definiciones_adicionales'])) {
            $this->line('  Otras fuentes usadas: ' . count($destacada['definiciones_adicionales']));
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Enseña qué palabras propuso la IA cuando el buscador no encontró nada.
     *
     * Es la única forma de distinguir "la IA no propuso nada útil" de "propuso bien y aun así no
     * hay nada en la ley". Son dos problemas distintos con dos arreglos distintos.
     */
    private function mostrarReformulaciones(string $consulta): void
    {
        $reformulador = app(\App\Services\ReformuladorConsultaService::class);

        $this->line('<fg=cyan>QUÉ PROPUSO LA IA PARA BUSCAR:</>');
        $this->line('');

        $alternativas = $reformulador->reformular($consulta);

        if ($alternativas === []) {
            $this->line('  <fg=red>NADA.</> La IA no propuso ninguna búsqueda alternativa válida.');
            $this->line('');
            $this->line('  Los motivos posibles, y todos quedan escritos en el log:');
            $this->line('');
            $this->line('   · Propuso FRASES en vez de términos de búsqueda (se descartan: buscar');
            $this->line('     una frase con AND da cero, igual que la pregunta original).');
            $this->line('   · CAMBIÓ DE TEMA (se descartan: una respuesta sobre otro impuesto,');
            $this->line('     perfectamente citada, es lo más peligroso que puede pasar aquí).');
            $this->line('   · La API falló, tardó más de 5s, o está apagada.');
            $this->line('');
            $this->line('  Mira storage/logs/laravel.log y busca "Reformulador".');

            return;
        }

        foreach ($alternativas as $i => $alt) {
            $this->line('  <fg=yellow>[' . ($i + 1) . ']</> ' . $alt);
        }

        $this->line('');
        $this->line('  Se buscó con estas y TAMPOCO se encontró nada.');
        $this->line('');
        $this->line('  Eso significa que las palabras que propuso la IA no están en ninguna');
        $this->line('  regulación cargada. O la ley no dice lo que se pregunta, o el vocabulario');
        $this->line('  que eligió sigue sin ser el de la ley.');
    }
}
