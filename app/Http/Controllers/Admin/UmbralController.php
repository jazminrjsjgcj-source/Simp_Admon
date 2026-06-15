<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SectorScian;
use App\Models\SubsectorScian;
use App\Models\UmbralConfigurado;
use App\Services\UmbralCalculadorService;
use Illuminate\Http\Request;

/**
 * CRUD de umbrales configurados.
 *
 * Cada umbral pertenece a un sector y opcionalmente un subsector.
 * Al guardarlo, el sistema convierte automáticamente el monto base
 * a pesos, UMA, salario mínimo y UDI según los valores vigentes del año.
 */
class UmbralController extends Controller
{
    public function __construct(private UmbralCalculadorService $calculador) {}

    public function index(Request $request)
    {
        $umbrales = UmbralConfigurado::with('sector', 'subsector', 'cargadoPor')
            ->when($request->sector,    fn ($q, $v) => $q->where('sector_id', $v))
            ->when($request->estatus,   fn ($q, $v) => $q->where('estatus', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $sectores = SectorScian::orderBy('codigo')->get();

        return view('screens.admin.umbrales.index', compact('umbrales', 'sectores'));
    }

    public function create()
    {
        $sectores    = SectorScian::orderBy('codigo')->get();
        $subsectores = SubsectorScian::orderBy('codigo')->get();
        $anios       = range(now()->year, now()->year + 1);

        return view('screens.admin.umbrales.crear', compact('sectores', 'subsectores', 'anios'));
    }

    public function store(Request $request)
    {
        $validated = $this->validarUmbral($request);

        try {
            $equivalencias = $this->calculador->calcularEquivalencias(
                floatval($validated['monto_base']),
                $validated['unidad_base'],
                intval($validated['anio'])
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        UmbralConfigurado::create(array_merge($validated, $equivalencias, [
            'cargado_por' => $request->user()->id,
            'fecha_carga' => now(),
        ]));

        return redirect()->route('admin.umbrales.index')
            ->with('success', 'Umbral configurado registrado.');
    }

    public function edit(UmbralConfigurado $umbral)
    {
        $sectores    = SectorScian::orderBy('codigo')->get();
        $subsectores = SubsectorScian::orderBy('codigo')->get();

        return view('screens.admin.umbrales.editar', compact('umbral', 'sectores', 'subsectores'));
    }

    public function update(Request $request, UmbralConfigurado $umbral)
    {
        $validated = $this->validarUmbral($request);

        try {
            $equivalencias = $this->calculador->calcularEquivalencias(
                floatval($validated['monto_base']),
                $validated['unidad_base'],
                intval($validated['anio'])
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $umbral->update(array_merge($validated, $equivalencias));

        return redirect()->route('admin.umbrales.index')
            ->with('success', 'Umbral actualizado.');
    }

    public function destroy(UmbralConfigurado $umbral)
    {
        $umbral->update(['estatus' => UmbralConfigurado::ESTATUS_INACTIVO]);

        return redirect()->route('admin.umbrales.index')
            ->with('success', 'Umbral marcado como inactivo.');
    }

    private function validarUmbral(Request $request): array
    {
        return $request->validate([
            'sector_id'       => 'nullable|exists:sectores_scian,id',
            'subsector_id'    => 'nullable|exists:subsectores_scian,id',
            'monto_base'      => 'required|numeric|min:0',
            'unidad_base'     => 'required|in:pesos,UMA,salario_minimo,UDI',
            'anio'            => 'required|integer|min:2020|max:2100',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fin'    => 'nullable|date|after_or_equal:vigencia_inicio',
            'estatus'         => 'required|in:activo,inactivo',
            'fuente'          => 'nullable|string|max:500',
            'fecha_fuente'    => 'nullable|date',
            'observaciones'   => 'nullable|string',
        ]);
    }
}
