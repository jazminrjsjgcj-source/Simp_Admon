<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Lee una "ficha" (el Reporte Catálogo de Trámites y Servicios que genera el
 * propio sistema) y devuelve sus datos en un arreglo estructurado, para precargar
 * el formulario de alta de trámite.
 *
 * Es DETERMINISTA: aprovecha que la ficha tiene una plantilla fija (secciones y
 * etiquetas siempre iguales) para extraer por posición y etiqueta, sin IA y sin
 * costo. Si una ficha viene rara y el parser saca muy poco, otra capa (respaldo
 * con IA) puede tomar el relevo; por eso este servicio nunca lanza por campos
 * faltantes: lo que no encuentra queda en null y el usuario lo completa.
 *
 * Extrae el texto con `pdftotext -layout`, que conserva la alineación en columnas
 * de la plantilla (indispensable para separar, p. ej., NOMBRE de HOMOCLAVE, o la
 * columna de "lugar de pago" de la de "forma de pago").
 */
class FichaTramiteParserService
{
    /**
     * @return array<string,mixed>|null  datos de la ficha, o null si no se pudo leer el PDF.
     */
    public function parsear(string $rutaPdf): ?array
    {
        $texto = $this->extraerTexto($rutaPdf);

        return $texto === null ? null : $this->parsearTexto($texto);
    }

    /**
     * Parsea el TEXTO ya extraído de una ficha. Separado de parsear() para que el
     * respaldo con IA pueda trabajar sobre el mismo texto sin volver a leer el PDF.
     *
     * @return array<string,mixed>
     */
    public function parsearTexto(string $texto): array
    {
        // Los saltos de página estorban; se tratan como salto de línea normal.
        $t = str_replace("\f", "\n", $texto);

        return [
            'nombre'           => $this->nombre($t),
            'homoclave'        => $this->homoclave($t),
            'naturaleza'       => $this->naturaleza($t),      // tramite | servicio
            'dirigido_a'       => $this->dirigidoA($t),        // ciudadano | empresaria | ambas
            'descripcion'      => $this->descripcion($t),
            'tiene_cobro'      => $this->tieneCobro($t),
            'costos'           => $this->costos($t),           // [{concepto,descripcion,formula,costo}]
            'lugar_pago'       => $this->lugarFormaPago($t)['lugar'],
            'forma_pago'       => $this->lugarFormaPago($t)['forma'],
            'ley'              => $this->lineaEtiqueta($t, 'LEY', anclarInicio: true),
            'articulos'        => $this->lineaEtiqueta($t, 'ART[ÍI]CULOS'),
            'vigencia'         => $this->vigenciaTiempo($t)['vigencia'],
            'tiempo_respuesta' => $this->vigenciaTiempo($t)['tiempo'],
            'requisitos'       => $this->requisitos($t),       // [{documento,original,copias}]
            'modulo'           => [
                'nombre'    => $this->lineaEtiqueta($t, 'M[ÓO]DULO', anclarInicio: true),
                'ubicacion' => $this->ubicacion($t),
                'horarios'  => $this->lineaEtiqueta($t, 'HORARIOS'),
                'telefonos' => $this->lineaEtiqueta($t, 'TEL[ÉE]FONOS'),
                'emails'    => $this->lineaEtiqueta($t, 'EMAILS'),
            ],
            'procedimiento'    => $this->procedimiento($t),    // [pasos]
            'condicion'        => $this->condicion($t),
            'afirmativa_ficta' => $this->afirmativaFicta($t),
        ];
    }

    /** Ejecuta pdftotext -layout y devuelve el texto, o null si falla. Público
     *  para que el respaldo con IA use el MISMO texto sin volver a leer el PDF. */
    public function extraerTexto(string $rutaPdf): ?string
    {
        $salida = tempnam(sys_get_temp_dir(), 'ficha') . '.txt';

        $proc = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $rutaPdf, $salida]);
        $proc->setTimeout(30);
        $proc->run();

        if (! $proc->isSuccessful() || ! is_file($salida)) {
            @unlink($salida);
            return null;
        }

        $texto = (string) file_get_contents($salida);
        @unlink($salida);

        return $texto !== '' ? $texto : null;
    }

    // ── Campos simples ───────────────────────────────────────────────────

    private function nombre(string $t): ?string
    {
        return preg_match('#NOMBRE\s+(.*?)\s{2,}HOMOCLAVE#iu', $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    private function homoclave(string $t): ?string
    {
        return preg_match('#HOMOCLAVE\s+(\S[^\n]*)#iu', $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    private function naturaleza(string $t): ?string
    {
        if (! preg_match('#TIPO\s+(TR[ÁA]MITE|SERVICIO)#iu', $t, $m)) {
            return null;
        }
        return str_contains(mb_strtoupper($m[1]), 'SERV') ? 'servicio' : 'tramite';
    }

    private function dirigidoA(string $t): ?string
    {
        if (! preg_match('#CLASE\s+CIUDADANO\s+(X?)\s+EMPRESARIA\s*(X?)#iu', $t, $m)) {
            return null;
        }
        $ciudadano  = trim($m[1]) !== '';
        $empresaria = trim($m[2] ?? '') !== '';

        return match (true) {
            $ciudadano && $empresaria => 'ambas',
            $ciudadano                => 'ciudadano',
            $empresaria               => 'empresaria',
            default                   => null,
        };
    }

    private function descripcion(string $t): ?string
    {
        return preg_match('#DESCRIPCI[ÓO]N\s+(.*?)\n\s*COBRO#isu', $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    private function tieneCobro(string $t): bool
    {
        return preg_match('#TIENE COBRO\s+SI\s+(X?)\s+NO\s*(X?)#iu', $t, $m) === 1
            && trim($m[1]) !== '';
    }

    private function afirmativaFicta(string $t): bool
    {
        // La ficha dice "NO APLICA AFIRMATIVA FICTA" cuando NO aplica.
        return preg_match('#NO APLICA AFIRMATIVA FICTA#iu', $t) !== 1;
    }

    // ── Bloques repetibles ───────────────────────────────────────────────

    /** @return list<array{concepto:string,descripcion:string,formula:string,costo:float}> */
    private function costos(string $t): array
    {
        preg_match_all(
            '#CONCEPTO\s+(.*?)DESCRIPCI[ÓO]N\s+(.*?)FORMULA\s+(.*?)COSTO\s+\$?([\d,]+\.?\d*)#isu',
            $t,
            $bloques,
            PREG_SET_ORDER
        );

        return array_map(fn ($b) => [
            'concepto'    => $this->limpiar($b[1]),
            'descripcion' => $this->limpiar($b[2]),
            'formula'     => $this->limpiar($b[3]),
            'costo'       => (float) str_replace(',', '', $b[4]),
        ], $bloques);
    }

    /** @return list<array{documento:string,original:int,copias:int}> */
    private function requisitos(string $t): array
    {
        preg_match_all(
            '#NOMBRE DEL DOCUMENTO\s+(.*?)\n.*?ORIGINAL\s+(\d+).*?COPIAS\s+(\d+)#isu',
            $t,
            $bloques,
            PREG_SET_ORDER
        );

        return array_map(fn ($b) => [
            'documento' => $this->limpiar($b[1]),
            'original'  => (int) $b[2],
            'copias'    => (int) $b[3],
        ], $bloques);
    }

    /** @return list<string> */
    private function procedimiento(string $t): array
    {
        if (! preg_match('#PROCEDIMIENTO EN M[ÓO]DULO\s+(.*?)\n\s*CONDICI[ÓO]N#isu', $t, $m)) {
            return [];
        }

        $pasos = [];
        foreach (explode("\n", $m[1]) as $linea) {
            $linea = $this->limpiar($linea);
            if ($linea !== '') {
                $pasos[] = $linea;
            }
        }
        return $pasos;
    }

    private function condicion(string $t): ?string
    {
        return preg_match('#CONDICI[ÓO]N PARA REALIZAR EL\s+TR[ÁA]MITE\s+([^\n]+)#iu', $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    private function ubicacion(string $t): ?string
    {
        return preg_match('#UBICACI[ÓO]N\s+(.*?)\n\s*HORARIOS#isu', $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    // ── Dos columnas: lugar y forma de pago ──────────────────────────────

    /** @return array{lugar:?string,forma:?string} */
    private function lugarFormaPago(string $t): array
    {
        $vacio = ['lugar' => null, 'forma' => null];

        // Posición donde arranca la columna derecha ("FORMA DE PAGO") en la cabecera.
        if (! preg_match('#^(.*LUGAR DE PAGO.*?)(FORMA DE PAGO)#imu', $t, $h, PREG_OFFSET_CAPTURE)) {
            return $vacio;
        }
        if (! preg_match('#LUGAR DE PAGO\s+FORMA DE PAGO\s*\n(.*?)\n\s*MARCO JUR#isu', $t, $b)) {
            return $vacio;
        }

        // Columna = cuántos caracteres hay antes de "FORMA DE PAGO" en la cabecera.
        $col = mb_strlen(substr($h[1][0], 0, $h[2][1] - $h[1][1]));

        $izq = [];
        $der = [];
        foreach (explode("\n", $b[1]) as $linea) {
            $l = trim(mb_substr($linea, 0, $col));
            $r = trim(mb_substr($linea, $col));
            if ($l !== '') { $izq[] = $l; }
            if ($r !== '') { $der[] = $r; }
        }

        return [
            'lugar' => $izq ? implode(' ', $izq) : null,
            'forma' => $der ? implode(' ', $der) : null,
        ];
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    /** Vigencia y tiempo de respuesta comparten renglón, en dos columnas. */
    private function vigenciaTiempo(string $t): array
    {
        if (preg_match('#Vigencia\s+(.*?)\s{2,}Tiempo m[áa]ximo de respuesta\s+(\S[^\n]*)#iu', $t, $m)) {
            return ['vigencia' => $this->limpiar($m[1]), 'tiempo' => $this->limpiar($m[2])];
        }
        return ['vigencia' => null, 'tiempo' => null];
    }

    /**
     * Valor de una etiqueta que ocupa UNA línea (LEY, ARTÍCULOS, HORARIOS...).
     * Captura solo hasta el fin de línea, para no arrastrar la sección siguiente
     * cuando el campo viene vacío.
     */
    private function lineaEtiqueta(string $t, string $etiqueta, bool $anclarInicio = false): ?string
    {
        $ancla = $anclarInicio ? '^' : '';
        $flags = $anclarInicio ? 'imu' : 'iu';

        return preg_match("#{$ancla}{$etiqueta}[ \\t]+([^\\n]+)#{$flags}", $t, $m)
            ? $this->limpiar($m[1]) : null;
    }

    /** Colapsa espacios/saltos y recorta. */
    private function limpiar(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
