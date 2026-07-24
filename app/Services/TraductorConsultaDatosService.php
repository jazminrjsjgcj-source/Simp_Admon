<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Traduce una pregunta en lenguaje natural ("¿cuántos trámites siguen en
 * borrador?") a una "receta" estructurada que ConsultaDatosService puede ejecutar.
 *
 * REGLA DE ORO: la IA SOLO traduce; no calcula ni decide la seguridad. La receta
 * que devuelve se vuelve a validar contra la lista blanca (config/consulta_datos.php)
 * dentro de ConsultaDatosService antes de tocar la base. Aquí, si algo falla —API
 * caída, JSON inválido, o no es una pregunta de datos— se devuelve null y el
 * buscador sigue funcionando exactamente como hoy.
 *
 * Reutiliza la misma integración de DeepSeek que el asistente del buscador
 * (config('punta.asistente.*')), para no tener dos formas distintas de hablar con
 * la API.
 */
class TraductorConsultaDatosService
{
    /**
     * Devuelve una receta ['entidad','metrica','filtros','agrupar'] o null.
     * null = no aplica, IA apagada, o fallo (el llamador cae a la búsqueda normal).
     */
    public function traducir(string $pregunta): ?array
    {
        if (! $this->disponible() || ! $this->pareceDeDatos($pregunta)) {
            return null;
        }

        try {
            $respuesta = Http::withToken(config('punta.asistente.api_key'))
                ->timeout((int) config('punta.asistente.timeout', 8))
                ->acceptJson()
                ->post(config('punta.asistente.url'), [
                    'model'    => config('punta.asistente.modelo', 'deepseek-v4-flash'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instrucciones()],
                        ['role' => 'user',   'content' => $pregunta],
                    ],
                    // Mismo criterio que el asistente: sin "thinking" (más barato) y
                    // temperatura 0 (traducción determinista, no creativa).
                    'thinking'        => ['type' => 'disabled'],
                    'temperature'     => 0,
                    'max_tokens'      => 300,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($respuesta->failed()) {
                Log::warning('Traductor de consulta: la API devolvió error.', [
                    'estado' => $respuesta->status(),
                    'cuerpo' => Str::limit($respuesta->body(), 300),
                ]);
                return null;
            }

            return $this->interpretar($respuesta->json('choices.0.message.content'));

        } catch (Throwable $e) {
            // Timeout, DNS, cortafuegos, API de mal humor... da igual: no romper.
            Log::warning('Traductor de consulta: la API no respondió.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** La IA solo se usa si el asistente está activo y hay credenciales. */
    private function disponible(): bool
    {
        return (bool) config('punta.asistente.activo')
            && ! empty(config('punta.asistente.api_key'))
            && ! empty(config('consulta_datos.entidades'));
    }

    /**
     * Compuerta barata: ¿la pregunta parece "de datos" (contar/listar/agrupar)?
     * Evita gastar una llamada a la API en cada búsqueda de texto normal. Es un
     * filtro grueso a propósito: si pasa, la IA decide de verdad; si no hay señal
     * analítica, ni se molesta en preguntar.
     */
    private function pareceDeDatos(string $pregunta): bool
    {
        $p = ' ' . mb_strtolower(Str::ascii($pregunta)) . ' ';

        return (bool) preg_match(
            '/\b(cuant[oa]s?|cuent[ae]|numero de|total de|cuales|listar|lista de|agrupa|por dependencia|por mes|por estatus|en estatus|en borrador|en revision)\b/',
            $p
        );
    }

    /**
     * Convierte el texto JSON del modelo en una receta, o null si viene vacío,
     * mal formado, o el modelo dijo que la pregunta no aplica. NO valida contra la
     * lista blanca (eso lo hace ConsultaDatosService): aquí solo saneamos forma.
     */
    private function interpretar(?string $contenido): ?array
    {
        if (! is_string($contenido) || trim($contenido) === '') {
            return null;
        }

        $datos = json_decode($contenido, true);
        if (! is_array($datos)) {
            return null;
        }

        // El modelo marca con "aplica": false cuando NO es una pregunta de datos.
        if (($datos['aplica'] ?? null) === false) {
            return null;
        }

        if (empty($datos['entidad']) || empty($datos['metrica'])) {
            return null;
        }

        // Forma mínima y limpia; los valores se validan después contra el config.
        return [
            'entidad' => (string) $datos['entidad'],
            'metrica' => (string) $datos['metrica'],
            'filtros' => is_array($datos['filtros'] ?? null) ? $datos['filtros'] : [],
            'agrupar' => ! empty($datos['agrupar']) ? (string) $datos['agrupar'] : null,
        ];
    }

    /**
     * Instrucciones para el modelo. El "catálogo" de lo permitido se arma DESDE el
     * config: si cambias config/consulta_datos.php, este prompt cambia solo.
     */
    private function instrucciones(): string
    {
        $catalogo = $this->catalogo();
        $metricas = implode(', ', (array) config('consulta_datos.metricas', ['conteo', 'lista', 'agrupar']));

        return <<<TXT
Eres un traductor. Conviertes la pregunta del usuario en un objeto JSON para consultar una base de datos municipal. NO respondes la pregunta ni inventas datos: solo produces el JSON.

Devuelve EXCLUSIVAMENTE un objeto JSON, sin texto alrededor, con esta forma:
{"aplica": true, "entidad": "<entidad>", "metrica": "<metrica>", "filtros": {"<filtro>": "<valor>"}, "agrupar": "<dimension o null>"}

Reglas:
- Usa SOLO las entidades, filtros, valores y dimensiones del catálogo de abajo. Si algo no está, no lo inventes.
- "metrica" debe ser una de: {$metricas}. Usa "conteo" para "cuántos", "lista" para "cuáles", "agrupar" para "por dependencia / por mes / por estatus".
- Si la pregunta NO se puede responder con este catálogo (no es sobre estos datos, o pide algo no permitido), responde {"aplica": false}.
- Si no hay filtros, usa "filtros": {}. Si no se agrupa, usa "agrupar": null.

CATÁLOGO:
{$catalogo}
TXT;
    }

    /** Texto legible del config: entidades, sus filtros con valores, y dimensiones. */
    private function catalogo(): string
    {
        $lineas = [];

        foreach ((array) config('consulta_datos.entidades', []) as $clave => $ent) {
            $lineas[] = "- Entidad \"{$clave}\" ({$ent['label']}):";

            $filtros = [];
            foreach ((array) ($ent['filtros'] ?? []) as $fClave => $fDef) {
                $valores = ! empty($fDef['valores'])
                    ? ' = uno de [' . implode(', ', $fDef['valores']) . ']'
                    : ' = (cualquier valor)';
                $filtros[] = "\"{$fClave}\"{$valores}";
            }
            $lineas[] = '    filtros: ' . ($filtros ? implode('; ', $filtros) : '(ninguno)');

            $dims = array_keys((array) ($ent['dimensiones'] ?? []));
            $lineas[] = '    agrupar por: ' . ($dims ? implode(', ', $dims) : '(ninguna)');
        }

        return implode("\n", $lineas);
    }
}
