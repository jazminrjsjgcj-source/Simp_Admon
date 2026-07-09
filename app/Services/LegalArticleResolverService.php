<?php

namespace App\Services;

use App\Models\RegulacionNodo;
use Illuminate\Support\Collection;

/**
 * Cuando un resultado de búsqueda cae en una fracción o un inciso (no en el
 * artículo completo), el usuario necesita ver el artículo entero con
 * contexto — no un fragmento suelto sin saber de qué parte del reglamento
 * viene. Este servicio resuelve esa jerarquía usando `parent_id`, que ya
 * existe en regulacion_nodos desde que se construyó el árbol del articulado.
 *
 * Responsabilidad única: dado un nodo cualquiera, encontrar su artículo
 * contenedor y traerlo completo con sus hijos en orden. No sabe nada de
 * cómo se buscó ese nodo ni de cómo se va a mostrar.
 */
class LegalArticleResolverService
{
    /**
     * Límite de saltos hacia arriba en la jerarquía antes de rendirse.
     * El árbol real nunca debería tener más de 4-5 niveles (Título >
     * Capítulo > Sección > Artículo > Fracción > Inciso), así que un
     * límite de 10 es una red de seguridad contra un ciclo de datos
     * corrupto (parent_id apuntando circularmente), no un caso esperado.
     */
    private const LIMITE_SALTOS = 10;

    /**
     * A partir de cualquier nodo, sube por parent_id hasta encontrar el
     * nodo tipo 'articulo' que lo contiene. Si el nodo mismo ya es un
     * artículo, se devuelve tal cual sin subir.
     *
     * @return RegulacionNodo|null  El artículo contenedor, o null si no se
     *                              encontró ninguno en la cadena (por
     *                              ejemplo, si el nodo es un título o
     *                              capítulo suelto sin artículo asociado).
     */
    public function resolverArticuloPadre(RegulacionNodo $nodo): ?RegulacionNodo
    {
        $actual = $nodo;
        $saltos = 0;

        while ($actual->tipo !== RegulacionNodo::TIPO_ARTICULO) {
            if ($actual->parent_id === null || $saltos >= self::LIMITE_SALTOS) {
                return null; // se llegó a la raíz sin encontrar un artículo
            }

            $actual = RegulacionNodo::find($actual->parent_id);
            if ($actual === null) {
                return null; // parent_id roto: el padre referenciado no existe
            }

            $saltos++;
        }

        return $actual;
    }

    /**
     * Trae un artículo completo: el nodo del artículo más todos sus hijos
     * (fracciones e incisos) en el orden correcto de lectura.
     *
     * @return array{articulo: RegulacionNodo, hijos: Collection<RegulacionNodo>}
     */
    public function obtenerArticuloCompleto(RegulacionNodo $articulo): array
    {
        return [
            'articulo' => $articulo,
            'hijos'    => $this->hijosEnOrden($articulo),
        ];
    }

    /**
     * Todos los descendientes de un nodo, recursivamente, ordenados para
     * lectura: cada fracción seguida inmediatamente de sus propios incisos,
     * antes de pasar a la siguiente fracción.
     */
    private function hijosEnOrden(RegulacionNodo $nodo): Collection
    {
        $directos = RegulacionNodo::where('parent_id', $nodo->id)
            ->orderBy('orden')
            ->get();

        $resultado = collect();
        foreach ($directos as $hijo) {
            $resultado->push($hijo);
            $resultado = $resultado->merge($this->hijosEnOrden($hijo));
        }

        return $resultado;
    }
}
