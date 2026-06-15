<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertirRegulacionJob;
use App\Models\Dependencia;
use App\Models\Regulacion;
use App\Models\SectorScian;
use App\Services\RegulacionConversorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegulacionController extends Controller
{
    public function __construct(private RegulacionConversorService $conversor) {}

    public function index(Request $request)
    {
        $regulaciones = Regulacion::with('dependencia', 'creador', 'sector')
            ->when($request->estatus,     fn ($q, $v) => $q->where('estatus', $v))
            ->when($request->dependencia, fn ($q, $v) => $q->where('dependencia_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                  ->orWhere('palabras_clave', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $dependencias = Dependencia::orderBy('nombre')->get();
        $estatuses    = Regulacion::ESTATUS_TODOS;

        return view('screens.regulaciones.index', compact('regulaciones', 'dependencias', 'estatuses'));
    }

    public function create()
    {
        $dependencias = Dependencia::orderBy('nombre')->get();
        $sectores     = SectorScian::orderBy('nombre')->get();
        return view('screens.regulaciones.create', compact('dependencias', 'sectores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'             => 'required|string|max:500',
            'tipo'               => 'nullable|string|max:100',
            'materia'            => 'nullable|string|max:100',
            'dependencia_id'     => 'nullable|exists:dependencias,id',
            'fecha_publicacion'  => 'nullable|date',
            'fecha_vigencia'     => 'nullable|date|after_or_equal:fecha_publicacion',
            'resumen'            => 'nullable|string',
            'fundamento_juridico'=> 'nullable|string',
            'objetivo'           => 'nullable|string',
            'sector_id'          => 'nullable|exists:sectores_scian,id',
            'palabras_clave'     => 'nullable|string|max:500',
            'deroga_otra'        => 'nullable',
            'regulacion_derogada'=> 'nullable|string|max:500',
            'archivo'            => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $regulacion = Regulacion::create([
            'nombre'              => $validated['nombre'],
            'tipo'                => $validated['tipo']               ?? null,
            'materia'             => $validated['materia']            ?? null,
            'dependencia_id'      => $request->user()->dependencia_id,
            'fecha_publicacion'   => $validated['fecha_publicacion']  ?? null,
            'fecha_vigencia'      => $validated['fecha_vigencia']     ?? null,
            'resumen'             => $validated['resumen']            ?? null,
            'fundamento_juridico' => $validated['fundamento_juridico']?? null,
            'objetivo'            => $validated['objetivo']           ?? null,
            'sector_id'           => $validated['sector_id']          ?? null,
            'palabras_clave'      => $validated['palabras_clave']     ?? null,
            'deroga_otra'         => $request->boolean('deroga_otra'),
            'regulacion_derogada' => $request->boolean('deroga_otra')
                                        ? ($validated['regulacion_derogada'] ?? null)
                                        : null,
            'estatus'             => Regulacion::ESTATUS_VIGENTE,
            'created_by'          => $request->user()->id,
        ]);

        // Genera el folio (LPZ-REG-SIGLAS-AÑO-NNN). Las regulaciones nacen
        // vigentes, así que el folio se asigna al crear.
        $regulacion->load('dependencia');
        $regulacion->folio = $regulacion->generarFolio();
        $regulacion->save();

        $this->conversor->guardarOriginal($regulacion, $request->file('archivo'));
        ConvertirRegulacionJob::dispatch($regulacion);

        return redirect()->route('regulaciones.show', $regulacion)
            ->with('success', 'Regulación registrada. La conversión a Markdown y la extracción de índice se están procesando.');
    }

    public function show(Regulacion $regulacion)
    {
        $regulacion->load('dependencia', 'creador', 'sector');
        $contenidoMd = $this->conversor->obtenerContenidoMarkdown($regulacion);

        return view('screens.regulaciones.show', compact('regulacion', 'contenidoMd'));
    }

    public function edit(Regulacion $regulacion)
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            return redirect()->route('regulaciones.show', $regulacion)
                ->with('error', 'Solo puede editar regulaciones de su dependencia.');
        }

        $dependencias = Dependencia::orderBy('nombre')->get();
        $sectores     = SectorScian::orderBy('nombre')->get();
        return view('screens.regulaciones.edit', compact('regulacion', 'dependencias', 'sectores'));
    }

    public function update(Request $request, Regulacion $regulacion)
    {
        if (!$request->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para editar esta regulación.');
        }

        $validated = $request->validate([
            'nombre'             => 'required|string|max:500',
            'tipo'               => 'nullable|string|max:100',
            'materia'            => 'nullable|string|max:100',
            'dependencia_id'     => 'nullable|exists:dependencias,id',
            'fecha_publicacion'  => 'nullable|date',
            'fecha_vigencia'     => 'nullable|date|after_or_equal:fecha_publicacion',
            'resumen'            => 'nullable|string',
            'fundamento_juridico'=> 'nullable|string',
            'objetivo'           => 'nullable|string',
            'sector_id'          => 'nullable|exists:sectores_scian,id',
            'palabras_clave'     => 'nullable|string|max:500',
            'deroga_otra'        => 'nullable',
            'regulacion_derogada'=> 'nullable|string|max:500',
            'estatus'            => 'required|in:' . implode(',', Regulacion::ESTATUS_TODOS),
            'archivo'            => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'indice_manual'      => 'nullable|string',
        ]);

        $regulacion->update([
            'nombre'              => $validated['nombre'],
            'tipo'                => $validated['tipo']               ?? null,
            'materia'             => $validated['materia']            ?? null,
            'dependencia_id'      => $validated['dependencia_id']    ?? null,
            'fecha_publicacion'   => $validated['fecha_publicacion']  ?? null,
            'fecha_vigencia'      => $validated['fecha_vigencia']     ?? null,
            'resumen'             => $validated['resumen']            ?? null,
            'fundamento_juridico' => $validated['fundamento_juridico']?? null,
            'objetivo'            => $validated['objetivo']           ?? null,
            'sector_id'           => $validated['sector_id']          ?? null,
            'palabras_clave'      => $validated['palabras_clave']     ?? null,
            'deroga_otra'         => $request->boolean('deroga_otra'),
            'regulacion_derogada' => $request->boolean('deroga_otra')
                                        ? ($validated['regulacion_derogada'] ?? null)
                                        : null,
            'estatus'             => $validated['estatus'],
        ]);

        if ($request->hasFile('archivo')) {
            $this->conversor->guardarOriginal($regulacion, $request->file('archivo'));
            ConvertirRegulacionJob::dispatch($regulacion);
        }

        return redirect()->route('regulaciones.show', $regulacion)
            ->with('success', 'Regulación actualizada.');
    }

    public function destroy(Regulacion $regulacion)
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para eliminar esta regulación.');
        }

        $this->conversor->eliminarArchivos($regulacion);
        $regulacion->delete();

        return redirect()->route('regulaciones.index')
            ->with('success', 'Regulación eliminada.');
    }

    public function descargarOriginal(Regulacion $regulacion)
    {
        if (empty($regulacion->archivo_original) || !Storage::disk('local')->exists($regulacion->archivo_original)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('local')->download($regulacion->archivo_original);
    }

    public function reintentar(Regulacion $regulacion)
    {
        if (empty($regulacion->archivo_original)) {
            return back()->with('error', 'No hay archivo original para convertir.');
        }

        $regulacion->update([
            'conversion_estatus' => Regulacion::CONVERSION_PENDIENTE,
            'conversion_error'   => null,
        ]);

        ConvertirRegulacionJob::dispatch($regulacion);

        return back()->with('success', 'Conversión reencolada.');
    }
}
