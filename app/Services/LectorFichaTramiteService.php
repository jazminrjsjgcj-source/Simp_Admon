<?php

namespace App\Services;

/**
 * Lee una ficha de trámite combinando las dos estrategias:
 *
 *   1) FichaTramiteParserService (determinista): rápido, gratis, exacto para la plantilla
 *      estándar. Es el que manda.
 *   2) FichaTramiteExtractorIaService (respaldo): solo entra si el determinista sacó poco
 *      —ficha rara, de otra plantilla o mal extraída—.
 *
 * El texto del PDF se extrae UNA sola vez y se comparte con ambos. El resultado
 * combinado prefiere lo determinista y rellena los huecos con lo de la IA.
 *
 * Es lo que el controlador llamará para precargar el formulario de alta.
 */
class LectorFichaTramiteService
{
    public function __construct(
        private FichaTramiteParserService $parser,
        private FichaTramiteExtractorIaService $ia,
    ) {}

    /**
     * @return array<string,mixed>|null  ficha (con clave '_fuente'), o null si no se pudo leer el PDF.
     */
    public function leer(string $rutaPdf): ?array
    {
        $texto = $this->parser->extraerTexto($rutaPdf);
        if ($texto === null) {
            return null;
        }

        $determinista = $this->parser->parsearTexto($texto);

        // Si el determinista trae lo esencial, se usa tal cual (sin gastar IA).
        if ($this->suficiente($determinista)) {
            return $determinista + ['_fuente' => 'determinista'];
        }

        // Respaldo con IA sobre el mismo texto.
        $porIa = $this->ia->extraer($texto);
        if ($porIa === null) {
            // La IA no está o falló: se devuelve lo poco que haya, mejor que nada.
            return $determinista + ['_fuente' => 'determinista'];
        }

        return $this->combinar($determinista, $porIa) + ['_fuente' => 'ia'];
    }

    /**
     * ¿El resultado determinista trae lo mínimo para no molestar a la IA?
     * Criterio: tiene nombre y al menos dos campos clave más.
     */
    private function suficiente(array $d): bool
    {
        if (empty($d['nombre'])) {
            return false;
        }

        $clave = 0;
        foreach (['naturaleza', 'descripcion', 'ley', 'costos', 'requisitos'] as $k) {
            if (! empty($d[$k])) {
                $clave++;
            }
        }

        return $clave >= 2;
    }

    /**
     * Combina dos fichas: gana el determinista; donde éste esté vacío/null/[], se
     * toma el valor de la IA. Se recorre a un nivel (incluye el sub-arreglo 'modulo').
     *
     * @param  array<string,mixed>  $base   determinista (preferido)
     * @param  array<string,mixed>  $relleno IA
     * @return array<string,mixed>
     */
    private function combinar(array $base, array $relleno): array
    {
        $resultado = $base;

        foreach ($relleno as $clave => $valorIa) {
            $valorBase = $base[$clave] ?? null;

            if ($clave === 'modulo' && is_array($valorBase) && is_array($valorIa)) {
                // El módulo se combina campo por campo.
                foreach ($valorIa as $sub => $subIa) {
                    if (empty($valorBase[$sub])) {
                        $resultado['modulo'][$sub] = $subIa;
                    }
                }
                continue;
            }

            // Para el resto: si el determinista está vacío, usar el de la IA.
            if ($this->vacio($valorBase) && ! $this->vacio($valorIa)) {
                $resultado[$clave] = $valorIa;
            }
        }

        return $resultado;
    }

    /** null, cadena vacía o arreglo vacío cuentan como "vacío". false NO es vacío. */
    private function vacio($v): bool
    {
        return $v === null || $v === '' || $v === [];
    }
}
