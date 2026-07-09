<?php

namespace App\Http\Controllers;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Services\RegulacionNodoService;
use Illuminate\Http\Request;

/**
 * Endpoints del editor jerárquico de regulaciones (capa de nodos).
 *
 * Todas las acciones exigen que el usuario pueda editar la regulación dueña del
 * nodo: jurídico de su propia dependencia o admin (User::puedeEditarRegulacion).
 * La lógica del árbol vive en RegulacionNodoService; aquí solo se autoriza,
 * valida la entrada y se delega.
 */
class RegulacionNodoController extends Controller
{
    public function __construct(private RegulacionNodoService $nodos) {}

    /** Crea un nodo dentro de una regulación. */
    public function store(Request $request, Regulacion $regulacion)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para editar el articulado.');
        }

        $this->autorizar($regulacion);

        $datos = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'tipo'      => ['required', 'string', 'in:' . implode(',', RegulacionNodo::TIPOS)],
            'numero'    => ['nullable', 'string', 'max:60'],
            'texto'     => ['required', 'string'],
        ]);

        // Validación anti-duplicado: no puede existir otro nodo con el mismo
        // tipo + número bajo el mismo padre (editar ≠ agregar).
        $numero = trim($datos['numero'] ?? '');
        if ($numero !== '') {
            $duplicado = RegulacionNodo::where('regulacion_id', $regulacion->id)
                ->where('parent_id', $datos['parent_id'] ?? null)
                ->where('tipo', $datos['tipo'])
                ->where('numero', $numero)
                ->exists();

            if ($duplicado) {
                $etiqueta = RegulacionNodo::ETIQUETAS_TIPO[$datos['tipo']] ?? $datos['tipo'];
                return back()->with('error', "{$etiqueta} {$numero} ya existe en ese nivel. Use «Editar» para modificarlo.");
            }
        }

        $parent = $this->parentDeLaRegulacion($regulacion, $datos['parent_id'] ?? null);

        $nodo = $this->nodos->crear(
            $regulacion,
            $parent,
            $datos['tipo'],
            $datos['numero'] ?? null,
            $datos['texto'] ?? null,
        );

        return back()->with('success', $nodo->etiquetaTipo() . ' ' . ($nodo->numero ?? '') . ' agregado.');
    }

    /**
     * Devuelve el siguiente número sugerido para un tipo de nodo bajo un padre.
     * Se usa desde JS (fetch) para prellenar el campo al abrir el modal.
     */
    public function sugerirNumero(Request $request, Regulacion $regulacion)
    {
        $tipo     = $request->query('tipo', '');
        $parentId = $request->query('parent_id') ?: null;

        $numero = RegulacionNodo::siguienteNumeroSugerido(
            $regulacion->id,
            $parentId ? (int) $parentId : null,
            $tipo,
        );

        return response()->json(['numero' => $numero]);
    }

    /** Edita número y/o texto de un nodo. */
    public function update(Request $request, RegulacionNodo $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para editar el articulado.');
        }

        $this->autorizar($nodo->regulacion);

        $datos = $request->validate([
            'numero' => ['nullable', 'string', 'max:60'],
            'texto'  => ['nullable', 'string'],
        ]);

        $this->nodos->actualizar($nodo, $datos['numero'] ?? null, $datos['texto'] ?? null);

        return back()->with('success', 'Cambios guardados.');
    }

    /** Mueve un nodo a otro padre y/o posición. */
    public function mover(Request $request, RegulacionNodo $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para modificar el articulado.');
        }

        $this->autorizar($nodo->regulacion);

        $datos = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'orden'     => ['required', 'integer', 'min:1'],
        ]);

        $nuevoPadre = $this->parentDeLaRegulacion($nodo->regulacion, $datos['parent_id'] ?? null);

        $this->nodos->mover($nodo, $nuevoPadre, (int) $datos['orden']);

        // #35: si la petición viene de fetch (Accept: application/json), devolver
        // JSON en vez de redirect. El drag&drop ya no recarga la página.
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'mensaje' => 'Elemento movido.']);
        }

        return back()->with('success', 'Elemento movido.');
    }

    /** Marca un nodo como derogado. */
    public function derogar(Request $request, RegulacionNodo $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para modificar el articulado.');
        }

        $this->autorizar($nodo->regulacion);

        $datos = $request->validate([
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $this->nodos->derogar($nodo, $datos['nota'] ?? null);

        return back()->with('success', 'Elemento marcado como derogado.');
    }

    /** Restaura un nodo derogado. */
    public function restaurar(RegulacionNodo $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para modificar el articulado.');
        }

        $this->autorizar($nodo->regulacion);

        $this->nodos->restaurar($nodo);

        return back()->with('success', 'Elemento restaurado.');
    }

    /** Manda un nodo y su subárbol a la papelera (para errores de captura). */
    public function destroy(RegulacionNodo $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para editar el articulado.');
        }

        $this->autorizar($nodo->regulacion);

        $this->nodos->eliminar($nodo);

        return back()->with('success', 'Elemento enviado a la papelera. Puedes restaurarlo desde ahí durante 7 días.');
    }

    /** Vista de la papelera de una regulación: elementos enviados, restaurables. */
    public function papelera(Regulacion $regulacion)
    {
        $this->autorizar($regulacion);

        // Solo los nodos en papelera de esta regulación. Se muestran los "tope"
        // (cuyo padre NO está también en papelera) para no listar el subárbol
        // entero: restaurar el tope restaura sus hijos.
        $enPapelera = RegulacionNodo::onlyTrashed()
            ->where('regulacion_id', $regulacion->id)
            ->orderByDesc('deleted_at')
            ->get();

        $idsEnPapelera = $enPapelera->pluck('id')->all();
        $topes = $enPapelera->filter(function ($nodo) use ($idsEnPapelera) {
            // Es "tope" si no tiene padre, o su padre no está en la papelera.
            return $nodo->parent_id === null || !in_array($nodo->parent_id, $idsEnPapelera, true);
        })->values();

        return view('screens.regulaciones.papelera', compact('regulacion', 'topes'));
    }

    /** Restaura desde la papelera un nodo (y su subárbol) a su lugar. */
    public function restaurarPapelera(int $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para modificar el articulado.');
        }

        $registro = RegulacionNodo::onlyTrashed()->findOrFail($nodo);
        $this->autorizar($registro->regulacion);

        $this->nodos->restaurarDePapelera($registro);

        return back()->with('success', 'Elemento restaurado a su lugar.');
    }

    /** Borra DEFINITIVAMENTE un nodo de la papelera (no se puede deshacer). */
    public function eliminarDefinitivo(int $nodo)
    {
        if (!auth()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para modificar el articulado.');
        }

        $registro = RegulacionNodo::onlyTrashed()->findOrFail($nodo);
        $this->autorizar($registro->regulacion);

        $this->nodos->eliminarDefinitivo($registro);

        return back()->with('success', 'Elemento eliminado definitivamente.');
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /** Permiso por dependencia: jurídico de su dependencia o admin. */
    private function autorizar(Regulacion $regulacion): void
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para editar el articulado de esta regulación.');
        }
    }

    /**
     * Resuelve el nodo padre asegurando que pertenece a la MISMA regulación
     * (evita mover/colgar nodos entre regulaciones distintas). Null = raíz.
     */
    private function parentDeLaRegulacion(Regulacion $regulacion, ?int $parentId): ?RegulacionNodo
    {
        if ($parentId === null) {
            return null;
        }

        $parent = RegulacionNodo::find($parentId);
        if (!$parent || $parent->regulacion_id !== $regulacion->id) {
            abort(404, 'El elemento padre no pertenece a esta regulación.');
        }
        return $parent;
    }
}
