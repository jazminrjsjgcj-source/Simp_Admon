<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Respaldo con IA del parser determinista de fichas. Cuando FichaTramiteParserService
 * saca muy poco (ficha rota, de otra plantilla, mal escaneada), este servicio le
 * pide a DeepSeek que extraiga los MISMOS campos del texto, con el MISMO esquema,
 * para que sean intercambiables.
 *
 * Solo EXTRAE lo que aparece en el texto; no inventa. Los campos ausentes van a
 * null (o [] en listas). Si la API falla, devuelve null y el orquestador se queda
 * con lo que haya sacado el parser determinista.
 *
 * Reutiliza la integración de DeepSeek del asistente (config('punta.asistente.*')).
 */
class FichaTramiteExtractorIaService
{
    /**
     * @return array<string,mixed>|null
     */
    public function extraer(string $textoFicha): ?array
    {
        if (! $this->disponible()) {
            return null;
        }

        try {
            $respuesta = Http::withToken(config('punta.asistente.api_key'))
                ->timeout((int) config('punta.asistente.timeout', 15))
                ->acceptJson()
                ->post(config('punta.asistente.url'), [
                    'model'    => config('punta.asistente.modelo', 'deepseek-v4-flash'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instrucciones()],
                        ['role' => 'user',   'content' => $textoFicha],
                    ],
                    'thinking'        => ['type' => 'disabled'],
                    'temperature'     => 0,
                    'max_tokens'      => 1200,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($respuesta->failed()) {
                Log::warning('Extractor IA de ficha: la API devolvió error.', [
                    'estado' => $respuesta->status(),
                    'cuerpo' => Str::limit($respuesta->body(), 300),
                ]);
                return null;
            }

            return $this->sanear($respuesta->json('choices.0.message.content'));

        } catch (Throwable $e) {
            Log::warning('Extractor IA de ficha: la API no respondió.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function disponible(): bool
    {
        return (bool) config('punta.asistente.activo')
            && ! empty(config('punta.asistente.api_key'));
    }

    /**
     * Convierte el JSON del modelo en el esquema de la ficha, con tipos correctos.
     * Devuelve null si viene vacío o mal formado.
     *
     * @return array<string,mixed>|null
     */
    private function sanear(?string $contenido): ?array
    {
        if (! is_string($contenido) || trim($contenido) === '') {
            return null;
        }

        $d = json_decode($contenido, true);
        if (! is_array($d)) {
            return null;
        }

        $texto = fn ($v) => (is_string($v) && trim($v) !== '') ? trim($v) : null;

        return [
            'nombre'           => $texto($d['nombre'] ?? null),
            'homoclave'        => $texto($d['homoclave'] ?? null),
            'naturaleza'       => in_array($d['naturaleza'] ?? null, ['tramite', 'servicio'], true) ? $d['naturaleza'] : null,
            'dirigido_a'       => in_array($d['dirigido_a'] ?? null, ['ciudadano', 'empresaria', 'ambas'], true) ? $d['dirigido_a'] : null,
            'descripcion'      => $texto($d['descripcion'] ?? null),
            'tiene_cobro'      => (bool) ($d['tiene_cobro'] ?? false),
            'costos'           => $this->costos($d['costos'] ?? null),
            'lugar_pago'       => $texto($d['lugar_pago'] ?? null),
            'forma_pago'       => $texto($d['forma_pago'] ?? null),
            'ley'              => $texto($d['ley'] ?? null),
            'articulos'        => $texto($d['articulos'] ?? null),
            'vigencia'         => $texto($d['vigencia'] ?? null),
            'tiempo_respuesta' => $texto($d['tiempo_respuesta'] ?? null),
            'requisitos'       => $this->requisitos($d['requisitos'] ?? null),
            'modulo'           => [
                'nombre'    => $texto($d['modulo']['nombre'] ?? null),
                'ubicacion' => $texto($d['modulo']['ubicacion'] ?? null),
                'horarios'  => $texto($d['modulo']['horarios'] ?? null),
                'telefonos' => $texto($d['modulo']['telefonos'] ?? null),
                'emails'    => $texto($d['modulo']['emails'] ?? null),
            ],
            'procedimiento'    => array_values(array_filter(array_map(
                fn ($p) => is_string($p) ? trim($p) : '',
                is_array($d['procedimiento'] ?? null) ? $d['procedimiento'] : []
            ))),
            'condicion'        => $texto($d['condicion'] ?? null),
            'afirmativa_ficta' => (bool) ($d['afirmativa_ficta'] ?? false),
        ];
    }

    /** @return list<array{concepto:?string,descripcion:?string,formula:?string,costo:float}> */
    private function costos($valor): array
    {
        if (! is_array($valor)) {
            return [];
        }
        $out = [];
        foreach ($valor as $c) {
            if (! is_array($c)) {
                continue;
            }
            $out[] = [
                'concepto'    => isset($c['concepto']) ? (string) $c['concepto'] : null,
                'descripcion' => isset($c['descripcion']) ? (string) $c['descripcion'] : null,
                'formula'     => isset($c['formula']) ? (string) $c['formula'] : null,
                'costo'       => (float) str_replace([',', '$'], '', (string) ($c['costo'] ?? 0)),
            ];
        }
        return $out;
    }

    /** @return list<array{documento:?string,original:int,copias:int}> */
    private function requisitos($valor): array
    {
        if (! is_array($valor)) {
            return [];
        }
        $out = [];
        foreach ($valor as $r) {
            if (! is_array($r)) {
                continue;
            }
            $out[] = [
                'documento' => isset($r['documento']) ? (string) $r['documento'] : null,
                'original'  => (int) ($r['original'] ?? 0),
                'copias'    => (int) ($r['copias'] ?? 0),
            ];
        }
        return $out;
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Eres un extractor de datos. Recibes el TEXTO de una ficha de trámite/servicio municipal (Reporte Catálogo de Trámites y Servicios) y devuelves sus datos en JSON.

Devuelve EXCLUSIVAMENTE un objeto JSON con exactamente este esquema:
{
  "nombre": string|null,
  "homoclave": string|null,
  "naturaleza": "tramite"|"servicio"|null,
  "dirigido_a": "ciudadano"|"empresaria"|"ambas"|null,
  "descripcion": string|null,
  "tiene_cobro": boolean,
  "costos": [{"concepto": string, "descripcion": string, "formula": string, "costo": number}],
  "lugar_pago": string|null,
  "forma_pago": string|null,
  "ley": string|null,
  "articulos": string|null,
  "vigencia": string|null,
  "tiempo_respuesta": string|null,
  "requisitos": [{"documento": string, "original": number, "copias": number}],
  "modulo": {"nombre": string|null, "ubicacion": string|null, "horarios": string|null, "telefonos": string|null, "emails": string|null},
  "procedimiento": [string],
  "condicion": string|null,
  "afirmativa_ficta": boolean
}

Reglas:
- Extrae SOLO lo que aparece en el texto. NO inventes ni completes datos que no estén.
- Si un campo no aparece, usa null (o [] en las listas).
- "naturaleza": "servicio" si el TIPO es SERVICIO; si no, "tramite".
- "dirigido_a": según qué CLASE tenga la marca X (CIUDADANO, EMPRESARIA, o ambas).
- "tiene_cobro": true si "TIENE COBRO" está marcado SI.
- "afirmativa_ficta": false si dice "NO APLICA AFIRMATIVA FICTA".
- "costo": número sin símbolo de moneda ni comas.
TXT;
    }
}
