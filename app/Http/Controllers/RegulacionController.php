<?php

namespace App\Http\Controllers;

use App\Models\Dependencia;
use App\Models\Regulacion;
use App\Models\SectorScian;
use App\Services\DefinitionExtractorService;
use App\Services\NotificadorService;
use App\Services\PdfConversorService;
use App\Services\RegulacionConversorService;
use App\Services\RegulacionEstructuradorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegulacionController extends Controller
{
    public function __construct(
        private RegulacionConversorService    $conversor,
        private RegulacionEstructuradorService $estructurador,
        private NotificadorService            $notificador,
        private PdfConversorService           $pdfConversor,
        private DefinitionExtractorService     $extractorDefiniciones,
    ) {}

    public function index(Request $request)
    {
        $regulaciones = Regulacion::with('dependencia', 'creador', 'sector')
            ->when($request->estatus, function ($q, $v) {
                // El filtro respeta el vencimiento calculado al vuelo:
                //  - 'vigente'  => marcada vigente Y sin fecha vencida.
                //  - 'vencida'  => marcada vigente PERO con fecha ya pasada
                //                  (estado calculado, no almacenado en BD).
                //  - otros      => coincidencia directa del campo estatus.
                if ($v === \App\Models\Regulacion::ESTATUS_VIGENTE) {
                    $q->where('estatus', $v)
                      ->where(fn ($s) => $s->whereNull('fecha_vigencia')
                                           ->orWhere('fecha_vigencia', '>=', now()->startOfDay()));
                } elseif ($v === \App\Models\Regulacion::ESTATUS_VENCIDA) {
                    $q->where('estatus', \App\Models\Regulacion::ESTATUS_VIGENTE)
                      ->whereNotNull('fecha_vigencia')
                      ->where('fecha_vigencia', '<', now()->startOfDay());
                } else {
                    $q->where('estatus', $v);
                }
            })
            ->when($request->dependencia, fn ($q, $v) => $q->where('dependencia_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                  ->orWhere('palabras_clave', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        // Para el filtro se añade 'vencida' (estado calculado al vuelo, no
        // almacenado): permite listar las regulaciones marcadas vigentes cuya
        // fecha de vigencia ya pasó. No se mete en ESTATUS_TODOS porque no es un
        // valor asignable ni guardado en BD.
        $estatuses    = array_merge(Regulacion::ESTATUS_TODOS, [Regulacion::ESTATUS_VENCIDA]);

        // Estante de favoritos del usuario: la lista completa para mostrar arriba,
        // y un set de IDs para que cada libro del catálogo sepa si está marcado.
        $favoritas    = $request->user()->regulacionesFavoritas()->with('dependencia')->get();
        $favoritasIds = $favoritas->pluck('id')->all();

        return view('screens.regulaciones.index', compact(
            'regulaciones', 'dependencias', 'estatuses', 'favoritas', 'favoritasIds'
        ));
    }

    public function create()
    {
        if (!auth()->user()->tienePermiso('regulaciones.crear')) {
            abort(403, 'No tiene permiso para subir regulaciones.');
        }

        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
        $sectores     = SectorScian::orderBy('nombre')->get();
        return view('screens.regulaciones.create', compact('dependencias', 'sectores'));
    }

    public function store(Request $request)
    {
        if (!$request->user()->tienePermiso('regulaciones.crear')) {
            abort(403, 'No tiene permiso para subir regulaciones.');
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
            'archivo'            => 'required|file|mimes:' . implode(',', Regulacion::EXTENSIONES_PERMITIDAS)
                                  . '|mimetypes:' . implode(',', Regulacion::MIME_TYPES_PERMITIDOS)
                                  . '|max:10240',
        ], [
            'archivo.mimes'     => Regulacion::ARCHIVO_ERROR_TIPO,
            'archivo.mimetypes' => Regulacion::ARCHIVO_ERROR_TIPO,
            'archivo.max'       => 'El archivo no debe pesar más de 10 MB.',
            'archivo.required'  => 'Seleccione un archivo Word o PDF para subir.',
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
        $this->conversor->convertirAMarkdown($regulacion);
        $this->pdfConversor->invalidarCache($regulacion);

        return redirect()->route('regulaciones.show', $regulacion)
            ->with('success', 'Regulación registrada y contenido convertido.');
    }

    public function show(Regulacion $regulacion)
    {
        $regulacion->load('dependencia', 'creador', 'sector');

        // Se pasa a la vista para que el modal de descarga muestre el botón
        // "Descargar como Word" solo cuando la conversión PDF→DOCX es viable.
        $tieneLibreOffice = $this->pdfConversor->libreOfficeDisponible();

        return view('screens.regulaciones.show', compact('regulacion', 'tieneLibreOffice'));
    }

    public function edit(Regulacion $regulacion)
    {
        if (!request()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para editar regulaciones.');
        }

        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            return redirect()->route('regulaciones.show', $regulacion)
                ->with('error', 'Solo puede editar regulaciones de su dependencia.');
        }

        $dependencias = Dependencia::activas()->orderBy('nombre')->get();
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

        return redirect()->route('regulaciones.show', $regulacion)
            ->with('success', 'Regulación actualizada.');
    }

    /**
     * Reemplaza el archivo original de una regulación.
     *
     * Acción separada del update() (B6): subir un archivo nuevo dispara la
     * conversión y regenera el índice, lo que puede borrar la estructura
     * manual ya construida; por eso vive en su propio botón con su propia
     * confirmación, en vez de viajar junto con el guardado de metadatos.
     */
    public function reemplazarArchivo(\Illuminate\Http\Request $request, Regulacion $regulacion)
    {
        if (!$request->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para reemplazar el archivo de esta regulación.');
        }

        $request->validate([
            'archivo' => 'required|file|mimes:' . implode(',', Regulacion::EXTENSIONES_PERMITIDAS)
                       . '|mimetypes:' . implode(',', Regulacion::MIME_TYPES_PERMITIDOS)
                       . '|max:10240',
        ], [
            'archivo.mimes'     => Regulacion::ARCHIVO_ERROR_TIPO,
            'archivo.mimetypes' => Regulacion::ARCHIVO_ERROR_TIPO,
            'archivo.max'       => 'El archivo no debe pesar más de 10 MB.',
            'archivo.required'  => 'Seleccione un archivo Word o PDF para subir.',
        ]);

        // Registrar si había articulado previo para el mensaje al usuario.
        $teniaArticulado = $regulacion->estructurada;

        $this->conversor->guardarOriginal($regulacion, $request->file('archivo'));
        $this->conversor->convertirAMarkdown($regulacion);
        $this->pdfConversor->invalidarCache($regulacion);

        // Invalidar el articulado existente: el archivo cambió, así que los
        // nodos (artículos, títulos, capítulos) del archivo anterior ya no
        // corresponden al contenido nuevo. Se marca estructurada=false para
        // que el show muestre el botón "Estructurar articulado" en vez del
        // árbol viejo. Los nodos no se borran aquí — se borran cuando el
        // usuario hace clic en "Estructurar" (importarDesdeMarkdown los
        // reemplaza en una transacción limpia con snapshot previo).
        if ($teniaArticulado) {
            $regulacion->update(['estructurada' => false]);
        }

        $mensaje = 'Archivo reemplazado y contenido reconvertido.';
        if ($teniaArticulado) {
            $mensaje .= ' El articulado anterior fue desactivado porque ya no corresponde al archivo nuevo. Use «Estructurar articulado» para reconstruirlo.';
        }

        return redirect()->route('regulaciones.show', $regulacion)
            ->with('success', $mensaje);
    }

    public function destroy(Regulacion $regulacion)
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para eliminar esta regulación.');
        }

        // Protección de integridad: no se puede borrar una regulación que
        // es citada como fundamento jurídico por trámites.
        $citaciones = $regulacion->citacionesEnTramites();
        if ($citaciones['total'] > 0) {
            $nombres = $citaciones['tramites']->pluck('nombre_oficial')->implode(', ');
            return back()->with('error',
                "No se puede eliminar: esta regulación es citada como fundamento jurídico por {$citaciones['total']} trámite(s): {$nombres}."
            );
        }

        // Con SoftDeletes, delete() marca el registro como eliminado (deleted_at)
        // pero NO borra los archivos físicos. Si el registro se restaura, los
        // archivos siguen disponibles. Los archivos solo se borran con forceDelete().
        $regulacion->delete();

        return redirect()->route('regulaciones.index')
            ->with('success', 'Regulación movida a papelera.');
    }

    /**
     * Descarga el archivo original de la regulación (Content-Disposition: attachment).
     * Es la acción del botón "Descargar" en la tarjeta del archivo.
     */
    public function descargarOriginal(Regulacion $regulacion)
    {
        if (empty($regulacion->archivo_original) || !Storage::disk('local')->exists($regulacion->archivo_original)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('local')->download($regulacion->archivo_original);
    }

    /**
     * Sirve el archivo original para vista previa en el iframe del show.
     * PDF: el navegador lo renderiza nativamente (Content-Disposition: inline).
     * DOCX/DOC: lee el Markdown ya convertido, lo parsea a HTML y lo sirve
     * como una página de lectura estilizada.
     * #2: lector embebido de regulaciones.
     */
    public function preview(Regulacion $regulacion)
    {
        // Sin archivo original, no hay nada que previsualizar.
        if (empty($regulacion->archivo_original)) {
            return $this->paginaError('Sin archivo', 'Esta regulación no tiene un archivo original asociado.');
        }

        // PDF original: servir inline para que el navegador lo renderice con
        // su visor nativo (zoom, búsqueda, impresión).
        if ($regulacion->extension_original === 'pdf') {
            if (!Storage::disk('local')->exists($regulacion->archivo_original)) {
                return $this->paginaError(
                    'Archivo no encontrado',
                    'El archivo PDF no existe en el servidor. Ruta esperada: ' . $regulacion->archivo_original
                );
            }
            return response()->file(Storage::disk('local')->path($regulacion->archivo_original));
        }

        // Word (DOC/DOCX): intentar servir como PDF si LibreOffice lo generó.
        // Esto permite al usuario ver el documento con fidelidad completa
        // (tablas, imágenes, formato) usando el visor PDF del navegador,
        // exactamente igual que si hubiera subido un PDF original.
        if ($this->pdfConversor->libreOfficeDisponible()) {
            try {
                $rutaPdf = $this->pdfConversor->obtenerOGenerarPdf($regulacion);
                return response()->file($rutaPdf, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . \Illuminate\Support\Str::slug($regulacion->nombre) . '.pdf"',
                ]);
            } catch (\Throwable $e) {
                // LibreOffice falló — continuar al fallback HTML del Markdown.
                \Illuminate\Support\Facades\Log::warning(
                    "Preview: LibreOffice falló para regulación #{$regulacion->id}, usando HTML. " . $e->getMessage()
                );
            }
        }

        // Fallback: convertir Markdown a HTML si aún no hay conversión.
        if (empty($regulacion->archivo_markdown) || !Storage::disk('local')->exists($regulacion->archivo_markdown)) {
            try {
                $this->conversor->convertirAMarkdown($regulacion);
                $regulacion->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Preview: error al convertir regulación #' . $regulacion->id . ': ' . $e->getMessage());
                return $this->paginaError(
                    'Error al convertir',
                    'No se pudo convertir el archivo Word a texto legible. Error: ' . $e->getMessage()
                );
            }
        }

        if (empty($regulacion->archivo_markdown) || !Storage::disk('local')->exists($regulacion->archivo_markdown)) {
            return $this->paginaError(
                'Conversión incompleta',
                'El archivo Word no generó contenido Markdown. '
                . 'Extensión: ' . ($regulacion->extension_original ?? 'desconocida')
                . '. Estatus: ' . ($regulacion->conversion_estatus ?? 'sin estatus')
                . '. Error: ' . ($regulacion->conversion_error ?? 'ninguno') . '.'
            );
        }

        $markdown = Storage::disk('local')->get($regulacion->archivo_markdown);

        if (trim($markdown) === '') {
            return $this->paginaError(
                'Documento sin texto extraíble',
                'El archivo Word fue procesado pero no se pudo extraer texto legible. '
                . 'Esto ocurre cuando el documento contiene solo imágenes, tablas complejas o campos de formulario sin texto.'
            );
        }

        $html = \Illuminate\Support\Str::markdown($markdown);

        $pagina = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>'
            . 'body{font-family:Georgia,serif;max-width:800px;margin:0 auto;padding:32px 24px;'
            . 'line-height:1.7;color:#1a1a1a;font-size:15px}'
            . 'h1,h2,h3,h4{font-family:sans-serif;margin-top:1.5em;color:#2d3748}'
            . 'h1{font-size:22px;border-bottom:2px solid #e2e8f0;padding-bottom:8px}'
            . 'h2{font-size:18px}h3{font-size:16px}h4{font-size:14px}'
            . 'p{margin:0.6em 0}strong{color:#1a202c}'
            . '</style></head><body>' . $html . '</body></html>';

        return response($pagina, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Devuelve una página HTML mínima con un mensaje de error para el iframe
     * de preview. Se usa en vez de abort() porque el iframe necesita contenido
     * HTML visible, no una redirección a la página de error del layout.
     */
    private function paginaError(string $titulo, string $detalle): \Illuminate\Http\Response
    {
        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<style>body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:24px;color:#333}'
            . 'h2{color:#b42318;font-size:18px;margin:0 0 12px}p{color:#666;line-height:1.6;font-size:14px}'
            . '.box{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:20px}</style>'
            . '</head><body><div class="box"><h2>' . e($titulo) . '</h2>'
            . '<p>' . e($detalle) . '</p></div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    /**
     * Descarga el contenido Markdown extraído de la regulación.
     *
     * Solo admin y revisora pueden descargar este archivo: es el texto
     * crudo que el conversor extrajo del PDF/Word, útil para revisión
     * interna y para alimentar el estructurador.
     */
    public function descargarMarkdown(Regulacion $regulacion)
    {
        if (!auth()->user()->veVariasDependencias()) {
            abort(403, 'Solo admin y revisora pueden descargar el Markdown.');
        }

        if (empty($regulacion->archivo_markdown) || !Storage::disk('local')->exists($regulacion->archivo_markdown)) {
            abort(404, 'No hay archivo Markdown disponible para esta regulación.');
        }

        $nombre = \Illuminate\Support\Str::slug($regulacion->nombre) . '.md';

        return Storage::disk('local')->download($regulacion->archivo_markdown, $nombre);
    }

    /**
     * Descarga el PDF de la regulación.
     *
     * La estrategia de generación (original PDF → LibreOffice → Dompdf)
     * vive en PdfConversorService. El controlador solo coordina la respuesta
     * HTTP — no sabe cómo se genera el PDF (Clean Code §11: separar capas).
     */
    public function descargarPdf(Regulacion $regulacion)
    {
        $nombre = \Illuminate\Support\Str::slug($regulacion->nombre) . '.pdf';

        try {
            $rutaAbsoluta = $this->pdfConversor->obtenerOGenerarPdf($regulacion);
            return response()->download($rutaAbsoluta, $nombre);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                "Error al descargar PDF de regulación #{$regulacion->id}: " . $e->getMessage()
            );
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Descarga la regulación en formato Word (DOCX).
     *
     * Si el archivo original ya es DOC/DOCX, lo sirve directamente.
     * Si el original es PDF, usa LibreOffice para convertirlo a DOCX
     * y lo cachea para descargas futuras.
     *
     * El botón que llama a esta ruta solo se muestra en la vista cuando
     * LibreOffice está disponible (validado en show() vía $tieneLibreOffice),
     * así que este método solo debería recibir peticiones válidas.
     */
    public function descargarDocx(Regulacion $regulacion)
    {
        $nombre = \Illuminate\Support\Str::slug($regulacion->nombre) . '.docx';

        try {
            $rutaAbsoluta = $this->pdfConversor->obtenerOGenerarDocx($regulacion);
            return response()->download($rutaAbsoluta, $nombre);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                "Error al descargar DOCX de regulación #{$regulacion->id}: " . $e->getMessage()
            );
            return back()->with('error', $e->getMessage());
        }
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
        if (!request()->user()->tienePermiso('regulaciones.editar')) {
            abort(403, 'No tiene permiso para reintentar la conversión.');
        }

        if (empty($regulacion->archivo_original)) {
            return back()->with('error', 'No hay archivo original para convertir.');
        }

        $regulacion->update([
            'conversion_estatus' => Regulacion::CONVERSION_PENDIENTE,
            'conversion_error'   => null,
        ]);

        $this->conversor->convertirAMarkdown($regulacion);
        $this->pdfConversor->invalidarCache($regulacion);

        return back()->with('success', 'Contenido reconvertido.');
    }

    /**
     * Estructura una regulación: si tiene un archivo original, primero lo
     * reconvierte a Markdown (aplicando las mejoras vigentes de limpieza de
     * texto), luego construye el árbol de nodos para poder editarla con el
     * editor jerárquico, y por último extrae automáticamente cualquier
     * definición legal que encuentre en los artículos recién estructurados
     * (para alimentar la respuesta destacada del buscador). Solo el
     * Jurídico de su dependencia (o el admin) puede hacerlo.
     */

    public function estructurar(Regulacion $regulacion)
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para estructurar esta regulación.');
        }

        // Reconvertir el archivo original antes de estructurar.
        //
        // ANTES, "Estructurar" y "Reintentar conversión" eran dos botones
        // independientes: estructurar solo leía el Markdown que ya estuviera
        // guardado en disco, sin importar qué tan viejo fuera. Si el sistema
        // mejoraba cómo se limpia el texto extraído (por ejemplo, corrigiendo
        // palabras pegadas por errores de extracción de PDF), una regulación
        // ya convertida ANTES de esa mejora seguía mostrando el texto viejo
        // sin importar cuántas veces se reestructurara — el usuario tenía que
        // saber, por su cuenta, que debía presionar "Reintentar conversión"
        // primero. Esto costó confusión real: se reestructuró varias veces
        // esperando ver una corrección de texto que nunca se aplicaba, porque
        // reestructurar nunca vuelve a tocar el archivo original.
        //
        // AHORA, si la regulación tiene un archivo original guardado, se
        // reconvierte automáticamente antes de construir el árbol de nodos.
        // Esto aplica siempre las mejoras vigentes de limpieza de texto
        // (espacios faltantes, palabras pegadas, y cualquier mejora futura)
        // sin que el usuario tenga que recordar el orden de dos botones.
        //
        // Si NO hay archivo original (caso raro: el archivo se perdió o la
        // regulación se armó sin uno), convertirAMarkdown() ya tiene su
        // propia validación interna y simplemente no hace nada — se sigue
        // usando el Markdown existente, igual que el comportamiento anterior.
        if (!empty($regulacion->archivo_original)) {
            $reconvertido = $this->conversor->convertirAMarkdown($regulacion);

            if ($reconvertido) {
                $this->pdfConversor->invalidarCache($regulacion);
            } else {
                return back()->with('error',
                    'No se pudo reconvertir el archivo original: '
                    . ($regulacion->conversion_error ?? 'error desconocido')
                    . '. Verifique el archivo o súbalo de nuevo.'
                );
            }
        }

        if (!$regulacion->conversionListaParaCitar()) {
            return back()->with('error', 'La regulación aún no tiene su contenido convertido. Use el botón «Reintentar conversión» primero.');
        }

        // Verificar que el Markdown es legible antes de intentar estructurar.
        // Si el texto está garbleado (caracteres mojibake por encoding roto),
        // el parser no encontrará encabezados y devolverá 0 elementos.
        // Mejor detectarlo aquí y dar un mensaje claro que dejarlo fallar silenciosamente.
        $markdown = $this->conversor->obtenerContenidoMarkdown($regulacion);
        if (empty($markdown)) {
            return back()->with('error', 'El archivo Markdown está vacío. Use el botón «Reintentar conversión» para regenerar el contenido.');
        }

        $scoreLegibilidad = $this->conversor->scoreLegibilidad($markdown);
        if ($scoreLegibilidad < 0.30) {
            return back()->with('error',
                'El contenido convertido no es legible (score: ' . round($scoreLegibilidad * 100) . '%). '
                . 'Esto ocurre cuando el archivo .doc tiene un formato que el sistema no pudo interpretar. '
                . 'Sugerencia: abra el archivo en Word, guárdelo como .docx, y súbalo de nuevo.'
            );
        }

        // Snapshot: guardar un resumen del articulado actual antes de
        // reconstruir, para poder comparar si algo cambió.
        if ($regulacion->estructurada) {
            $snapshot = $regulacion->nodos()
                ->select('id', 'tipo', 'numero', 'parent_id', 'orden')
                ->orderBy('orden')
                ->get()
                ->toArray();

            Storage::disk('local')->put(
                'regulaciones/snapshots/' . $regulacion->id . '-' . now()->format('Y-m-d-His') . '.json',
                json_encode([
                    'regulacion_id' => $regulacion->id,
                    'nombre'        => $regulacion->nombre,
                    'fecha'         => now()->toIso8601String(),
                    'usuario'       => request()->user()->name,
                    'nodos'         => $snapshot,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );

            $prefijo = 'regulaciones/snapshots/' . $regulacion->id . '-';
            $existentes = collect(Storage::disk('local')->files('regulaciones/snapshots'))
                ->filter(fn ($f) => str_starts_with($f, $prefijo))
                ->sort()
                ->values();

            if ($existentes->count() > 3) {
                $existentes->slice(0, $existentes->count() - 3)
                    ->each(fn ($f) => Storage::disk('local')->delete($f));
            }
        }

        // Análisis de impacto: detectar trámites afectados ANTES de re-estructurar.
        $citaciones = $regulacion->citacionesEnTramites();

        try {
            $creados = $this->estructurador->importarDesdeMarkdown($regulacion);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'Error al estructurar regulación #' . $regulacion->id . ': ' . $e->getMessage()
            );
            return back()->with('error',
                'Error al construir el articulado: ' . $e->getMessage()
                . '. Revise el log para más detalles.'
            );
        }

        if ($creados === 0) {
            return back()->with('error',
                'No se encontraron encabezados de artículos en el contenido (score de legibilidad: '
                . round($scoreLegibilidad * 100) . '%). '
                . 'El parser busca patrones como "Artículo 1.", "TÍTULO PRIMERO", "CAPÍTULO I". '
                . 'Si el documento no usa estos formatos, capture el articulado manualmente en el editor.'
            );
        }

        // Extraer definiciones legales automáticamente, ahora que el árbol
        // de nodos ya está construido.
        //
        // ANTES no existía este paso: para que el buscador mostrara una
        // respuesta destacada con la definición de un término, alguien
        // tenía que acordarse de correr un comando aparte después de cada
        // estructuración. Es el mismo problema que ya se resolvió arriba
        // con la reconversión automática — si el paso depende de que
        // alguien lo recuerde, tarde o temprano alguien no lo recuerda, y
        // el síntoma es silencioso: la respuesta destacada simplemente no
        // aparece, sin ningún error que avise por qué.
        //
        // AHORA se ejecuta automáticamente en el mismo momento en que la
        // regulación queda estructurada, usando los nodos que
        // importarDesdeMarkdown() acaba de crear.
        //
        // Va en su PROPIO try/catch, separado del de la estructuración de
        // arriba, a propósito: un fallo aquí no debe poder convertir una
        // estructuración que sí funcionó en un mensaje de error para el
        // usuario. Si algo sale mal extrayendo definiciones, se registra
        // como advertencia (no como error) y el flujo sigue normalmente
        // hacia el mensaje de éxito de la estructuración.
        $definicionesEncontradas = 0;
        try {
            $definicionesEncontradas = $this->extractorDefiniciones->extraerDeRegulacion($regulacion);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'No se pudieron extraer definiciones de la regulación #' . $regulacion->id . ': ' . $e->getMessage()
            );
        }

        if ($citaciones['total'] > 0) {
            $this->notificador->regulacionReEstructurada(
                $regulacion,
                request()->user(),
                $citaciones,
            );
        }

        $mensaje = "Regulación estructurada: {$creados} elementos detectados.";
        if ($definicionesEncontradas > 0) {
            $mensaje .= " Se encontraron {$definicionesEncontradas} definición(es) legal(es).";
        }
        if ($citaciones['total'] > 0) {
            $mensaje .= " Se notificó a los enlaces de {$citaciones['total']} trámite(s) afectado(s).";
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Pantalla del editor jerárquico de articulado. Solo accesible por el
     * jurídico de la dependencia (o admin) y para regulaciones ya estructuradas.
     */
    public function editor(Regulacion $regulacion, ?int $unidad = null)
    {
        if (!request()->user()->puedeEditarRegulacion($regulacion)) {
            abort(403, 'No tiene permiso para editar el articulado de esta regulación.');
        }

        if (!$regulacion->estructurada) {
            return redirect()->route('regulaciones.show', $regulacion)
                ->with('error', 'Primero estructura el articulado de esta regulación.');
        }

        // Árbol completo en memoria (una sola consulta), agrupado por padre.
        $regulacion->load(['nodos' => fn ($q) => $q->orderBy('orden')]);
        $hijosPorPadre = $regulacion->nodos->groupBy('parent_id');

        // Unidades de navegación: cada bloque de primer nivel editable de una en
        // una. Un "capítulo" es la unidad típica; cuando un título no tiene
        // capítulos (Transitorios, encabezado, títulos con artículos directos)
        // el propio nodo raíz hace de unidad.
        $unidades = $this->unidadesDeNavegacion($regulacion, $hijosPorPadre);

        // Análisis de impacto: qué trámites citan esta regulación y qué
        // artículos referencian. Se muestra como banner informativo para que
        // el usuario sepa qué puede afectar antes de editar.
        $citaciones = $regulacion->citacionesEnTramites();

        if ($unidades->isEmpty()) {
            // Sin estructura navegable: render plano (caso borde, regulación vacía).
            return view('screens.regulaciones.editor', [
                'regulacion'    => $regulacion,
                'hijosPorPadre' => $hijosPorPadre,
                'unidades'      => $unidades,
                'indiceActivo'  => null,
                'citaciones'    => $citaciones,
            ]);
        }

        // Unidad activa: la pedida por URL, o la primera. Se acota al rango válido.
        $indiceActivo = $unidades->search(fn ($u) => $u['id'] === $unidad);
        if ($indiceActivo === false) {
            $indiceActivo = 0;
        }

        return view('screens.regulaciones.editor', [
            'regulacion'    => $regulacion,
            'hijosPorPadre' => $hijosPorPadre,
            'unidades'      => $unidades,
            'indiceActivo'  => $indiceActivo,
            'citaciones'    => $citaciones,
        ]);
    }

    /**
     * Aplana el árbol en una lista ordenada de "unidades de navegación".
     * Cada unidad es un nodo (capítulo, o título/bloque sin capítulos) con su
     * título de grupo para el índice lateral. Devuelve una colección de arrays
     * ['id', 'nodo', 'grupo', 'etiqueta'].
     */
    private function unidadesDeNavegacion(Regulacion $regulacion, $hijosPorPadre)
    {
        $unidades = collect();
        $raices = $hijosPorPadre[null] ?? collect();

        foreach ($raices as $raiz) {
            $hijos = $hijosPorPadre[$raiz->id] ?? collect();
            $capitulos = $hijos->where('tipo', \App\Models\RegulacionNodo::TIPO_CAPITULO);

            if ($capitulos->isNotEmpty()) {
                // El título agrupa varios capítulos: cada capítulo es una unidad.
                foreach ($capitulos as $cap) {
                    $unidades->push([
                        'id'       => $cap->id,
                        'nodo'     => $cap,
                        'grupo'    => $this->etiquetaNodo($raiz),
                        'etiqueta' => $this->etiquetaNodo($cap),
                    ]);
                }
            } else {
                // Título/bloque sin capítulos (Transitorios, encabezado, etc.):
                // el propio nodo raíz es la unidad.
                $unidades->push([
                    'id'       => $raiz->id,
                    'nodo'     => $raiz,
                    'grupo'    => $this->etiquetaNodo($raiz),
                    'etiqueta' => $this->etiquetaNodo($raiz),
                ]);
            }
        }

        return $unidades->values();
    }

    /** Etiqueta legible de un nodo para el índice ("Capítulo II", "Título Primero"). */
    private function etiquetaNodo(\App\Models\RegulacionNodo $nodo): string
    {
        $partes = array_filter([
            $nodo->etiquetaTipo(),
            $nodo->numero,
        ]);
        $base = implode(' ', $partes);
        // Si el nodo trae texto corto (título descriptivo), añadirlo como pista.
        if ($nodo->texto && mb_strlen($nodo->texto) <= 80) {
            $base .= ' — ' . $nodo->texto;
        }
        return $base ?: $nodo->etiquetaTipo();
    }

    /**
     * Marca o desmarca una regulación como favorita del usuario autenticado.
     * Devuelve JSON con el nuevo estado para que el corazón cambie en la vista
     * sin recargar la página. Máximo 10 favoritos por usuario (#9).
     */
    public function toggleFavorita(Regulacion $regulacion)
    {
        $user = auth()->user();

        // Si la regulación NO es favorita aún, verificar el límite antes de agregar.
        $yaEsFavorita = $user->regulacionesFavoritas()
            ->where('regulaciones.id', $regulacion->id)
            ->exists();

        if (!$yaEsFavorita && $user->regulacionesFavoritas()->count() >= 10) {
            return response()->json([
                'favorita' => false,
                'error'    => 'Máximo 10 favoritos. Quita uno antes de agregar otro.',
            ], 422);
        }

        $resultado = $regulacion->usuariosQueLaFavoritaron()
            ->toggle($user->id);

        $esFavorita = in_array($user->id, $resultado['attached']);

        return response()->json(['favorita' => $esFavorita]);
    }

    // ── Papelera de regulaciones (#33) ─────────────────────────────────

    /**
     * Lista las regulaciones soft-deleted (en papelera).
     * Solo admin y revisora pueden ver y gestionar la papelera.
     */
    public function papeleraRegulaciones()
    {
        abort_unless(auth()->user()->veVariasDependencias(), 403);

        $regulaciones = Regulacion::onlyTrashed()
            ->with('dependencia')
            ->latest('deleted_at')
            ->paginate(20);

        return view('screens.regulaciones.papelera-regulaciones', compact('regulaciones'));
    }

    /**
     * Restaura una regulación de la papelera (revierte el soft delete).
     * Los archivos nunca se borraron (decisión de diseño del #36), así que
     * al restaurar todo queda funcional de inmediato.
     */
    public function restaurar(int $id)
    {
        abort_unless(auth()->user()->veVariasDependencias(), 403);

        $regulacion = Regulacion::onlyTrashed()->findOrFail($id);
        $regulacion->restore();

        return redirect()->route('regulaciones.papelera-regulaciones')
            ->with('success', "Regulación «{$regulacion->nombre}» restaurada.");
    }

    /**
     * Elimina una regulación de forma permanente (forceDelete).
     * AHORA sí se borran los archivos físicos (PDF/Word + Markdown),
     * porque la eliminación es irreversible.
     */
    public function eliminarDefinitivo(int $id)
    {
        abort_unless(auth()->user()->veVariasDependencias(), 403);

        $regulacion = Regulacion::onlyTrashed()->findOrFail($id);

        // Borrar archivos físicos antes del forceDelete: primero el PDF cacheado
        // (PdfConversorService sabe dónde está según config), luego el original
        // y el Markdown (RegulacionConversorService).
        $this->pdfConversor->invalidarCache($regulacion);
        $this->conversor->eliminarArchivos($regulacion);

        $nombre = $regulacion->nombre;
        $regulacion->forceDelete();

        return redirect()->route('regulaciones.papelera-regulaciones')
            ->with('success', "Regulación «{$nombre}» eliminada permanentemente.");
    }
}
