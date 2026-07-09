<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Clase base para los catálogos tipo "lista simple con nombre/orden":
 * tipos de regulación y tipos de trámite. Ambos compartían un CRUD idéntico
 * (misma validación, misma vista tipo-form, mismo patrón de redirect), por lo
 * que aquí vive una sola vez. Cada catálogo concreto hereda de esta clase y
 * solo declara su modelo, su tabla, su título y sus nombres de ruta.
 *
 * Los controllers hijos conservan los nombres de método originales
 * (tiposRegulacion, guardarTipoRegulacion, etc.) como alias delgados que
 * llaman a los métodos genéricos de aquí, para no tener que reescribir las
 * rutas: en web.php solo cambia la clase, no el método ni el nombre de ruta.
 */
abstract class TipoCatalogoController extends Controller
{
    /** Clase del modelo Eloquent del catálogo (p. ej. TipoRegulacion::class). */
    abstract protected function modelo(): string;

    /** Nombre de la tabla, para la regla de validación unique. */
    abstract protected function tabla(): string;

    /** Título legible que se muestra en el formulario ("Tipo de regulación"). */
    abstract protected function titulo(): string;

    /** Prefijo de las rutas del catálogo ("admin.catalogos.tipos-regulacion"). */
    abstract protected function rutaBase(): string;

    /** Vista de listado del catálogo ("screens.admin.catalogos.tipos-regulacion"). */
    abstract protected function vistaLista(): string;

    /** Nombre de la variable que la vista de listado espera (compact). */
    abstract protected function variableLista(): string;

    // ─── CRUD genérico ───────────────────────────────────────────

    protected function listar()
    {
        $modelo = $this->modelo();
        $items  = $modelo::orderBy('orden')->orderBy('nombre')->get();

        return view($this->vistaLista(), [$this->variableLista() => $items]);
    }

    protected function mostrarCrear()
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'          => null,
            'titulo'        => $this->titulo(),
            'ruta_guardar'  => $this->rutaBase() . '.guardar',
            'ruta_cancelar' => $this->rutaBase(),
        ]);
    }

    protected function guardar(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:' . $this->tabla() . ',nombre',
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $validated['activo'] = true;
        $validated['orden']  = $validated['orden'] ?? 0;

        $modelo = $this->modelo();
        $modelo::create($validated);

        return redirect()->route($this->rutaBase())
            ->with('success', $this->titulo() . ' creado.');
    }

    protected function mostrarEditar(Model $item)
    {
        return view('screens.admin.catalogos.tipo-form', [
            'item'            => $item,
            'titulo'          => $this->titulo(),
            'ruta_actualizar' => $this->rutaBase() . '.actualizar',
            'ruta_cancelar'   => $this->rutaBase(),
        ]);
    }

    protected function actualizar(Request $request, Model $item)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:' . $this->tabla() . ',nombre,' . $item->id,
            'descripcion' => 'nullable|string|max:500',
            'orden'       => 'nullable|integer|min:0',
        ]);
        $item->update($validated);

        return redirect()->route($this->rutaBase())
            ->with('success', $this->titulo() . ' actualizado.');
    }

    protected function alternar(Model $item)
    {
        $item->update(['activo' => !$item->activo]);
        $estado = $item->activo ? 'activado' : 'desactivado';

        return back()->with('success', 'Tipo ' . $estado . '.');
    }
}
