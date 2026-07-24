<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Traduce una ficha ya leída (LectorFichaTramiteService) + los IDs de catálogo
 * resueltos (ResolverCatalogosFichaService) a los NOMBRES DE CAMPO del formulario
 * de alta de trámite, listos para precargar.
 *
 * Tres destinos distintos, según cómo consume cada dato el formulario:
 *
 *   - Escalares (nombre, homoclave...): viajan como "old input", así que los
 *     old('campo') que la vista ya tiene los recogen solos.
 *   - Costos y pasos: el asistente los maneja con arreglos JS sembrados desde los
 *     hidden #derechosJson y #pasosJson, así que aquí se generan esos JSON.
 *   - Requisitos: son inputs reales (requisitos[i][...]), así que se devuelven como
 *     lista y la vista los llena con un script.
 *
 * No decide nada de negocio: solo mapea. Lo que la ficha no traiga se omite, y el
 * formulario lo deja en blanco.
 */
class MapeadorFichaTramiteService
{
    /**
     * @param  array<string,mixed>  $ficha      salida de LectorFichaTramiteService
     * @param  array{dependencia_id:?int,unidad_id:?int}  $catalogos  salida de ResolverCatalogosFichaService
     * @return array{escalares: array<string,mixed>, requisitos: array, costos: array, pasos: array}
     */
    public function mapear(array $ficha, array $catalogos): array
    {
        [$plazoCantidad, $plazoUnidad] = $this->plazo($ficha['tiempo_respuesta'] ?? null);

        // Solo se incluyen las claves que traen valor; así el formulario precarga
        // lo que hay y deja el resto en blanco.
        $escalares = array_filter([
            'nombre_oficial'            => $ficha['nombre'] ?? null,
            'homoclave'                 => $ficha['homoclave'] ?? null,
            'naturaleza'                => $ficha['naturaleza'] ?? null,
            'dirigido_a'                => $ficha['dirigido_a'] ?? null,
            'objetivo'                  => $ficha['descripcion'] ?? null,
            'dependencia_id'            => $catalogos['dependencia_id'] ?? null,
            'unidad_id'                 => $catalogos['unidad_id'] ?? null,
            'plazo_resolucion_cantidad' => $plazoCantidad,
            'plazo_resolucion_unidad'   => $plazoUnidad,
        ], fn ($v) => $v !== null && $v !== '');

        // Costos y pasos: se siembran vía sus hidden JSON, que el JS del asistente
        // ya lee al cargar la página. Solo se agregan si hay datos, para no pisar
        // el valor por defecto '[]' cuando la ficha no trae nada.
        $derechos = $this->derechos($ficha['costos'] ?? []);
        if ($derechos !== []) {
            $escalares['derechos_json'] = json_encode($derechos, JSON_UNESCAPED_UNICODE);
        }

        $pasos = $this->pasos($ficha['procedimiento'] ?? []);
        if ($pasos !== []) {
            $escalares['pasos_json'] = json_encode($pasos, JSON_UNESCAPED_UNICODE);
        }

        return [
            'escalares'  => $escalares,
            'requisitos' => $this->requisitos($ficha['requisitos'] ?? []),
            'costos'     => $ficha['costos'] ?? [],
            'pasos'      => $ficha['procedimiento'] ?? [],
        ];
    }

    /**
     * Costos de la ficha -> formato del hidden #derechosJson:
     * {concepto, monto, unidad, es_variable}. Como etiqueta visible se usa la
     * descripción del costo, que es más específica que el concepto legal.
     *
     * @return list<array{concepto:string,monto:float,unidad:string,es_variable:bool}>
     */
    private function derechos(array $costos): array
    {
        $out = [];
        foreach ($costos as $c) {
            $out[] = [
                'concepto'    => (string) ($c['descripcion'] ?? $c['concepto'] ?? ''),
                'monto'       => (float) ($c['costo'] ?? 0),
                'unidad'      => 'pesos',
                'es_variable' => false,
            ];
        }
        return $out;
    }

    /**
     * Pasos de la ficha -> formato del hidden #pasosJson:
     * {es_subpaso, area, accion}.
     *
     * @return list<array{es_subpaso:bool,area:string,accion:string}>
     */
    private function pasos(array $procedimiento): array
    {
        $out = [];
        foreach ($procedimiento as $paso) {
            $texto = trim((string) $paso);
            if ($texto !== '') {
                $out[] = ['es_subpaso' => false, 'area' => '', 'accion' => $texto];
            }
        }
        return $out;
    }

    /**
     * Requisitos de la ficha, normalizados para el script de la vista.
     *
     * @return list<array{nombre:string,original:int,copias:int}>
     */
    private function requisitos(array $requisitos): array
    {
        $out = [];
        foreach ($requisitos as $r) {
            $nombre = trim((string) ($r['documento'] ?? ''));
            if ($nombre !== '') {
                $out[] = [
                    'nombre'   => $nombre,
                    'original' => (int) ($r['original'] ?? 0),
                    'copias'   => (int) ($r['copias'] ?? 0),
                ];
            }
        }
        return $out;
    }

    /**
     * "2-DÍAS HÁBILES" -> [2, 'habiles'].  "5 días naturales" -> [5, 'naturales'].
     * Si no se reconoce, [null, null] y el usuario lo llena.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function plazo(?string $texto): array
    {
        if (empty($texto)) {
            return [null, null];
        }

        $cantidad = preg_match('/(\d+)/', $texto, $m) ? (int) $m[1] : null;

        // Str::ascii quita los acentos ANTES de comparar: la ficha escribe
        // "DÍAS HÁBILES", y sin esto "hábiles" nunca casaría con "habil".
        $t = mb_strtolower(Str::ascii($texto));
        $unidad = match (true) {
            str_contains($t, 'habil')   => 'habiles',
            str_contains($t, 'natural') => 'naturales',
            default                     => null,
        };

        return [$cantidad, $unidad];
    }
}
