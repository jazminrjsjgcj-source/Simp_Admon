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
            // #6: el editor visual manda el índice serializado como JSON.
            'indice_json'        => 'nullable|string',
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

        // #6: guardar el índice editado manualmente (si viene del editor visual).
        // Si el usuario no tocó el editor, el campo llega vacío y se conserva el
        // índice extraído automáticamente del archivo.
        if (!empty($validated['indice_json'])) {
            $indiceEditado = json_decode($validated['indice_json'], true);
            if (is_array($indiceEditado)) {
                $regulacion->update(['indice' => $indiceEditado]);
            }
        }

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

    /**
     * Descarga masiva: empaqueta en un ZIP los archivos originales de las
     * regulaciones que cumplen los filtros activos (los mismos del index:
     * dependencia, estatus y búsqueda). Solo incluye regulaciones que tengan
     * archivo original existente en disco.
     */
    public function descargarZip(Request $request)
    {
        // Mismos filtros que el index, para descargar exactamente lo que se ve.
        $regulaciones = Regulacion::with('dependencia')
            ->when($request->estatus,     fn ($q, $v) => $q->where('estatus', $v))
            ->when($request->dependencia, fn ($q, $v) => $q->where('dependencia_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%");
            }))
            ->get();

        // Quedarnos solo con las que tienen archivo original existente.
        $conArchivo = $regulaciones->filter(function ($reg) {
            return !empty($reg->archivo_original)
                && Storage::disk('local')->exists($reg->archivo_original);
        });

        if ($conArchivo->isEmpty()) {
            return back()->with('error', 'No hay archivos para descargar con los filtros seleccionados.');
        }

        // Crear el ZIP en una carpeta temporal.
        $nombreZip = 'regulaciones_' . now()->format('Ymd_His') . '.zip';
        $rutaTemp  = storage_path('app/tmp');
        if (!is_dir($rutaTemp)) {
            mkdir($rutaTemp, 0755, true);
        }
        $rutaZip = $rutaTemp . DIRECTORY_SEPARATOR . $nombreZip;

        $zip = new \ZipArchive();
        if ($zip->open($rutaZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el archivo ZIP.');
        }

        $usados = []; // para evitar nombres repetidos dentro del ZIP
        foreach ($conArchivo as $reg) {
            $rutaAbsoluta = Storage::disk('local')->path($reg->archivo_original);

            // Nombre legible dentro del ZIP: folio o nombre + extensión original.
            $extension = pathinfo($reg->archivo_original, PATHINFO_EXTENSION);
            $base      = $this->nombreArchivoSeguro($reg->folio ?? $reg->nombre);
            $nombreEnZip = $base . ($extension ? '.' . $extension : '');

            // Si ya existe ese nombre, le agrega un sufijo para no sobrescribir.
            $contador = 1;
            while (in_array($nombreEnZip, $usados)) {
                $nombreEnZip = $base . '_' . $contador . ($extension ? '.' . $extension : '');
                $contador++;
            }
            $usados[] = $nombreEnZip;

            $zip->addFile($rutaAbsoluta, $nombreEnZip);
        }

        $zip->close();

        // Descargar y borrar el ZIP temporal después de enviarlo.
        return response()->download($rutaZip, $nombreZip)->deleteFileAfterSend(true);
    }

    /**
     * Convierte un texto en un nombre de archivo seguro (sin acentos ni
     * caracteres especiales), para usarlo como nombre dentro del ZIP.
     */
    private function nombreArchivoSeguro(?string $texto): string
    {
        $texto = $texto ?: 'regulacion';
        // Quitar acentos
        $texto = \Illuminate\Support\Str::ascii($texto);
        // Dejar solo letras, números, guiones y guiones bajos
        $texto = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $texto);
        $texto = trim($texto, '_');
        return $texto !== '' ? $texto : 'regulacion';
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
