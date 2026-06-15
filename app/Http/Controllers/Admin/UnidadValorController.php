<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnidadValorReferencia;
use Illuminate\Http\Request;

/**
 * CRUD de unidades de valor (UMA, salario mínimo, UDI) por año.
 *
 * Cada año debe actualizarse según publicación oficial:
 *   - UMA: INEGI (enero)
 *   - Salario mínimo: CONASAMI (diciembre del año anterior)
 *   - UDI: BANXICO (diario, se toma cierre)
 */
class UnidadValorController extends Controller
{
    public function index()
    {
        $unidades = UnidadValorReferencia::orderByDesc('anio')
            ->orderBy('unidad')
            ->get();

        return view('screens.admin.unidades-valor.index', compact('unidades'));
    }

    public function create()
    {
        $tiposUnidad = [
            UnidadValorReferencia::UMA,
            UnidadValorReferencia::SALARIO_MINIMO,
            UnidadValorReferencia::UDI,
        ];
        $anios = range(now()->year, now()->year - 5);

        return view('screens.admin.unidades-valor.crear', compact('tiposUnidad', 'anios'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unidad'          => 'required|string|max:30',
            'valor_pesos'     => 'required|numeric|min:0',
            'anio'            => 'required|integer|min:2020|max:2100',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fin'    => 'nullable|date|after_or_equal:vigencia_inicio',
            'fuente'          => 'nullable|string|max:255',
        ]);

        // Verificar duplicado unidad+año
        $existe = UnidadValorReferencia::where('unidad', $validated['unidad'])
            ->where('anio', $validated['anio'])
            ->exists();

        if ($existe) {
            return back()->withInput()
                ->with('error', "Ya existe un valor para {$validated['unidad']} del año {$validated['anio']}. Edítelo en lugar de crear uno nuevo.");
        }

        $validated['activo']          = true;
        $validated['actualizado_por'] = $request->user()->id;

        UnidadValorReferencia::create($validated);

        return redirect()->route('admin.unidades-valor.index')
            ->with('success', 'Unidad de valor registrada.');
    }

    public function edit(UnidadValorReferencia $unidad)
    {
        return view('screens.admin.unidades-valor.editar', compact('unidad'));
    }

    public function update(Request $request, UnidadValorReferencia $unidad)
    {
        $validated = $request->validate([
            'valor_pesos'     => 'required|numeric|min:0',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fin'    => 'nullable|date|after_or_equal:vigencia_inicio',
            'fuente'          => 'nullable|string|max:255',
            'activo'          => 'sometimes|boolean',
        ]);

        $validated['activo']          = $request->boolean('activo', true);
        $validated['actualizado_por'] = $request->user()->id;

        $unidad->update($validated);

        return redirect()->route('admin.unidades-valor.index')
            ->with('success', "Valor {$unidad->unidad} {$unidad->anio} actualizado.");
    }
}
