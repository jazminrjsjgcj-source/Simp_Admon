<?php

namespace App\Services;

use App\Support\TextoNormalizador;

/**
 * Limpia la consulta de búsqueda del usuario antes de que las demás capas
 * (detector de intención, diccionario, FULLTEXT) la procesen.
 *
 * Responsabilidad única: convertir texto libre en las distintas formas que
 * cada capa necesita. No decide qué significan esas palabras (eso es
 * SearchIntentDetector) ni busca nada en ninguna tabla.
 *
 * Devuelve DOS versiones de la consulta, no una sola, porque cada capa
 * necesita algo distinto:
 *
 *   - 'consulta_normalizada': el texto completo con acentos y mayúsculas
 *     fuera, PERO CONSERVANDO las palabras vacías ("que", "es", "un"...).
 *     SearchIntentDetector la necesita así porque sus patrones buscan
 *     frases completas como "que es" o "en que ley" — si esas palabras
 *     se quitaran aquí, la intención de definición nunca se detectaría.
 *
 *   - 'palabras': la lista de palabras SIN las vacías, para todo lo que
 *     necesite comparar por concepto (LegalDictionaryService) o construir
 *     una consulta FULLTEXT sin ruido.
 *
 * Ejemplo:
 *   "¿Qué es un servicio?"
 *     -> consulta_normalizada: "que es un servicio"
 *     -> palabras: ['servicio']
 */
class SearchQueryNormalizer
{
    /**
     * Palabras que no aportan significado para FULLTEXT ni para comparar
     * contra el diccionario de conceptos. Se eliminan SOLO de la lista de
     * 'palabras', nunca de 'consulta_normalizada' (ver docblock de clase).
     */
    private const PALABRAS_VACIAS = [
        'que', 'es', 'un', 'una', 'unos', 'unas', 'el', 'la', 'los', 'las',
        'de', 'del', 'al', 'para', 'con', 'por', 'en', 'y', 'o', 'a',
        'se', 'su', 'sus', 'lo', 'como', 'cual', 'cuales', 'donde',
        'cuando', 'quien', 'quienes', 'este', 'esta', 'estos', 'estas',
    ];

    /**
     * Normaliza una consulta completa.
     *
     * @return array{consulta_normalizada: string, palabras: array<string>}
     */
    public function normalizar(string $consultaOriginal): array
    {
        $texto = TextoNormalizador::normalizar(trim($consultaOriginal));

        // Quitar signos de interrogación, puntuación y cualquier carácter
        // que no sea letra, número o espacio.
        $texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);

        // Colapsar espacios múltiples que pudo dejar el paso anterior.
        $consultaNormalizada = preg_replace('/\s+/', ' ', trim($texto));

        $todasLasPalabras = $consultaNormalizada === '' ? [] : explode(' ', $consultaNormalizada);

        $palabrasRelevantes = array_values(array_filter(
            $todasLasPalabras,
            fn ($palabra) => !in_array($palabra, self::PALABRAS_VACIAS, true) && $palabra !== ''
        ));

        return [
            'consulta_normalizada' => $consultaNormalizada, // CON palabras vacías, para el detector de intención
            'palabras'             => $palabrasRelevantes,   // SIN palabras vacías, para diccionario y FULLTEXT
        ];
    }
}
