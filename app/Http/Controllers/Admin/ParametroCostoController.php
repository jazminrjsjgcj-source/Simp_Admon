<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametroCostoBurocratico;
use Illuminate\Http\Request;

/**
 * CRUD de parámetros del cálculo de costo burocrático.
 *
 * Los parámetros existentes pueden editarse pero NO eliminarse para
 * no romper el cálculo. Se pueden desactivar con el toggle `activo`,
 * lo que hace que el sistema caiga al valor por defecto del modelo.
 */
class ParametroCostoController extends Controller
{
    public function index()
    {
        $parametros = ParametroCostoBurocratico::with('actualizadoPor')
            ->orderBy('clave')
            ->get();

        return view('screens.admin.parametros.index', compact('parametros'));
    }

    public function edit(ParametroCostoBurocratico $parametro)
    {
        return view('screens.admin.parametros.editar', compact('parametro'));
    }

    public function update(Request $request, ParametroCostoBurocratico $parametro)
    {
        $validated = $request->validate([
            'valor'           => 'required|numeric|min:0',
            'unidad'          => 'required|string|max:50',
            'fuente'          => 'nullable|string|max:255',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fin'    => 'nullable|date|after_or_equal:vigencia_inicio',
            'activo'          => 'sometimes|boolean',
        ]);

        $validated['actualizado_por'] = $request->user()->id;
        $validated['activo']          = $request->boolean('activo', true);

        $parametro->update($validated);

        return redirect()->route('admin.parametros.index')
            ->with('success', "Parámetro \"{$parametro->clave}\" actualizado.");
    }
}
