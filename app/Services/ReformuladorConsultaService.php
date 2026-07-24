<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Traduce la pregunta del ciudadano al vocabulario de la ley, ANTES de buscar.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL PROBLEMA QUE RESUELVE (y por qué siete parches no bastaron)
 * ══════════════════════════════════════════════════════════════════════
 *
 * Un ciudadano pregunta:
 *
 *     "¿Qué tasa de impuesto predial corresponde a una casa habitación?"
 *
 * Y el buscador devuelve CERO resultados.
 *
 * Mientras tanto, el artículo 31 fracción I de la Ley de Hacienda dice EXACTAMENTE eso:
 *
 *     "A razón de 2 al millar anual sobre el valor catastral de los predios destinados
 *      totalmente por el contribuyente para su propia casa habitación."
 *
 * ── Por qué no lo encuentra ──
 *
 * El buscador usa AND: exige que TODAS las palabras estén en el mismo nodo.
 *
 *     tasa & impuesto & predial & casa & habitacion
 *
 * Y esa fracción NO DICE "tasa". NO DICE "impuesto". NO DICE "predial" (dice "predios", que el
 * stemmer separa).
 *
 * Solo dice "casa habitación". Dos palabras de cinco.
 *
 * ── Lo absurdo ──
 *
 *     CUANTAS MÁS PALABRAS ACIERTA EL CIUDADANO, MENOS ENCUENTRA.
 *
 * Escribió "tasa", "impuesto", "predial", "casa habitación" — TODAS correctas. Y el AND las
 * exige juntas en un mismo nodo.
 *
 * Ningún nodo de una ley contiene cinco términos del tema. Están REPARTIDOS POR LA ESTRUCTURA:
 * el impuesto en el título del capítulo, el predial en el nombre de la sección, la tasa en el
 * artículo, la casa habitación en la fracción.
 *
 * ── Por qué los parches no bastan ──
 *
 * Llevamos siete: quitar verbos, quitar palabras comunes, normalizar por longitud, subir el
 * límite, filtrar rótulos, heredar contexto, poner un suelo al umbral.
 *
 * Cada uno arregló un caso y destapó el siguiente. Porque ninguno ataca la causa:
 *
 *     EL BUSCADOR EXIGE COINCIDENCIA EXACTA DE PALABRAS, Y EL LENGUAJE NO FUNCIONA ASÍ.
 *
 * ══════════════════════════════════════════════════════════════════════
 * LA REGLA, QUE NO CAMBIA
 * ══════════════════════════════════════════════════════════════════════
 *
 *     LA IA NO BUSCA. PROPONE PALABRAS. EL BUSCADOR BUSCA.
 *
 * Este servicio NO consulta la base, NO devuelve artículos, NO responde nada. Coge una pregunta
 * y devuelve DOS O TRES CONSULTAS ALTERNATIVAS, escritas con el vocabulario que usaría una ley.
 *
 * Luego BuscadorService busca con cada una, contra la base de datos, como siempre. Si la IA
 * propone términos que no existen en ninguna regulación, no se encuentra nada — y no pasa nada.
 *
 * La IA sigue sin poder inventar un dato. Solo puede sugerir por dónde mirar.
 *
 * ══════════════════════════════════════════════════════════════════════
 * EL RIESGO, Y CÓMO SE ACOTA
 * ══════════════════════════════════════════════════════════════════════
 *
 * Hay un fallo posible y es serio: que la IA traduzca a un concepto QUE SÍ EXISTE PERO ES OTRO.
 *
 *     "cuánto pago por la basura"  →  la IA traduce a "impuesto predial"
 *                                  →  el buscador encuentra artículos del predial
 *                                  →  el asistente redacta una respuesta PERFECTAMENTE CITADA
 *                                     y PERFECTAMENTE FALSA
 *
 * Los tres candados del asistente NO lo detectarían: la cita existe, el dato está en el texto,
 * el artículo es real. Todo cuadra. Y la respuesta es sobre otra cosa.
 *
 * ── Las tres defensas ──
 *
 * 1. LA CONSULTA ORIGINAL SIEMPRE SE BUSCA. Las reformulaciones se AÑADEN, nunca sustituyen. Si
 *    la búsqueda original encuentra algo, eso va primero.
 *
 * 2. SE CONSERVA UN ANCLA. Cada reformulación debe conservar al menos un sustantivo de la
 *    pregunta original. "Basura" no puede convertirse en "predial" sin dejar rastro.
 *
 * 3. EL ASISTENTE SIGUE LEYENDO. Recibe los resultados de todas las consultas y ELIGE los que
 *    responden. Si le llegan artículos del predial para una pregunta sobre basura, los descarta
 *    — porque entiende el contenido.
 *
 * La tercera es la más fuerte, y es la que ya funciona.
 */
class ReformuladorConsultaService
{
    /**
     * Devuelve consultas alternativas escritas con el vocabulario de la ley.
     *
     * NUNCA incluye la consulta original: de eso se encarga el buscador, que la busca siempre.
     * Aquí solo salen las ALTERNATIVAS.
     *
     * @return array<string>  Vacío si no se pudo reformular. El buscador sigue igual.
     */
    public function reformular(string $consulta): array
    {
        if (! $this->estaActivo()) {
            return [];
        }

        // Una consulta de una o dos palabras no necesita reformularse: ya es un término.
        //
        // Se cuenta con preg_split y no con str_word_count() porque esa función NO ENTIENDE
        // ACENTOS: parte "habitación" en dos ("habitaci" + "n") e infla el conteo.
        $palabras = count(preg_split('/\s+/u', trim($consulta), -1, PREG_SPLIT_NO_EMPTY));

        if ($palabras < 3) {
            return [];
        }

        return Cache::remember(
            'reformulador:' . md5(mb_strtolower(trim($consulta))),
            now()->addHours((int) config('punta.asistente.cache_horas', 24)),
            fn () => $this->preguntarAlModelo($consulta)
        );
    }

    /**
     * ¿Está encendido Y configurado?
     *
     * Reutiliza el interruptor del asistente a propósito. Son la misma decisión: si el
     * Ayuntamiento apaga la IA, se apaga ENTERA. Tener dos interruptores para "la IA" sería
     * pedirle a alguien que se acuerde de los dos el día que haya que apagarla con prisa.
     */
    private function estaActivo(): bool
    {
        return (bool) config('punta.asistente.activo')
            && ! empty(config('punta.asistente.api_key'));
    }

    private function preguntarAlModelo(string $consulta): array
    {
        try {
            $respuesta = Http::withToken(config('punta.asistente.api_key'))
                // ── EL TIMEOUT: 8 segundos, no 5 ──
                //
                // Empezó en 5, y el log demostró que se agotaba:
                //
                //     cURL error 28: Connection timed out after 5000 milliseconds
                //
                // Y lo peor es que el modelo SÍ estaba respondiendo bien —el log tiene las
                // propuestas de otras veces— solo que a veces tarda un poco más. Estábamos
                // tirando reformulaciones correctas por medio segundo.
                //
                // Sigue siendo un compromiso incómodo: esto ocurre ANTES de buscar, así que el
                // ciudadano está esperando con la página en blanco. Ocho segundos son muchos.
                //
                // Pero la alternativa era peor: cero resultados. Más vale esperar ocho segundos y
                // encontrar la respuesta que responder en dos con las manos vacías.
                //
                // Y solo pasa cuando la búsqueda normal YA FALLÓ. En el caso normal, el
                // reformulador ni se llama.
                ->timeout(8)
                ->acceptJson()
                ->post(config('punta.asistente.url'), [
                    'model'    => config('punta.asistente.modelo', 'deepseek-v4-flash'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instrucciones()],
                        ['role' => 'user',   'content' => $consulta],
                    ],
                    'thinking'        => ['type' => 'disabled'],
                    'temperature'     => 0,
                    'max_tokens'      => 150,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($respuesta->failed()) {
                return [];
            }

            return $this->interpretar(
                $respuesta->json('choices.0.message.content'),
                $consulta
            );

        } catch (Throwable $e) {
            // Timeout, API caída, lo que sea. El buscador sigue con la consulta original.
            //
            // Este servicio es una MEJORA, nunca un requisito. Si falla, el buscador funciona
            // exactamente como funcionaba ayer.
            Log::warning('El reformulador de consultas no respondió.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
        Eres un traductor entre el lenguaje de la calle y el lenguaje de las leyes municipales
        mexicanas.

        Te doy la pregunta de un ciudadano. Tú devuelves DOS O TRES búsquedas alternativas,
        escritas con las palabras que USARÍA UNA LEY.

        NO respondes la pregunta. NO explicas nada. Solo propones por dónde buscar.

        ── EL PROBLEMA QUE RESUELVES ──

        El ciudadano y la ley usan palabras distintas para lo mismo:

            El ciudadano dice        La ley dice
            ─────────────────        ───────────────────────────────
            permiso                  derecho, cuota, licencia
            basura                   residuos sólidos, recolección, aseo público
            cuánto pago              tasa, tarifa, cuota, el impuesto será
            casa                      casa habitación, predio urbano
            calcular                 se causará, pagarán, a razón de
            terreno vacío            predio no edificado, baldío

        Y el buscador exige coincidencia EXACTA. Si el ciudadano escribe "permiso" y la ley dice
        "derecho", no encuentra nada.

        ── REGLAS ──

        1. Cada alternativa: 2 a 4 palabras. NO frases. NO preguntas. Son TÉRMINOS DE BÚSQUEDA.

        2. CONSERVA SIEMPRE EL SUSTANTIVO DEL TEMA. Si preguntan por basura, todas tus
           alternativas deben hablar de basura (o de residuos, o de recolección). NUNCA cambies
           de tema.

           Esto es lo más importante. Si traduces "basura" a "impuesto predial", el buscador
           encontrará artículos del predial y alguien recibirá una respuesta perfectamente
           citada y completamente falsa sobre lo que tiene que pagar.

        3. NO inventes conceptos jurídicos que no existan. Si no sabes cómo lo llama la ley, deja
           la palabra del ciudadano.

        4. Piensa en QUÉ PALABRAS APARECERÍAN EN EL ARTÍCULO, no en cómo se hace la pregunta.

        ── EJEMPLO ──

        Pregunta: "¿Qué tasa de impuesto predial corresponde a una casa habitación?"

        {
          "consultas": [
            "predial casa habitación",
            "millar valor catastral casa habitación",
            "predios destinados casa habitación"
          ]
        }

        Fíjate: "casa habitación" aparece en las tres. Es el ancla. Y se añaden los términos que
        una ley usaría: "al millar", "valor catastral", "predios destinados".

        ── FORMATO ──

        Responde SIEMPRE con este JSON, y nada más:

        {
          "consultas": ["...", "...", "..."]
        }
        TXT;
    }

    /**
     * Valida lo que devolvió el modelo. Aquí está el candado que importa.
     */
    private function interpretar(?string $contenido, string $consultaOriginal): array
    {
        if (empty($contenido)) {
            return [];
        }

        $datos = json_decode($contenido, true);

        if (! is_array($datos) || empty($datos['consultas'])) {
            return [];
        }

        $propuestas = collect((array) $datos['consultas'])
            ->filter(fn ($c) => is_string($c) && trim($c) !== '')
            ->map(fn ($c) => trim($c));

        $descartadas = [];

        $consultas = $propuestas

            // Máximo cuatro palabras. Si el modelo devuelve una frase, no es un término de
            // búsqueda: es una pregunta reescrita, y eso no sirve para nada.
            // ── OJO: str_word_count() NO ENTIENDE ACENTOS ──
            //
            // La primera versión usaba str_word_count($c) > 4. Y descartaba esto:
            //
            //     "millar valor catastral casa habitación"  →  "(es una frase, no un término)"
            //
            // Eso NO es una frase. Son cinco palabras clave, y perfectamente útiles.
            //
            // El problema es que str_word_count() cuenta solo caracteres ASCII. "habitación" la
            // parte en DOS: "habitaci" + "n". Y "valoración", "instalación", "sanción"... todas.
            //
            // Así que INFLABA el conteo y descartaba reformulaciones válidas — precisamente las
            // que llevan las palabras jurídicas, que en español van llenas de tildes.
            //
            // Es el MISMO error que el de los acentos en el buscador (str_word_count es a
            // preg_split lo que substr es a mb_substr), cometido por mí, en la función que
            // escribí para ayudar a arreglarlo.
            //
            // preg_split con /\s+/ cuenta palabras de verdad, tildes incluidas.
            ->filter(function ($c) use (&$descartadas) {
                $palabras = count(preg_split('/\s+/u', trim($c), -1, PREG_SPLIT_NO_EMPTY));

                // El límite sube a 5. Una reformulación jurídica útil ("millar valor catastral
                // casa habitación") tiene cinco términos con facilidad. Cuatro era demasiado
                // estrecho, y lo era por accidente: con el conteo roto, cuatro "de verdad"
                // parecían seis.
                if ($palabras > 5) {
                    $descartadas[] = "{$c} (demasiado larga: {$palabras} palabras)";

                    return false;
                }

                return true;
            })

            // ── EL CANDADO: cada alternativa debe conservar un ANCLA de la pregunta original ──
            //
            // Esto impide el fallo grave: que la IA cambie de tema.
            //
            //     "cuánto pago por la basura"  →  "impuesto predial"
            //
            // Sería una traducción plausible —los dos son cobros municipales— y produciría una
            // respuesta perfectamente citada sobre el impuesto equivocado. Los candados del
            // asistente no la detectarían: la cita existe, el dato está en el texto, el artículo
            // es real. Todo cuadra. Y es sobre otra cosa.
            //
            // Se exige que al menos una palabra significativa de la pregunta original sobreviva
            // en la reformulación. "Basura" puede convertirse en "residuos sólidos"... pero
            // entonces la palabra "residuos" tiene que estar, y "basura" no puede desaparecer sin
            // dejar nada suyo.
            //
            // No es infalible —la IA podría conservar "basura" y añadir "predial"— pero acota
            // mucho el desastre. Y la última defensa sigue siendo el asistente, que LEE los
            // artículos y descarta los que no responden.
            ->filter(function ($c) use ($consultaOriginal, &$descartadas) {
                if (! $this->conservaElTema($c, $consultaOriginal)) {
                    $descartadas[] = "{$c} (CAMBIÓ DE TEMA)";

                    return false;
                }

                return true;
            })

            ->take(3)
            ->values()
            ->all();

        // ── SIN ESTE LOG, EL REFORMULADOR TRABAJA A CIEGAS ──
        //
        // Este servicio puede fallar de tres formas, y las tres son SILENCIOSAS:
        //
        //   · El modelo propone frases en vez de términos  → se descartan → cero resultados
        //   · El modelo cambia de tema                     → se descartan → cero resultados
        //   · Se busca con las propuestas y no hay nada    → cero resultados
        //
        // Desde fuera, las tres se ven IGUAL: "no se encontraron resultados". Y no se puede
        // arreglar lo que no se puede ver.
        //
        // Es exactamente el patrón que este proyecto lleva persiguiendo, cometido por mí: un
        // servicio que falla sin dejar rastro.
        Log::info('Reformulador: consultas propuestas.', [
            'pregunta'    => $consultaOriginal,
            'propuestas'  => $propuestas->all(),
            'aceptadas'   => $consultas,
            'descartadas' => $descartadas,
        ]);

        return $consultas;
    }

    /**
     * ¿La reformulación conserva algo del tema original?
     *
     * Se comparan las raíces de las palabras largas (5+ letras), que son las que llevan el
     * significado. "Basura" y "basuras" comparten raíz; "basura" y "predial", no.
     *
     * ── SE COMPARA SIN ACENTOS, Y ESO NO ES UN DETALLE ──
     *
     * El ciudadano escribe sin tildes ("via publica"); la IA responde con ellas ("vía pública").
     * Si se comparan en crudo, "publi" (del ciudadano) y "públi" (de la IA) son cadenas DISTINTAS
     * byte a byte, no comparten nada, y una reformulación PERFECTA se descarta por "cambió de
     * tema".
     *
     * Fue exactamente lo que pasó con "cuánto cuesta la multa por extensión de la vía pública": la
     * IA propuso "ocupación vía pública" —la traducción correcta de la fracción VII TER, que la
     * ley llama así— y este guardián la mató porque la única raíz común, "publi", llevaba tilde en
     * un lado y no en el otro.
     *
     * Str::ascii quita los acentos ANTES de comparar. Es el mismo principio que el buscador ya
     * aplica en todo: los dos lados tienen que hablar el mismo idioma, y ese idioma es sin tildes.
     *
     * Es una heurística, no una garantía. Pero convierte un fallo catastrófico —cambiar de tema
     * sin que nadie lo note— en uno improbable, sin castigar a las reformulaciones correctas por
     * una tilde.
     */
    private function conservaElTema(string $reformulada, string $original): bool
    {
        $raices = fn (string $texto) => collect(preg_split('/\s+/', Str::ascii(mb_strtolower($texto))))
            ->filter(fn ($p) => mb_strlen($p) >= 5)
            ->map(fn ($p) => mb_substr($p, 0, 5)) // raíz aproximada: las 5 primeras letras
            ->unique();

        $delOriginal   = $raices($original);
        $deLaPropuesta = $raices($reformulada);

        if ($delOriginal->isEmpty()) {
            return true; // no hay nada que conservar
        }

        return $deLaPropuesta->intersect($delOriginal)->isNotEmpty();
    }
}
