<?php

namespace App\Services;

/**
 * Detecta qué quiere saber el usuario a partir de la consulta ya
 * normalizada, usando patrones de palabras clave (sin IA, como pide la
 * Fase 1 de la especificación del buscador robusto).
 *
 * Regla de la especificación que este servicio respeta: "La intención no
 * reemplaza la búsqueda, solo ayuda a priorizar fuentes y ordenar
 * resultados." Por eso este servicio SOLO clasifica — no busca nada, no
 * decide qué mostrar. Eso lo hacen FeaturedAnswerService y BuscadorService
 * usando el resultado de esta clasificación.
 *
 * Intenciones cubiertas en la Fase 1 (las 4 más frecuentes y más fáciles
 * de detectar con certeza). Las demás de la especificación (vigencia,
 * autoridad_competente, sancion, procedimiento, etc.) se agregan en fases
 * posteriores, cuando haya índices especializados que las puedan resolver.
 */
class SearchIntentDetector
{
    public const DEFINICION  = 'definicion';
    public const REQUISITOS  = 'requisitos';
    public const COSTO       = 'costo';
    public const FUNDAMENTO  = 'fundamento';

    /**
     * Patrones por intención. Se buscan como subcadena dentro de la
     * consulta ya normalizada (minúsculas, sin acentos). El orden importa:
     * se evalúan de arriba hacia abajo y se devuelve la primera que coincida.
     */
    private const PATRONES = [
        self::DEFINICION => [
            'que es', 'que son', 'definicion', 'significa', 'significado',
        ],
        self::REQUISITOS => [
            'requisito', 'requisitos', 'documentos', 'que necesito', 'que pide', 'que piden',
        ],
        self::COSTO => [
            'costo', 'cuesta', 'cuanto vale', 'precio', 'pago', 'monto', 'tarifa',
        ],
        self::FUNDAMENTO => [
            'fundamento', 'fundamenta', 'base legal', 'en que ley', 'que articulo',
        ],
    ];

    /**
     * Detecta la intención de una consulta ya normalizada (el texto
     * completo, no solo la lista de palabras, porque algunos patrones son
     * de varias palabras como "que es" o "base legal").
     *
     * @return string|null  Una de las constantes de esta clase, o null si
     *                       no se detectó ninguna intención con confianza.
     */
    public function detectar(string $consultaNormalizada): ?string
    {
        foreach (self::PATRONES as $intencion => $patrones) {
            foreach ($patrones as $patron) {
                if (str_contains($consultaNormalizada, $patron)) {
                    return $intencion;
                }
            }
        }

        return null;
    }
}
