<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Arma la "respuesta destacada" que se muestra arriba de los resultados
 * normales del buscador, cuando la intención de la consulta es clara y
 * existe una fuente legal real que la responda.
 *
 * Orquesta, en este orden:
 *   1. SearchIntentDetector   -> ¿qué quiere saber el usuario?
 *   2. LegalDictionaryService -> ¿alguna palabra de la consulta es un
 *                                 concepto conocido por PUNTA?
 *   3. definiciones_legales   -> ¿existe una definición real extraída de
 *                                 alguna regulación ya cargada?
 *
 * Regla de seguridad que este servicio respeta siempre (sección 11 de la
 * especificación del buscador robusto): "si no hay fuente suficiente, el
 * sistema debe decir que no encontró fundamento claro". Este servicio
 * NUNCA inventa ni completa una definición — si no hay una fila real en
 * definiciones_legales, devuelve null y el buscador sigue con los
 * resultados normales de FULLTEXT sin mostrar ninguna respuesta destacada.
 */
class FeaturedAnswerService
{
    public function __construct(
        private SearchIntentDetector $detector,
        private LegalDictionaryService $diccionario,
    ) {}

    /**
     * Intenta construir una respuesta destacada para la consulta dada.
     *
     * Busca en DOS fuentes, en orden de prioridad:
     *
     *   1. PRIMER INTENTO — Diccionario curado (busqueda_diccionario_juridico,
     *      7 conceptos sembrados manualmente). Si el término está aquí, la
     *      confianza es "alta" porque alguien lo curó a propósito. Solo aplica
     *      a términos que están en el diccionario AND cuyo tipo_concepto es
     *      'concepto' — los tipo 'dato' (costo, plazo, requisito) se usan para
     *      enrutar la búsqueda, no para respuestas destacadas.
     *
     *   2. SEGUNDO INTENTO — Definiciones extraídas automáticamente
     *      (definiciones_legales, 37+ entradas pobladas por
     *      DefinitionExtractorService al estructurar regulaciones). Se busca
     *      directamente en esta tabla sin exigir que el término pase primero
     *      por el diccionario. La confianza es "media" porque la extracción
     *      fue automática (basada en patrones de texto, no curada por un
     *      humano), pero sigue siendo una fuente legal real — no es texto
     *      inventado.
     *
     *   ANTES, solo existía el primer intento: el diccionario de 7 conceptos
     *   era un filtro obligatorio previo a consultar las 37+ definiciones.
     *   Esto descartaba el 95% de las definiciones que el sistema ya tenía
     *   disponibles (como "certeza jurídica", "sujeto obligado", "propuesta
     *   regulatoria"), simplemente porque nadie las había agregado manualmente
     *   al diccionario de 7 entradas.
     *
     * Para conceptos de VARIAS PALABRAS ("certeza jurídica", "sujeto
     * obligado", "costo burocrático"), se construyen todas las combinaciones
     * posibles de palabras consecutivas de la consulta y se prueban contra
     * ambas fuentes. La versión anterior probaba cada palabra por separado,
     * así que nunca podía encontrar un concepto de dos o más palabras.
     *
     * @param  string  $consultaNormalizada  Texto completo normalizado,
     *                  CON palabras vacías (para que el detector de
     *                  intención pueda reconocer frases como "que es").
     * @param  array<string>  $palabras  Lista de palabras SIN vacías, para
     *                  comparar contra definiciones.
     *
     * @return array|null  La respuesta destacada, o null si no se pudo
     *                      construir una con confianza suficiente.
     */
    public function construir(string $consultaNormalizada, array $palabras): ?array
    {
        $intencion = $this->detector->detectar($consultaNormalizada);

        if ($intencion !== SearchIntentDetector::DEFINICION) {
            // La Fase 1 solo arma respuesta destacada para definiciones.
            // Las demás intenciones (costo, requisitos, fundamento) usan
            // el diccionario para PRIORIZAR fuentes dentro de la búsqueda
            // normal, no para mostrar una respuesta destacada todavía —
            // eso depende de los índices especializados de la Fase 3.
            return null;
        }

        // Construir todas las combinaciones posibles de palabras consecutivas.
        // Para ['certeza', 'juridica']:
        //   → 'certeza juridica' (2 palabras juntas)
        //   → 'certeza' (palabra sola)
        //   → 'juridica' (palabra sola)
        // Se prueban de la más larga a la más corta: un match de 2 palabras
        // es más específico y confiable que uno de 1 sola.
        $combinaciones = $this->construirCombinaciones($palabras);

        // ── Primer intento: diccionario curado ──────────────────────────
        foreach ($combinaciones as $combo) {
            $concepto = $this->diccionario->buscarConcepto($combo);
            if ($concepto !== null && $concepto->tipo_concepto === 'concepto') {
                $definiciones = $this->buscarDefiniciones($concepto->termino);
                if ($definiciones->isNotEmpty()) {
                    return $this->armarRespuesta($concepto->termino, $definiciones, 'alta');
                }
            }
        }

        // ── Segundo intento: directamente en definiciones_legales ────────
        // El diccionario no reconoció ningún concepto, pero las definiciones
        // extraídas automáticamente podrían tener el término. Esto cubre los
        // 35+ conceptos de la LNETB (y de cualquier otra regulación
        // estructurada) que nadie agregó manualmente al diccionario de 7.
        foreach ($combinaciones as $combo) {
            $definiciones = $this->buscarDefiniciones($combo);
            if ($definiciones->isNotEmpty()) {
                return $this->armarRespuesta($combo, $definiciones, 'media');
            }
        }

        return null;
    }

    /**
     * Construye todas las combinaciones posibles de palabras CONSECUTIVAS
     * de la consulta, ordenadas de la más larga a la más corta.
     *
     * Para ['certeza', 'juridica', 'licencia']:
     *   → 'certeza juridica licencia' (3 palabras)
     *   → 'certeza juridica' (2 palabras, empezando por pos 0)
     *   → 'juridica licencia' (2 palabras, empezando por pos 1)
     *   → 'certeza' (1 palabra)
     *   → 'juridica' (1 palabra)
     *   → 'licencia' (1 palabra)
     *
     * Solo se generan combinaciones de hasta 4 palabras consecutivas:
     * los términos legales más largos que hemos visto en la práctica son
     * de 4 palabras ("Modelo Nacional para Eliminar Trámites Burocráticos"
     * son 6, pero su forma normalizada sin vacías sería "modelo nacional
     * eliminar tramites burocraticos" — 5 palabras — de las cuales solo
     * las primeras 4 suelen ser suficientes para un match único).
     *
     * @return array<string>
     */
    private function construirCombinaciones(array $palabras): array
    {
        $combinaciones = [];
        $total = count($palabras);
        $maxLargo = min(4, $total);

        // De la combinación más larga a la más corta.
        for ($largo = $maxLargo; $largo >= 1; $largo--) {
            for ($inicio = 0; $inicio <= $total - $largo; $inicio++) {
                $combinaciones[] = implode(' ', array_slice($palabras, $inicio, $largo));
            }
        }

        return $combinaciones;
    }

    /**
     * Arma el arreglo de respuesta destacada a partir de las definiciones
     * encontradas. Se extrajo como método separado porque se llama desde
     * dos puntos distintos de construir() (primer y segundo intento) y el
     * formato de salida es idéntico en ambos casos — solo cambia el nivel
     * de confianza.
     */
    private function armarRespuesta(string $termino, \Illuminate\Support\Collection $definiciones, string $confianza): array
    {
        $principal = $definiciones->first();
        $adicionales = $definiciones->slice(1);

        return [
            'termino'              => $termino,
            'definicion'           => $principal->definicion,
            'fuente'               => $principal->fuente,
            'articulo'             => $principal->articulo,
            'fraccion'             => $principal->fraccion,
            'regulacion_id'        => $principal->regulacion_id,
            'confianza'            => $confianza,
            'motivo'               => $confianza === 'alta'
                ? 'Definición legal encontrada en el diccionario curado y en una regulación cargada.'
                : 'Definición legal encontrada automáticamente en el articulado de una regulación cargada.',
            'definiciones_adicionales' => $adicionales->map(fn ($d) => [
                'fuente'        => $d->fuente,
                'articulo'      => $d->articulo,
                'fraccion'      => $d->fraccion,
                'regulacion_id' => $d->regulacion_id,
            ])->values()->toArray(),
        ];
    }

    /**
     * Busca en definiciones_legales todas las filas activas para un
     * término, comparando de forma normalizada (sin acentos, minúsculas)
     * para que "trámite" encuentre también "Trámite" o "TRAMITE".
     *
     * También reconoce el plural regular en español (terminado en -s),
     * con el mismo criterio documentado en LegalDictionaryService:
     * si la consulta dice "regulaciones", también busca "regulacion"
     * (la forma sin la s final) contra los términos guardados.
     *
     * Si la tabla todavía no existe (la migración de esta funcionalidad no
     * se ha corrido en este servidor), devuelve una colección vacía en vez
     * de lanzar un error de SQL.
     */
    private function buscarDefiniciones(string $termino): \Illuminate\Support\Collection
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('definiciones_legales')) {
            return collect();
        }

        $normalizado = \App\Support\TextoNormalizador::normalizar($termino);
        $formas = [$normalizado];
        if (str_ends_with($normalizado, 's') && mb_strlen($normalizado) > 4) {
            $formas[] = mb_substr($normalizado, 0, -1);
        }

        return DB::table('definiciones_legales')
            ->where('activo', true)
            ->get()
            ->filter(function ($fila) use ($formas) {
                $terminoFila = \App\Support\TextoNormalizador::normalizar($fila->termino);
                return in_array($terminoFila, $formas, true);
            })
            ->sortByDesc('created_at')
            ->values();
    }
}
