<?php

namespace App\Support;

/**
 * Normalización de texto para comparaciones que no deben depender de
 * acentos ni de mayúsculas/minúsculas.
 *
 * Usada por SearchQueryNormalizer, DefinitionExtractorService y
 * LegalDictionaryService para que "Construcción", "construccion" y
 * "CONSTRUCCIÓN" se reconozcan como el mismo término al comparar.
 *
 * La ñ/Ñ se preserva tal cual (no se convierte a n) porque es una letra
 * propia del español con significado distinto ("año" y "ano" son palabras
 * diferentes) — el mismo criterio que ya se usa en SegmentadorPalabrasService
 * para el diccionario de palabras.
 *
 * Nota: SegmentadorPalabrasService tiene su propia copia de esta misma
 * lógica como método privado, porque esa clase ya estaba construida y
 * probada antes de crear esta utilidad compartida. No se modificó ese
 * archivo para evitar tocar algo que ya funciona sin que se haya pedido.
 */
class TextoNormalizador
{
    /**
     * Quita el acento de intensidad de las vocales (á,é,í,ó,ú,ü -> a,e,i,o,u)
     * sin tocar la ñ. No cambia mayúsculas/minúsculas.
     */
    public static function quitarAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        ]);
    }

    /**
     * Normaliza un texto para comparar: minúsculas y sin acentos.
     * "Construcción" y "construccion" devuelven el mismo resultado.
     */
    public static function normalizar(string $texto): string
    {
        return self::quitarAcentos(mb_strtolower($texto, 'UTF-8'));
    }
}
