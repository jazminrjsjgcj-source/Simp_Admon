<?php

namespace App\Services;

use App\Support\TextoNormalizador;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta el diccionario de conceptos jurídico-administrativos de PUNTA
 * (tabla busqueda_diccionario_juridico).
 *
 * Responsabilidad única: dado un término, decir si PUNTA lo reconoce como
 * un concepto conocido y, si es así, en qué tabla propia conviene buscarlo
 * primero. No sabe nada de cómo se detecta la intención de una pregunta
 * (eso es SearchIntentDetector) ni de cómo se arma la respuesta final
 * (eso es FeaturedAnswerService).
 *
 * ── Por qué se cachea la tabla completa ──────────────────────────────
 *
 * Esta tabla casi no cambia: agregar un concepto nuevo es una excepción
 * puntual, no algo que pase seguido. Sin cachear, cada palabra de cada
 * consulta del usuario dispara una consulta idéntica a esta misma tabla
 * pequeña — con una consulta de 4 palabras, son 4 consultas repetidas a
 * los mismos datos sin que nada haya cambiado entre una y otra.
 *
 * Se cachea con Cache::rememberForever: la primera vez que se necesita,
 * se carga la tabla completa (son pocas filas, no pesa nada mantenerla en
 * memoria). Las siguientes veces, sale de caché sin tocar la base de
 * datos. Si se agrega o edita un concepto, hay que invalidar la caché
 * llamando a self::invalidarCache() — todavía no existe una pantalla de
 * administración para este catálogo, así que por ahora la invalidación es
 * manual (correr `php artisan tinker` y llamar al método, o simplemente
 * `php artisan cache:clear` después de correr el seeder de nuevo).
 */
class LegalDictionaryService
{
    private const CACHE_KEY = 'diccionario_juridico_conceptos';
    /**
     * Busca un término en el diccionario de conceptos. La comparación se
     * hace normalizando ambos lados (minúsculas, sin acentos) para que
     * "trámite", "Tramite" y "TRÁMITE" encuentren la misma fila.
     *
     * También reconoce el plural regular en español: si la palabra de la
     * consulta termina en "s" (ej. "requisitos"), también se prueba la
     * forma sin esa "s" (ej. "requisito") contra el diccionario. Los 7
     * conceptos sembrados hoy (servicio, tramite, requisito, costo,
     * plazo, fundamento, agenda) se guardan solo en singular; sin esto,
     * una pregunta tan común como "¿cuáles son los requisitos?" nunca
     * encontraba el concepto "requisito", porque "requisitos" y
     * "requisito" son cadenas distintas para una comparación exacta.
     *
     * Limitación conocida: esta regla solo cubre plurales que se forman
     * agregando "-s" (el patrón de los 7 términos actuales). No cubre
     * plurales en "-es" (ej. "actividad" -> "actividades"). Si se agrega
     * un concepto con ese patrón, esta regla habría que ampliarla.
     *
     * Además del término principal, también compara contra los SINÓNIMOS
     * guardados en la columna `relacionados` de cada fila. Por ejemplo,
     * la fila de "costo" tiene sembrados los sinónimos "pago", "monto",
     * "tarifa", "derecho" y "uma" — antes de este cambio, esos sinónimos
     * se guardaban en la base de datos pero ningún código los leía nunca,
     * así que preguntar "¿cuánto es la tarifa?" nunca activaba el
     * enrutamiento hacia costos, a pesar de que la propia base de datos ya
     * sabía que "tarifa" significa lo mismo que "costo" para estos efectos.
     *
     * Limitación conocida de los sinónimos: solo se comparan como cadena
     * completa contra la palabra o combinación de palabras que llega aquí.
     * Un sinónimo de varias palabras que incluya una preposición ("tiempo
     * de resolucion", sinónimo de "plazo") no va a coincidir, porque
     * SearchQueryNormalizer ya quitó la preposición "de" de la consulta
     * antes de llegar a este método — es la misma limitación de
     * preposiciones que ya está documentada en FeaturedAnswerService.
     *
     * Si la tabla del diccionario todavía no existe (por ejemplo, porque
     * la migración de esta funcionalidad no se ha corrido todavía en este
     * servidor), devuelve null en vez de lanzar un error de SQL — el
     * buscador debe poder seguir funcionando con sus 5 fuentes originales
     * aunque esta capa nueva no esté instalada todavía.
     *
     * @return object|null  Fila de busqueda_diccionario_juridico, o null si
     *                       el término no está catalogado o la tabla no existe.
     */
    public function buscarConcepto(string $termino): ?object
    {
        $normalizado = TextoNormalizador::normalizar(trim($termino));

        if ($normalizado === '') {
            return null;
        }

        $formasBuscadas = $this->formasSingularYPlural($normalizado);

        return $this->obtenerConceptos()
            ->first(function ($fila) use ($formasBuscadas) {
                // 1. Comparación contra el término principal de la fila
                //    (lo que ya hacía esta función antes de este cambio).
                $terminoFila = TextoNormalizador::normalizar($fila->termino);
                if (in_array($terminoFila, $formasBuscadas, true)) {
                    return true;
                }

                // 2. Comparación contra cada sinónimo de 'relacionados'.
                //    Antes de este cambio, esta columna se guardaba pero
                //    nunca se leía — ver el docblock de arriba.
                foreach ($this->obtenerSinonimos($fila) as $sinonimo) {
                    $sinonimoNormalizado = TextoNormalizador::normalizar($sinonimo);
                    if (in_array($sinonimoNormalizado, $formasBuscadas, true)) {
                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * Decodifica la columna `relacionados` de una fila del diccionario
     * (guardada como JSON, ej. '["pago","monto","tarifa"]') a un arreglo
     * plano de PHP.
     *
     * Devuelve un arreglo vacío — nunca null ni un error — si la columna
     * viene vacía o con un JSON inválido, para que buscarConcepto() pueda
     * iterar el resultado sin tener que validar nada antes.
     *
     * @return array<string>
     */
    private function obtenerSinonimos(object $fila): array
    {
        if (empty($fila->relacionados)) {
            return [];
        }

        $decodificado = json_decode($fila->relacionados, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    /**
     * Devuelve la palabra tal cual, y además su forma sin la "s" final
     * cuando termina en "s" — la regla de pluralización más común del
     * español (servicio -> servicios, requisito -> requisitos, costo ->
     * costos). Esto permite que una consulta en plural encuentre un
     * concepto sembrado en singular sin tener que duplicar cada término
     * del catálogo en sus dos formas.
     *
     * Se exige más de 3 letras antes de quitar la "s" para no generar
     * formas sin sentido a partir de palabras muy cortas (por ejemplo,
     * evitar que "es" se reduzca a "e"). Ninguno de los 7 términos
     * actuales del catálogo se ve afectado por esta guarda, porque todos
     * miden 5 letras o más.
     *
     * @return array<string>
     */
    private function formasSingularYPlural(string $palabra): array
    {
        $formas = [$palabra];

        if (str_ends_with($palabra, 's') && mb_strlen($palabra) > 4) {
            $formas[] = mb_substr($palabra, 0, -1);
        }

        return $formas;
    }

    /**
     * Revisa una lista de palabras (ya normalizadas por SearchQueryNormalizer)
     * y devuelve el primer concepto conocido que encuentre. Se usa cuando la
     * consulta completa del usuario no es exactamente un término del
     * diccionario, sino que lo CONTIENE entre otras palabras — por ejemplo,
     * "qué es un servicio" contiene la palabra "servicio".
     */
    public function encontrarConceptoEnPalabras(array $palabras): ?object
    {
        foreach ($palabras as $palabra) {
            $concepto = $this->buscarConcepto($palabra);
            if ($concepto !== null) {
                return $concepto;
            }
        }

        return null;
    }

    /**
     * Todos los conceptos activos, cacheados de forma permanente.
     *
     * Si la tabla todavía no existe (la migración de esta funcionalidad no
     * se ha corrido en este servidor), devuelve una colección vacía en vez
     * de lanzar un error de SQL — el buscador debe poder seguir funcionando
     * con sus 5 fuentes originales aunque esta capa nueva no esté instalada.
     *
     * ── Por qué se cachea un arreglo plano y no la Collection directa ───
     *
     * DB::table(...)->get() devuelve una Collection que por dentro guarda
     * objetos stdClass (uno por fila). Guardar ese árbol de objetos
     * directamente en Cache::rememberForever() significa que Laravel tiene
     * que serializar y luego reconstruir objetos completos cada vez que se
     * lee la caché — un proceso frágil que puede fallar a medias y producir
     * un error "__PHP_Incomplete_Class" si algo cambia entre el momento en
     * que se guardó y el momento en que se intenta reconstruir (una
     * actualización de dependencias, una diferencia de carga de clases,
     * etc.).
     *
     * Aquí cada fila se convierte a un arreglo asociativo simple ((array)
     * $fila) ANTES de guardarla en caché. Un arreglo de texto y números no
     * tiene ninguna clase que reconstruir — es imposible que produzca ese
     * error, sin importar la causa exacta. Los objetos stdClass se
     * reconstruyen después, en memoria, cada vez que se llama a este
     * método — esa reconstrucción nunca pasa por la caché, así que nunca
     * puede fallar de esta forma.
     */
    private function obtenerConceptos(): \Illuminate\Support\Collection
    {
        $datosPlanos = Cache::rememberForever(self::CACHE_KEY, function () {
            if (!Schema::hasTable('busqueda_diccionario_juridico')) {
                return [];
            }

            return DB::table('busqueda_diccionario_juridico')
                ->where('activo', true)
                ->get()
                ->map(fn ($fila) => (array) $fila)
                ->all();
        });

        // Reconstruir los objetos en memoria, fuera de la caché. Esto nunca
        // puede fallar: (object) sobre un arreglo simple siempre produce un
        // stdClass válido, porque stdClass es una clase nativa de PHP que
        // siempre está disponible.
        return collect($datosPlanos)->map(fn ($fila) => (object) $fila);
    }

    /**
     * Invalida la caché del diccionario. Debe llamarse después de agregar,
     * editar o desactivar un concepto en busqueda_diccionario_juridico —
     * por ejemplo, después de correr DiccionarioJuridicoSeeder de nuevo.
     */
    public static function invalidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
