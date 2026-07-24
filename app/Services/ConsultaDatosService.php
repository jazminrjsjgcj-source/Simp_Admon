<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ejecuta una consulta a los datos a partir de una "receta" estructurada, SIEMPRE
 * validada contra la lista blanca de config/consulta_datos.php.
 *
 * Este servicio es la parte SEGURA del módulo de preguntas en lenguaje natural:
 * la IA (en otra etapa) solo produce la receta; aquí no hay IA, no hay SQL escrito
 * a mano y no se toca nada fuera de lo declarado en el config. Solo lee.
 *
 * Forma de la receta (la produce la IA o un test):
 *   [
 *     'entidad' => 'tramites',
 *     'metrica' => 'conteo' | 'lista' | 'agrupar',
 *     'filtros' => ['estatus' => 'borrador'],   // opcional
 *     'agrupar' => 'dependencia',                // requerido si metrica = agrupar
 *   ]
 *
 * Devuelve un arreglo con el resultado YA CALCULADO desde la base de datos.
 */
class ConsultaDatosService
{
    /**
     * @throws InvalidArgumentException si la receta pide algo fuera de la lista blanca.
     */
    public function ejecutar(array $receta): array
    {
        $cfg = config('consulta_datos');

        // 1) Entidad permitida.
        $entidadKey = (string) ($receta['entidad'] ?? '');
        $entidad = $cfg['entidades'][$entidadKey] ?? null;
        if ($entidad === null) {
            throw new InvalidArgumentException("Entidad no permitida: '{$entidadKey}'.");
        }

        // 2) Métrica permitida.
        $metrica = (string) ($receta['metrica'] ?? 'conteo');
        if (! in_array($metrica, $cfg['metricas'], true)) {
            throw new InvalidArgumentException("Métrica no permitida: '{$metrica}'.");
        }

        // 3) Consulta base sobre el modelo declarado (respeta soft-deletes por defecto).
        /** @var \Illuminate\Database\Eloquent\Model $modelo */
        $modelo = $entidad['modelo'];
        $query  = $modelo::query();
        $tablaBase = (new $modelo)->getTable();

        // 4) Filtros: cada uno debe estar declarado, y su valor debe ser válido.
        foreach ((array) ($receta['filtros'] ?? []) as $clave => $valor) {
            $def = $entidad['filtros'][$clave] ?? null;
            if ($def === null) {
                throw new InvalidArgumentException("Filtro no permitido: '{$clave}'.");
            }
            // Si el filtro declara una lista de valores, el valor debe estar en ella.
            // Lista vacía = se acepta cualquier valor (filtro abierto declarado a propósito).
            if (! empty($def['valores'])
                && ! in_array((string) $valor, array_map('strval', $def['valores']), true)) {
                throw new InvalidArgumentException("Valor no permitido para '{$clave}': '{$valor}'.");
            }
            $query->where($tablaBase . '.' . $def['columna'], $valor);
        }

        // 5) Ejecutar según la métrica.
        return match ($metrica) {
            'conteo'  => $this->conteo($query, $entidad, $receta),
            'lista'   => $this->lista($query, $entidad, $cfg, $receta),
            'agrupar' => $this->agrupar($query, $entidad, $modelo, $tablaBase, $receta),
        };
    }

    /** Un número: cuántas filas cumplen los filtros. */
    private function conteo($query, array $entidad, array $receta): array
    {
        return [
            'tipo'    => 'conteo',
            'entidad' => $entidad['label'],
            'filtros' => (array) ($receta['filtros'] ?? []),
            'total'   => (int) $query->count(),
        ];
    }

    /** Filas (id + nombre), con un tope duro para no traer la tabla entera. */
    private function lista($query, array $entidad, array $cfg, array $receta): array
    {
        $limite = (int) ($cfg['limite_lista'] ?? 50);
        $columnaNombre = $entidad['columna_nombre'];

        $total = (int) $query->count();
        $filas = $query->limit($limite)
            ->get(['id', $columnaNombre])
            ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->{$columnaNombre}])
            ->all();

        return [
            'tipo'    => 'lista',
            'entidad' => $entidad['label'],
            'filtros' => (array) ($receta['filtros'] ?? []),
            'total'   => $total,
            'limite'  => $limite,
            'filas'   => $filas,
        ];
    }

    /** Conteo por dimensión: por columna, por relación (join), o por mes. */
    private function agrupar($query, array $entidad, string $modelo, string $tablaBase, array $receta): array
    {
        $dimKey = (string) ($receta['agrupar'] ?? '');
        $dim = $entidad['dimensiones'][$dimKey] ?? null;
        if ($dim === null) {
            throw new InvalidArgumentException("No se puede agrupar por: '{$dimKey}'.");
        }

        // SQL (una sola cadena) de la columna por la que se agrupa. Todos los
        // nombres vienen del config (lista blanca), nunca del usuario: sin inyección.
        $grupoSql = match ($dim['tipo']) {
            'columna'  => $tablaBase . '.' . $dim['columna'],
            'mes'      => "to_char({$tablaBase}.{$dim['columna']}, 'YYYY-MM')",
            'relacion' => $this->columnaDeRelacion($query, $modelo, $dim['relacion'], $dim['columna']),
            default    => throw new InvalidArgumentException("Tipo de dimensión desconocido: '{$dim['tipo']}'."),
        };

        // El SQL de agrupación se compone con nombres del config, nunca con datos del
        // usuario. Aun así se valida su forma antes de interpolarlo: es la única
        // interpolación de SQL del servicio y no debe poder degradarse sin que salte.
        if (! preg_match('/^[a-z0-9_.()\s,\'-]+$/i', $grupoSql)) {
            throw new InvalidArgumentException('Expresión de agrupación no válida.');
        }

        $filas = $query
            ->select(DB::raw("{$grupoSql} as grupo"), DB::raw('count(*) as total'))
            ->groupBy(DB::raw($grupoSql))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['grupo' => $r->grupo ?? '(sin dato)', 'total' => (int) $r->total])
            ->all();

        return [
            'tipo'      => 'agrupar',
            'entidad'   => $entidad['label'],
            'dimension' => $dimKey,
            'filtros'   => (array) ($receta['filtros'] ?? []),
            'grupos'    => $filas,
        ];
    }

    /**
     * Resuelve el JOIN de una relación belongsTo (una o varias, separadas por punto,
     * p. ej. "tramite.dependencia") y devuelve la columna calificada por la que
     * agrupar. Las llaves y tablas se sacan de la relación de Eloquent, NO se
     * escriben a mano: si mañana cambia una FK, esto se ajusta solo.
     */
    private function columnaDeRelacion($query, string $modeloClass, string $rutaRelacion, string $columna): string
    {
        $instancia   = new $modeloClass;
        $tablaActual = $instancia->getTable();

        foreach (explode('.', $rutaRelacion) as $nombreRelacion) {
            $relacion = $instancia->{$nombreRelacion}();
            if (! $relacion instanceof BelongsTo) {
                throw new InvalidArgumentException(
                    "Solo se puede agrupar por relaciones belongsTo; '{$nombreRelacion}' no lo es."
                );
            }

            $relacionado = $relacion->getRelated();
            $tablaRel    = $relacionado->getTable();

            // belongsTo: la FK vive en la tabla actual; la owner key, en la relacionada.
            $query->leftJoin(
                $tablaRel,
                $tablaActual . '.' . $relacion->getForeignKeyName(),
                '=',
                $tablaRel . '.' . $relacion->getOwnerKeyName()
            );

            $instancia   = $relacionado;
            $tablaActual = $tablaRel;
        }

        return $tablaActual . '.' . $columna;
    }
}
