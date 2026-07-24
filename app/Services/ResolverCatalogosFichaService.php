<?php

namespace App\Services;

use App\Models\Dependencia;
use App\Models\UnidadAdministrativa;
use Illuminate\Support\Str;

/**
 * Convierte los textos de una ficha (p. ej. el módulo "DEPARTAMENTO DE
 * RECLUTAMIENTO") en los IDs reales de los catálogos (dependencia_id, unidad_id),
 * para poder precargar los selects del formulario de alta.
 *
 * Regla: SOLO casa por coincidencia EXACTA normalizada (sin acentos, minúsculas,
 * espacios colapsados). Si no hay match, o hay más de uno, devuelve null: mejor
 * dejar el select en blanco que precargar una dependencia equivocada en un
 * registro oficial.
 *
 * "Departamento de Reclutamiento" es un nivel "departamento", así que se busca
 * primero como UNIDAD administrativa (y de ella se toma su dependencia); si no
 * aparece como unidad, se intenta como dependencia directa.
 */
class ResolverCatalogosFichaService
{
    /**
     * @param  array<string,mixed>  $ficha  salida de LectorFichaTramiteService
     * @return array{dependencia_id: int|null, unidad_id: int|null}
     */
    public function resolver(array $ficha): array
    {
        $vacio = ['dependencia_id' => null, 'unidad_id' => null];

        $nombreModulo = $ficha['modulo']['nombre'] ?? null;
        if (empty($nombreModulo)) {
            return $vacio;
        }

        $objetivo = $this->normalizar($nombreModulo);

        // 1) ¿Es una unidad administrativa? Si sí, tomamos su dependencia.
        $unidad = $this->unico(
            UnidadAdministrativa::query()->get(['id', 'nombre', 'dependencia_id']),
            $objetivo
        );
        if ($unidad !== null) {
            return [
                'dependencia_id' => $unidad->dependencia_id,
                'unidad_id'      => $unidad->id,
            ];
        }

        // 2) ¿Es una dependencia directa?
        $dependencia = $this->unico(
            Dependencia::query()->get(['id', 'nombre']),
            $objetivo
        );
        if ($dependencia !== null) {
            return [
                'dependencia_id' => $dependencia->id,
                'unidad_id'      => null,
            ];
        }

        return $vacio;
    }

    /**
     * Devuelve el ÚNICO registro cuyo nombre normalizado coincide con el objetivo,
     * o null si no hay ninguno o hay más de uno (ambigüedad = no adivinar).
     *
     * @param  \Illuminate\Support\Collection  $registros
     */
    private function unico($registros, string $objetivo)
    {
        $coincidencias = $registros->filter(
            fn ($r) => $this->normalizar((string) $r->nombre) === $objetivo
        )->values();

        return $coincidencias->count() === 1 ? $coincidencias->first() : null;
    }

    /** Sin acentos, minúsculas, espacios colapsados y recortado. */
    private function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strtolower(Str::ascii($texto))));
    }
}
