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
        // ── Artículos, preposiciones y conjunciones ──
        'que', 'es', 'un', 'una', 'unos', 'unas', 'el', 'la', 'los', 'las',
        'de', 'del', 'al', 'para', 'con', 'por', 'en', 'y', 'o', 'a',
        'se', 'su', 'sus', 'lo', 'como', 'cual', 'cuales', 'donde',
        'cuando', 'quien', 'quienes', 'este', 'esta', 'estos', 'estas',

        // ══════════════════════════════════════════════════════════════════════
        // LOS VERBOS DE ACCIÓN: LA TRAMPA
        // ══════════════════════════════════════════════════════════════════════
        //
        // Esta sección existe por un descubrimiento que costó seis intentos, y conviene entenderlo
        // bien porque explica CÓMO hay que ampliar esta lista.
        //
        // ── El caso ──
        //
        // Alguien pregunta: "cuáles y cómo se calculan los cobros sobre espectáculos públicos"
        //
        // El artículo que responde es el 65 de la Ley de Hacienda:
        //
        //     "Los sujetos PAGARÁN por concepto de este IMPUESTO, el 8% del monto total de los
        //      INGRESOS obtenidos."
        //
        // Frecuencias reales de las cuatro palabras en la Ley de Hacienda de La Paz:
        //
        //     calculan      →    7 nodos   ← recargos, notarios, adquisición de inmuebles
        //     espectaculos  →   28 nodos   ← EL TEMA
        //     cobros        →   31 nodos
        //     publicos      →  113 nodos   ← ruido puro (vía pública, orden público...)
        //
        // ── El error que se cometió ──
        //
        // Se intentó ordenar las palabras por RAREZA ("cuanto más rara, más informativa" — el IDF
        // de toda la vida) y conservar las más raras.
        //
        // Y `calculan` es LA MÁS RARA DE LAS CUATRO. Con 7 apariciones, gana a `espectaculos`,
        // que tiene 28.
        //
        // Resultado: el buscador conservaba `calculan` y soltaba `espectaculos`. Devolvía siete
        // artículos sobre recargos y notarios. Ninguno de espectáculos.
        //
        // ── POR QUÉ LA RAREZA NO SIRVE AQUÍ ──
        //
        // En un buscador web, una palabra rara es una palabra específica. En una LEY, una palabra
        // puede ser rara por dos motivos completamente distintos:
        //
        //     · Es específica del TEMA          → "espectaculos" (28)   ← SIRVE
        //     · Es del habla del CIUDADANO,      → "calculan" (7)        ← NO SIRVE
        //       no de la ley
        //
        // Y la frecuencia NO LOS DISTINGUE. De hecho, la segunda suele ser MÁS rara que la
        // primera — precisamente porque la ley no la usa.
        //
        // ── LO QUE SÍ LOS DISTINGUE ──
        //
        //     "espectáculos" es un SUSTANTIVO.
        //     "calculan" es un VERBO.
        //
        // Y ahí está el patrón, en toda su claridad:
        //
        //     · El ciudadano describe la ACCIÓN con SUS verbos:  calculan, cobran, pagan, cuesta.
        //     · La ley usa OTROS verbos:                          pagarán, cubrirán, causarán.
        //     · Pero el SUSTANTIVO DEL TEMA ES EL MISMO:          espectáculos, ambulantes, basura.
        //
        //     LOS SUSTANTIVOS SON EL PUENTE ENTRE EL CIUDADANO Y LA LEY.
        //     LOS VERBOS SON LA TRAMPA.
        //
        // ── CÓMO AMPLIAR ESTA LISTA ──
        //
        // No añadas palabras "que estorban". Añade VERBOS con los que un ciudadano describe una
        // acción administrativa. Si dudas, pregúntate:
        //
        //     ¿Escribiría la ley esta palabra? Si la respuesta es "usaría otra", va a la lista.
        //
        // Y ojo: esta lista NUNCA estará completa. Mañana alguien preguntará "¿cómo se determina
        // el monto?" y "determina" no estará. Es un parche que cubre el 90% de los casos.
        //
        // El arreglo de fondo —traducir la pregunta al vocabulario de la ley— es otra cosa, y va
        // aparte.
        //
        // ── Y por qué esto NO se lleva la 'consulta_normalizada' ──
        //
        // Solo se quitan de la lista de 'palabras' (la que alimenta al full-text). La consulta
        // normalizada CONSERVA los verbos enteros, porque el detector de intención los necesita:
        // es justamente por el "cuánto" y el "calculan" por lo que sabe que están preguntando un
        // COSTO y no una definición.
        // ══════════════════════════════════════════════════════════════════════

        // Verbos con los que la gente pregunta por un CÁLCULO o un COBRO.
        // La ley dice "pagarán", "cubrirán", "se causará", "el impuesto será".
        'calcula', 'calculan', 'calcular', 'calculo', 'cálculo', 'calculos', 'cálculos',
        'cobro', 'cobros', 'cobra', 'cobran', 'cobrar', 'cobrado',
        'tarifa', 'tarifas', 'monto', 'montos', 'importe', 'importes',
        'determina', 'determinan', 'determinar', 'aplica', 'aplican', 'aplicar',

        // Verbos con los que la gente pregunta por un TRÁMITE o un PROCESO.
        // La ley dice "deberá presentar", "se sujetará", "está obligado a".
        'tramitar', 'tramite', 'trámite', 'hacer', 'hago', 'hace', 'realizar', 'realiza',
        'obtener', 'obtengo', 'obtiene', 'sacar', 'saco', 'saca', 'conseguir', 'consigo',
        'solicitar', 'solicito', 'solicita', 'pedir', 'pido', 'pide',
        'presentar', 'presento', 'presenta', 'entregar', 'entrego', 'entrega',

        // Verbos con los que la gente pregunta por una DURACIÓN o un PLAZO.
        // La ley dice "tendrá vigencia de", "el plazo será de", "a más tardar".
        'dura', 'duran', 'durar', 'duracion', 'duración', 'tarda', 'tardan', 'tardar',
        'vence', 'vencen', 'vencer', 'caduca', 'caducan',

        // ── PALABRAS DE PREGUNTA ──
        //
        // Estas faltaban, y eran la mitad del problema.
        //
        // El normalizador se diseñó para reconocer CONCEPTOS ("qué es un servicio"), no para
        // digerir PREGUNTAS COMPLETAS ("cuánto paga un semifijo por la basura"). Y un ciudadano
        // no escribe conceptos: escribe preguntas.
        //
        // El resultado era este: la consulta full-text exige que TODAS las palabras aparezcan en
        // el artículo (' & ' en tsquery). Y ningún artículo de la Ley de Hacienda contiene la
        // palabra "cuanto" ni la palabra "paga". La ley dice "cuota", "cubrirán", "el pago del
        // derecho será".
        //
        // Así que la búsqueda devolvía CERO resultados — no porque la ley no lo dijera, sino
        // porque el ciudadano no había adivinado las palabras exactas de la ley. Y si las
        // hubiera adivinado, no habría necesitado preguntar.
        //
        // Estas palabras describen la FORMA de la pregunta, no su CONTENIDO. Quitarlas no pierde
        // información: la deja al descubierto.
        //
        //     "cuanto paga un semifijo en basura"  →  ['semifijo', 'basura']
        //
        // OJO: se quitan solo de la lista 'palabras' (la que alimenta al full-text). La
        // 'consulta_normalizada' las conserva ENTERAS, porque el detector de intención las
        // necesita: es justamente por el "cuánto" por lo que sabe que preguntan un COSTO.
        'cuanto', 'cuanta', 'cuantos', 'cuantas', 'cuánto', 'cuánta', 'cuántos', 'cuántas',
        'cuesta', 'cuestan', 'cueste',
        'paga', 'pagan', 'pago', 'pagar', 'pagas', 'pagaria', 'pagaría',
        'cobra', 'cobran', 'cobro', 'cobrar',
        'necesito', 'necesita', 'necesitan', 'requiere', 'requieren',
        'debo', 'debe', 'deben', 'tengo', 'tiene', 'tienen', 'hay',
        'puedo', 'puede', 'pueden', 'sirve', 'sirven',
        'saber', 'quiero', 'quisiera', 'busco', 'buscar',
        'dime', 'dice', 'sobre', 'acerca', 'respecto',
        'me', 'mi', 'mis', 'te', 'tu', 'tus', 'nos', 'les', 'le',
        'soy', 'eres', 'somos', 'son', 'estoy', 'esta', 'estan', 'están',
        'si', 'no', 'mas', 'más', 'muy', 'todo', 'toda', 'todos', 'todas',
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
