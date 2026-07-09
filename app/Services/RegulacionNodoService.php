<?php

namespace App\Services;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operaciones del árbol de nodos de una regulación (editor del Jurídico).
 *
 * Centraliza la lógica de manipulación —crear, editar, mover, reordenar,
 * derogar— para que el controlador quede delgado y las reglas (anidamiento
 * válido, recálculo de orden) vivan en un solo lugar.
 */
class RegulacionNodoService
{
    /**
     * Crea un nodo bajo un padre (o en la raíz si $parent es null). Valida el
     * anidamiento y, si no se da número, sugiere uno. El nuevo nodo se coloca al
     * final de sus hermanos.
     */
    public function crear(Regulacion $regulacion, ?RegulacionNodo $parent, string $tipo, ?string $numero, ?string $texto): RegulacionNodo
    {
        $this->validarAnidamiento($parent, $tipo);

        return DB::transaction(function () use ($regulacion, $parent, $tipo, $numero, $texto) {
            $parentId = $parent?->id;

            // Número sugerido si no se especificó (ayuda, no imposición).
            if ($numero === null || trim($numero) === '') {
                $numero = RegulacionNodo::siguienteNumeroSugerido($regulacion->id, $parentId, $tipo) ?: null;
            }

            $ordenMax = RegulacionNodo::where('regulacion_id', $regulacion->id)
                ->where('parent_id', $parentId)
                ->max('orden');

            return $regulacion->nodos()->create([
                'parent_id' => $parentId,
                'tipo'      => $tipo,
                'numero'    => $numero,
                'texto'     => $texto,
                'orden'     => ($ordenMax ?? 0) + 1,
                'estado'    => RegulacionNodo::ESTADO_VIGENTE,
            ]);
        });
    }

    /** Edita el número y/o texto de un nodo (no cambia su posición ni tipo). */
    public function actualizar(RegulacionNodo $nodo, ?string $numero, ?string $texto): RegulacionNodo
    {
        $nodo->update([
            'numero' => $numero !== null && trim($numero) !== '' ? $numero : null,
            'texto'  => $texto,
        ]);
        return $nodo->fresh();
    }

    /**
     * Mueve un nodo a un nuevo padre y/o nueva posición.
     *
     * @param  RegulacionNodo      $nodo        nodo a mover
     * @param  RegulacionNodo|null $nuevoPadre  destino (null = raíz)
     * @param  int                 $nuevoOrden  posición deseada entre los hermanos del destino (1-based)
     */
    public function mover(RegulacionNodo $nodo, ?RegulacionNodo $nuevoPadre, int $nuevoOrden): RegulacionNodo
    {
        // No se puede mover un nodo dentro de sí mismo o de un descendiente.
        if ($nuevoPadre && $this->esDescendiente($nuevoPadre, $nodo)) {
            throw ValidationException::withMessages([
                'parent_id' => 'No se puede mover un elemento dentro de sí mismo o de uno de sus hijos.',
            ]);
        }

        $this->validarAnidamiento($nuevoPadre, $nodo->tipo);

        return DB::transaction(function () use ($nodo, $nuevoPadre, $nuevoOrden) {
            $nuevoParentId = $nuevoPadre?->id;

            $nodo->update(['parent_id' => $nuevoParentId]);
            $this->reordenarHermanos($nodo->regulacion_id, $nuevoParentId, $nodo->id, $nuevoOrden);

            return $nodo->fresh();
        });
    }

    /** Marca un nodo como derogado (sin cascada: solo este nodo). */
    public function derogar(RegulacionNodo $nodo, ?string $nota): RegulacionNodo
    {
        $nodo->update([
            'estado'        => RegulacionNodo::ESTADO_DEROGADO,
            'derogado_nota' => $nota !== null && trim($nota) !== '' ? $nota : null,
        ]);
        return $nodo->fresh();
    }

    /** Restaura un nodo derogado a vigente. */
    public function restaurar(RegulacionNodo $nodo): RegulacionNodo
    {
        $nodo->update([
            'estado'        => RegulacionNodo::ESTADO_VIGENTE,
            'derogado_nota' => null,
        ]);
        return $nodo->fresh();
    }

    /**
     * Borra realmente un nodo y todo su subárbol (la FK tiene cascadeOnDelete).
     * Para errores de captura; la derogación es el camino normal.
     */
    /**
     * Manda un nodo a la papelera (soft-delete), junto con TODOS sus descendientes
     * para que el bloque se borre y se restaure como una sola unidad. No es un
     * borrado físico: los nodos conservan su parent_id y orden, y pueden
     * restaurarse o limpiarse después (ver restaurar / eliminarDefinitivo).
     */
    public function eliminar(RegulacionNodo $nodo): void
    {
        $regulacionId = $nodo->regulacion_id;
        $parentId     = $nodo->parent_id;

        DB::transaction(function () use ($nodo, $regulacionId, $parentId) {
            // Marcar el subárbol completo (el nodo y sus descendientes vivos).
            foreach ($this->descendientesVivos($nodo) as $desc) {
                $desc->delete();
            }
            $nodo->delete();
            // Compactar el orden de los hermanos que quedaron visibles.
            $this->compactarOrden($regulacionId, $parentId);
        });
    }

    /**
     * Restaura desde la papelera un nodo y todo su subárbol, devolviéndolo a su
     * lugar. Si su padre ya no existe (fue limpiado), se restaura como raíz para
     * no perderlo. Se recoloca al final de sus hermanos para no chocar órdenes.
     *
     * Distinto de restaurar(): aquel revierte una DEROGACIÓN (estado); este saca
     * un nodo de la PAPELERA (soft delete).
     */
    public function restaurarDePapelera(RegulacionNodo $nodo): void
    {
        DB::transaction(function () use ($nodo) {
            // Si el padre ya no existe (ni vivo ni en papelera), pasa a raíz.
            if ($nodo->parent_id !== null) {
                $padreExiste = RegulacionNodo::withTrashed()
                    ->whereKey($nodo->parent_id)->exists();
                if (!$padreExiste) {
                    $nodo->parent_id = null;
                }
            }
            // Recolocar al final de sus hermanos vivos actuales.
            $nodo->orden = $this->siguienteOrdenLibre($nodo->regulacion_id, $nodo->parent_id);
            $nodo->save();
            $nodo->restore();

            // Restaurar los descendientes que se mandaron a la papelera junto a él.
            foreach ($this->descendientesEnPapelera($nodo) as $desc) {
                $desc->restore();
            }
        });
    }

    /**
     * Borra DEFINITIVAMENTE (físico) un nodo de la papelera y su subárbol. Solo
     * debe llamarse sobre nodos que ya están en la papelera.
     */
    public function eliminarDefinitivo(RegulacionNodo $nodo): void
    {
        DB::transaction(function () use ($nodo) {
            // forceDelete dispara la cascada física de la FK, que ya elimina a los
            // descendientes; se hace explícito por claridad y por si la cascada
            // no cubriera nodos en papelera en algún motor.
            foreach ($this->descendientesEnPapelera($nodo) as $desc) {
                $desc->forceDelete();
            }
            $nodo->forceDelete();
        });
    }

    /** Descendientes vivos (no en papelera) de un nodo, recursivamente. */
    private function descendientesVivos(RegulacionNodo $nodo): \Illuminate\Support\Collection
    {
        $acumulado = collect();
        $hijos = RegulacionNodo::where('parent_id', $nodo->id)->get();
        foreach ($hijos as $hijo) {
            $acumulado->push($hijo);
            $acumulado = $acumulado->merge($this->descendientesVivos($hijo));
        }
        return $acumulado;
    }

    /** Descendientes en papelera de un nodo, recursivamente (incluye trashed). */
    private function descendientesEnPapelera(RegulacionNodo $nodo): \Illuminate\Support\Collection
    {
        $acumulado = collect();
        $hijos = RegulacionNodo::withTrashed()
            ->where('parent_id', $nodo->id)
            ->whereNotNull('deleted_at')
            ->get();
        foreach ($hijos as $hijo) {
            $acumulado->push($hijo);
            $acumulado = $acumulado->merge($this->descendientesEnPapelera($hijo));
        }
        return $acumulado;
    }

    /** Siguiente orden libre entre los hermanos vivos de un padre. */
    private function siguienteOrdenLibre(int $regId, ?int $parentId): int
    {
        $max = RegulacionNodo::where('regulacion_id', $regId)
            ->where('parent_id', $parentId)
            ->max('orden');
        return (int) $max + 1;
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private function validarAnidamiento(?RegulacionNodo $parent, string $tipoHijo): void
    {
        $permitidos = $parent
            ? (RegulacionNodo::ANIDAMIENTO[$parent->tipo] ?? [])
            : RegulacionNodo::ANIDAMIENTO['raiz'];

        if (!in_array($tipoHijo, $permitidos, true)) {
            $destino = $parent ? $parent->etiquetaTipo() : 'la raíz de la regulación';
            $etiqueta = RegulacionNodo::ETIQUETAS_TIPO[$tipoHijo] ?? $tipoHijo;
            throw ValidationException::withMessages([
                'tipo' => "Un elemento de tipo «{$etiqueta}» no puede ir dentro de {$destino}.",
            ]);
        }
    }

    /** ¿$posibleDescendiente está dentro del subárbol de $nodo? */
    private function esDescendiente(RegulacionNodo $posibleDescendiente, RegulacionNodo $nodo): bool
    {
        $actual = $posibleDescendiente;
        while ($actual !== null) {
            if ($actual->id === $nodo->id) {
                return true;
            }
            $actual = $actual->parent_id ? RegulacionNodo::find($actual->parent_id) : null;
        }
        return false;
    }

    /**
     * Inserta $nodoId en la posición $orden entre los hermanos de ($regId,
     * $parentId) y recalcula el orden de todos de forma consecutiva (1..N).
     */
    private function reordenarHermanos(int $regId, ?int $parentId, int $nodoId, int $orden): void
    {
        $hermanos = RegulacionNodo::where('regulacion_id', $regId)
            ->where('parent_id', $parentId)
            ->orderBy('orden')
            ->pluck('id')
            ->reject(fn ($id) => $id === $nodoId)
            ->values()
            ->all();

        $orden = max(1, min($orden, count($hermanos) + 1));
        array_splice($hermanos, $orden - 1, 0, [$nodoId]);

        foreach ($hermanos as $i => $id) {
            RegulacionNodo::where('id', $id)->update(['orden' => $i + 1]);
        }
    }

    /** Reasigna orden consecutivo a los hermanos (tras un borrado). */
    private function compactarOrden(int $regId, ?int $parentId): void
    {
        $hermanos = RegulacionNodo::where('regulacion_id', $regId)
            ->where('parent_id', $parentId)
            ->orderBy('orden')
            ->pluck('id');

        foreach ($hermanos as $i => $id) {
            RegulacionNodo::where('id', $id)->update(['orden' => $i + 1]);
        }
    }
}
