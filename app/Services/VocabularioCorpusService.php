<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ¿Esta palabra aparece en las regulaciones cargadas, y en cuántos artículos?
 *
 * Es una clase aparte porque la usan dos servicios: BuscadorService, para descartar
 * palabras demasiado comunes que no acotan la búsqueda, y TesauroService, para
 * descartar sinónimos que no existen en ninguna regulación cargada. El buscador ya
 * inyecta el tesauro, así que meter esto dentro de él crearía una dependencia
 * circular.
 *
 * Dos decisiones de medición que no son obvias:
 *
 * 1. Se cuentan PALABRAS, no raíces. Contar con to_tsquery pasaría la palabra por el
 *    stemmer, y en español raíces distintas se juntan: "casa" y "caso" comparten la
 *    raíz "cas", así que "casa" salía en 73 artículos en vez de 16 y el filtro la
 *    descartaba por común. La búsqueda sí debe usar raíces —es lo que hace que
 *    "predio" encuentre "predios"—, pero la medición no. De ahí la expresión regular
 *    anclada al principio de palabra (\m), que conserva los plurales sin stemmer.
 *
 * 2. La frecuencia se mide solo sobre el TEXTO, aunque la búsqueda mire texto y
 *    contexto. Al contar el contexto, todos los artículos de un capítulo "dicen" su
 *    título y las frecuencias se disparan: "patrimonio" pasa del 0,2% al 13,9% y el
 *    filtro descarta justo la palabra clave. Además la frecuencia no distingue una
 *    palabra dispersa por toda la ley (ruido) de una concentrada en un capítulo (la
 *    respuesta): el filtro decide con el texto, la búsqueda usa el contexto.
 */
class VocabularioCorpusService
{
    /**
     * Menos de tres letras no se cuenta: el buscador añade ':*' a cada palabra, y un
     * prefijo de dos letras casa con media ley.
     */
    private const LARGO_MINIMO = 3;

    /**
     * ¿Existe la palabra en alguna regulación cargada?
     *
     * Es la pregunta del tesauro: no le importa si es común o rara, solo si existe.
     * Un sinónimo que no aparece en ningún artículo no puede encontrar nada.
     */
    public function existe(string $palabra): bool
    {
        return $this->frecuencia($palabra) > 0;
    }

    /**
     * En cuántos nodos del articulado aparece la palabra.
     *
     * Se cachea una hora: el corpus no cambia cada minuto y sin caché sería una
     * consulta por cada palabra de cada búsqueda. La clave lleva versión ('v2')
     * porque la forma de contar cambió; sin versionarla, las frecuencias viejas
     * seguirían vivas una hora después de desplegar.
     */
    public function frecuencia(string $palabra): int
    {
        $limpia = $this->limpiar($palabra);

        if (mb_strlen($limpia) < self::LARGO_MINIMO) {
            return 0;
        }

        return Cache::remember(
            'buscador:frecuencia:v2:' . md5($limpia),
            now()->addHour(),
            fn () => (int) DB::table('regulacion_nodos')
                ->whereNull('deleted_at')
                // El texto de la ley lleva tildes y la palabra llega sin ellas, de ahí
                // unaccent(). El ancla \m exige principio de palabra: hace que "casa"
                // cuente "casas" pero no "caso".
                ->whereRaw("unaccent(coalesce(texto, '')) ~* ('\\m' || ?)", [$limpia])
                ->count()
        );
    }

    /**
     * Deja la palabra en algo que se pueda meter en una expresión regular sin riesgo.
     *
     * Quita los operadores de tsquery y los metacaracteres de regex: sin esto, una
     * palabra escrita por el ciudadano —o una entrada del tesauro puesta a mano—
     * podría romper la consulta o convertirse en un patrón que case con media ley.
     */
    private function limpiar(string $palabra): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}\-]/u', '', $palabra));
    }
}
