<?php

namespace App\Jobs;

use App\Models\Regulacion;
use App\Models\RegulacionNodo;
use App\Models\User;
use App\Services\DefinitionExtractorService;
use App\Services\DetectorCatalogosService;
use App\Services\NotificadorService;
use App\Services\PdfConversorService;
use App\Services\RegulacionConversorService;
use App\Services\RegulacionEstructuradorService;
use App\Services\RegulacionPaginadorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Construye el articulado de una regulación a partir de su Markdown, y todo lo que va con eso.
 *
 * ── Por qué existe este job ──────────────────────────────────────────
 *
 * En el controlador, "Estructurar articulado" hacía DOS cosas seguidas:
 *
 *   1. Reconvertir el archivo original a Markdown.
 *   2. Construir el árbol de nodos a partir de ese Markdown.
 *
 * El paso 1 estaba ahí por una razón concreta, que el comentario del controlador explicaba: una
 * regulación convertida ANTES de una mejora del limpiador de texto seguía mostrando el texto
 * viejo por más veces que se reestructurara, y el usuario tenía que saber, por su cuenta, que
 * debía apretar "Reintentar conversión" primero. Eso costó confusión real, y alguien lo arregló
 * uniendo las dos cosas en un solo botón.
 *
 * Al pasar la conversión a segundo plano, ese arreglo se rompería: el controlador despacharía la
 * conversión, seguiría de largo, e intentaría estructurar el Markdown viejo.
 *
 * La solución no es quitar el paso: es ENCADENARLO.
 *
 *     Bus::chain([
 *         new ConvertirRegulacionJob($regulacion),
 *         new EstructurarRegulacionJob($regulacion, $usuarioId),
 *     ])->dispatch();
 *
 * Este job es el segundo eslabón. Solo arranca si la conversión terminó bien.
 *
 * ── LAS CINCO TAREAS, Y POR QUÉ NINGUNA ES OPCIONAL ──────────────────
 *
 * Al mover la estructuración a la cola hay que mover TODO lo que el controlador hacía después
 * de importar. Si alguna se queda atrás, deja de ocurrir en el camino normal — y nadie lo nota
 * hasta meses después, porque ninguna de ellas produce un error cuando falta:
 *
 *   1. SNAPSHOT del articulado anterior. Reestructurar destruye el árbol entero. Si el parseo
 *      nuevo sale PEOR que el viejo (pasa: una mejora del limpiador puede romper un caso que
 *      antes funcionaba), sin la foto previa no hay forma de saber qué se perdió.
 *
 *   2. CITACIONES, calculadas ANTES de tocar nada. Después ya no se sabría qué artículos
 *      citaban los trámites: el árbol viejo ya no existiría.
 *
 *   3. IMPORTAR el articulado.
 *
 *   4. EXTRAER LAS DEFINICIONES legales. El propio controlador explicaba por qué es automático:
 *      "si el paso depende de que alguien lo recuerde, tarde o temprano alguien no lo recuerda,
 *      y el síntoma es silencioso — la respuesta destacada simplemente no aparece, sin ningún
 *      error que avise por qué".
 *
 *   5. AVISAR a los enlaces de los trámites que citan la regulación.
 *
 * ── Quién disparó esto ───────────────────────────────────────────────
 *
 * En el controlador, la notificación usaba request()->user(). Dentro de un job NO HAY PETICIÓN:
 * no hay usuario autenticado, y auth()->user() devuelve null. Por eso el ID se pasa al
 * construirlo. Sin eso, el aviso diría que la reestructuró "nadie".
 */
class EstructurarRegulacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Un solo intento.
     *
     * A diferencia de la conversión —donde un reintento puede salvar un fallo transitorio
     * (LibreOffice que no arrancó, disco ocupado)—, estructurar es determinista: lee un texto y
     * construye un árbol. Si falla, fallará igual la segunda vez. Reintentar solo destruiría el
     * articulado dos veces.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Regulacion $regulacion,
        public ?int $usuarioId = null,
    ) {}

    public function handle(
        RegulacionEstructuradorService $estructurador,
        DefinitionExtractorService $extractorDefiniciones,
        NotificadorService $notificador,
        PdfConversorService $pdfConversor,
        DetectorCatalogosService $detectorCatalogos,
        RegulacionConversorService $conversor,
        RegulacionPaginadorService $paginador,
    ): void {
        $regulacion = Regulacion::find($this->regulacion->id);

        if (! $regulacion) {
            Log::warning('Se iba a estructurar una regulación que ya no existe.', [
                'regulacion_id' => $this->regulacion->id,
            ]);

            return;
        }

        // Guardia: no se construye un árbol sobre un texto que no está listo.
        //
        // No debería ocurrir —la cadena solo llega aquí si la conversión terminó bien—, pero un
        // job se ejecuta en un mundo que pudo cambiar desde que se encoló. Es más barato
        // comprobarlo que depurar un articulado construido sobre un Markdown a medias.
        if (! $regulacion->conversionListaParaCitar()) {
            $this->registrarError(
                $regulacion,
                'No se pudo construir el articulado porque el contenido del archivo no está '
                . 'convertido. Use el botón «Reintentar conversión» y vuelva a intentarlo.'
            );

            return;
        }

        // Se limpia el error de la vez anterior. Si no, un reintento que sale bien dejaría el
        // mensaje viejo en pantalla: la regulación tendría su articulado Y un aviso rojo
        // diciendo que la estructuración falló. Contradictorio y desconcertante.
        $regulacion->update(['estructuracion_error' => null]);

        // (1) Foto del articulado anterior, antes de destruirlo.
        $this->guardarSnapshot($regulacion);

        // (2) Quién cita esta regulación, y qué artículos.
        //     SE CALCULA AHORA, no después: importarDesdeMarkdown() borra el árbol entero y lo
        //     recrea, y entonces ya no se sabría qué se estaba citando.
        $citaciones = $regulacion->citacionesEnTramites();

        // (3) El articulado.
        $creados = $estructurador->importarDesdeMarkdown($regulacion);

        // ── ¿SE CONSTRUYÓ UN ARTICULADO, O SOLO UN MONTÓN DE PÁRRAFOS? ──
        //
        // Ojo con la trampa: NO basta con comprobar $creados > 0.
        //
        // El estructurador vuelca cualquier línea de texto suelta como un nodo "párrafo". Un
        // documento de texto corrido, sin un solo artículo, produce DECENAS de nodos — y por
        // tanto $creados sale alto y el sistema lo da por bueno.
        //
        // El usuario vería entonces un "articulado" que no es un articulado: una lista de
        // párrafos colgando de la nada, sin ningún artículo que citar. Y sin ningún aviso,
        // porque técnicamente no falló nada.
        //
        // Lo que hace útil a una regulación estructurada es poder CITARLA: "Artículo 15,
        // fracción II". Si no hay artículos, no hay nada que citar, y todo el articulado no
        // sirve para el único propósito por el que existe.
        //
        // Por eso la pregunta correcta no es "¿se creó algo?", sino "¿se creó algún ARTÍCULO?".
        $articulos = $regulacion->nodos()
            ->where('tipo', RegulacionNodo::TIPO_ARTICULO)
            ->count();

        if ($articulos === 0) {
            // Esto NO es un error técnico: el sistema funcionó perfectamente y no encontró
            // artículos. El documento simplemente no usa los formatos que el parser reconoce.
            //
            // Por eso el mensaje no dice "error interno" ni pide reintentar: dice exactamente qué
            // busca el parser y qué hacer si el documento no lo usa. Un mensaje que no lleva a una
            // acción concreta es casi tan inútil como el silencio — el usuario sabe que algo se
            // rompió y sigue sin saber qué hacer.
            $this->registrarError(
                $regulacion,
                'No se encontró ningún artículo en el texto del documento. '
                . 'El sistema reconoce formatos como «Artículo 1.», «TÍTULO PRIMERO» y «CAPÍTULO I». '
                . 'Si el documento no los usa, capture el articulado a mano desde el editor.'
            );

            return;
        }

        // La vista previa en PDF se cachea. Si el articulado cambió, esa caché ya no refleja el
        // documento.
        $pdfConversor->invalidarCache($regulacion);

        // (4) Definiciones legales.
        $this->extraerDefiniciones($extractorDefiniciones, $regulacion);

        // (5) El aviso a los trámites afectados.
        $this->avisarATramitesCitantes($notificador, $regulacion, $citaciones);

        // (6) Detección de artículos-catálogo con IA.
        //
        // Marca los artículos que son TABLAS DE REFERENCIA (escalas de sanción, catálogos,
        // tarifas, definiciones) que otros artículos necesitan para entenderse. Ver
        // DetectorCatalogosService.
        //
        // Corre AQUÍ —fuera de la transacción de importarDesdeMarkdown— a propósito: son cientos
        // de llamadas a la IA y no pueden tener una transacción de base de datos abierta mientras
        // esperan. Y corre DESPUÉS de que el árbol está guardado, porque necesita leer los
        // artículos ya creados.
        //
        // Es una MEJORA, no un requisito: si la IA falla, la ley queda cargada y buscable, solo
        // que sin el cruce de cadenas (el buscador encuentra la conducta, pero no salta al
        // catálogo). Nunca bloquea la estructuración.
        $this->detectarCatalogos($detectorCatalogos, $regulacion);

        // Recupera del PDF las tablas que pdftotext destruyó (el Catálogo de
        // Infracciones) y las vuelca, legibles, en el nodo-catálogo que el detector
        // acaba de marcar. Cierra el cruce: conducta → clase → escala. Va después del
        // detector porque necesita el nodo ya marcado; misma disciplina defensiva.
        $this->repararTablasCatalogo($conversor, $detectorCatalogos, $regulacion);

        // (7) Página de cada artículo dentro del PDF original.
        //
        // Permite que un resultado del buscador abra el PDF en el lugar correcto
        // (#page=N). Igual que la detección de catálogos: es una MEJORA, solo aplica
        // a PDFs, y está protegida —si falla, la ley queda estructurada y buscable,
        // solo que sus resultados abren el PDF en la página 1 en vez de la exacta—.
        $this->detectarPaginas($paginador, $regulacion);

        // Sello de éxito: se estructuró con la versión vigente del pipeline. Solo se llega
        // aquí si todos los pasos corrieron; si algo falló antes, NO se sella, y la ley
        // queda marcada como desactualizada (ver Regulacion::estaDesactualizada). Es la
        // red contra "cambié el código y olvidé re-estructurar".
        $regulacion->update([
            'pipeline_version' => Regulacion::PIPELINE_VERSION,
            'estructurado_en'  => now(),
        ]);

        Log::info('Regulación estructurada en segundo plano.', [
            'regulacion_id'     => $regulacion->id,
            'nodos_creados'     => $creados,
            'articulos'         => $articulos,
            'tramites_avisados' => $citaciones['total'],
        ]);
    }

    /**
     * Guarda una foto del articulado antes de destruirlo, y conserva las tres últimas.
     *
     * Si el parseo nuevo sale PEOR que el viejo, esta foto es lo único que permite saber qué se
     * perdió. Solo se conservan tres: son fotos de depuración, no un historial legal. Sin ese
     * barrido, una regulación reestructurada cien veces deja cien archivos JSON en el disco.
     */
    private function guardarSnapshot(Regulacion $regulacion): void
    {
        if (! $regulacion->estructurada) {
            return; // no hay articulado que fotografiar
        }

        try {
            $nodos = $regulacion->nodos()
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
                    'usuario'       => $this->nombreDelUsuario(),
                    'nodos'         => $nodos,
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
        } catch (Throwable $e) {
            // Un snapshot que falla no puede impedir la estructuración. Pero SÍ tiene que dejar
            // rastro: si los snapshots dejan de guardarse en silencio, el día que haga falta uno
            // no estará, y nadie sabrá desde cuándo.
            Log::warning('No se pudo guardar el snapshot del articulado.', [
                'regulacion_id' => $regulacion->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extrae las definiciones legales del articulado recién construido.
     *
     * Va en su propio try/catch, separado del resto. Un fallo aquí NO puede convertir una
     * estructuración que sí funcionó en un error: el articulado ya está construido y es válido.
     * Lo único que se pierde son las respuestas destacadas del buscador.
     */
    private function extraerDefiniciones(DefinitionExtractorService $extractor, Regulacion $regulacion): void
    {
        try {
            $encontradas = $extractor->extraerDeRegulacion($regulacion);

            Log::info("Definiciones legales extraídas: {$encontradas}.", [
                'regulacion_id' => $regulacion->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudieron extraer las definiciones legales.', [
                'regulacion_id' => $regulacion->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca los artículos-catálogo con IA, para que el asistente pueda cruzar cadenas de
     * artículos (ej. "multa por obstruir banqueta" = conducta art. 65 + catálogo 105 + escala 104).
     *
     * Mismo patrón defensivo que extraerDefiniciones: si la IA falla, se registra y se sigue. La
     * ley queda cargada y buscable; solo se pierde el cruce de cadenas, que es una mejora.
     */
    private function detectarCatalogos(DetectorCatalogosService $detector, Regulacion $regulacion): void
    {
        try {
            $marcados = $detector->detectarYMarcar($regulacion);

            Log::info("Artículos-catálogo detectados: {$marcados}.", [
                'regulacion_id' => $regulacion->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudieron detectar los artículos-catálogo.', [
                'regulacion_id' => $regulacion->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detecta en qué página del PDF original aparece cada artículo y la guarda.
     *
     * Mismo patrón defensivo que detectarCatalogos: una MEJORA, nunca un requisito.
     * El propio servicio ya se salta las regulaciones que no son PDF; este envoltorio
     * solo garantiza que, si algo revienta al leer el PDF, la estructuración no caiga
     * con él. Sin páginas, el buscador simplemente abre el PDF en la página 1.
     */
    private function detectarPaginas(RegulacionPaginadorService $paginador, Regulacion $regulacion): void
    {
        try {
            $exactas = $paginador->detectarPaginas($regulacion);

            Log::info("Páginas de artículos detectadas: {$exactas} exactas.", [
                'regulacion_id' => $regulacion->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudieron detectar las páginas de los artículos.', [
                'regulacion_id' => $regulacion->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recupera las tablas-catálogo del PDF y las vuelca en el nodo que el detector
     * marcó. Es lo que da al asistente el cruce legible "artículo 65 → Clase D".
     *
     * Mismo patrón defensivo que detectarCatalogos: una MEJORA, nunca un requisito.
     * Si Python no está, el PDF no trae tablas, o algo falla, la ley queda cargada
     * igual —solo sin el cruce recuperado—. Solo aplica a PDFs: pdfplumber saca
     * tablas de un PDF; una ley en Word no tiene aquí un PDF del que sacarlas.
     */
    private function repararTablasCatalogo(
        RegulacionConversorService $conversor,
        DetectorCatalogosService $detector,
        Regulacion $regulacion
    ): void {
        // Solo PDFs: pdfplumber saca tablas de un PDF. Se mira la extensión del
        // propio archivo original —más fiable que la columna extension_original, que
        // podría no estar puesta y haría que este paso se saltara en silencio—.
        $rutaOriginal = (string) $regulacion->archivo_original;
        if (! str_ends_with(strtolower($rutaOriginal), '.pdf')) {
            return;
        }

        try {
            $rutaPdf = Storage::disk('local')->path($regulacion->archivo_original);
            $pares   = $conversor->extraerTablasCatalogo($rutaPdf);

            if ($pares === []) {
                return; // el PDF no traía tablas legibles, o Python no está disponible.
            }

            $reparados = $detector->repararCatalogoConTabla($regulacion, $pares);

            Log::info("Tablas-catálogo recuperadas en {$reparados} artículo(s).", [
                'regulacion_id' => $regulacion->id,
                'pares'         => count($pares),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudieron recuperar las tablas-catálogo.', [
                'regulacion_id' => $regulacion->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Avisa a los enlaces y creadores de los trámites que citan esta regulación.
     *
     * ── Por qué esto es lo más importante del job ──
     *
     * Los trámites guardan su fundamento jurídico como TEXTO ("Artículo 15, fracción II"), no
     * como una referencia al nodo. Eso es bueno: al reestructurar, las citas sobreviven en vez
     * de romperse todas.
     *
     * Pero sobreviven apuntando a un artículo que puede decir OTRA COSA. El "Artículo 15" del
     * reglamento nuevo quizá ya no hable de licencias, sino de horarios. La cita sigue siendo
     * válida sintácticamente y falsa en el fondo.
     *
     * Ningún sistema puede detectar eso solo. Hace falta que una PERSONA lea el artículo nuevo y
     * decida si el trámite sigue bien fundamentado. Y para que lo lea, hay que avisarle.
     *
     * Este aviso es lo único que hay entre una reestructuración y un trámite mal fundamentado en
     * silencio.
     */
    private function avisarATramitesCitantes(
        NotificadorService $notificador,
        Regulacion $regulacion,
        array $citaciones,
    ): void {
        if ($citaciones['total'] === 0) {
            return;
        }

        $usuario = $this->usuarioId ? User::find($this->usuarioId) : null;

        if (! $usuario) {
            // Sin usuario no se puede firmar el aviso. Pero eso NO es motivo para no avisar: un
            // aviso sin firma vale infinitamente más que ningún aviso.
            Log::warning('Se va a notificar una reestructuración sin saber quién la disparó.', [
                'regulacion_id' => $regulacion->id,
                'usuario_id'    => $this->usuarioId,
            ]);
        }

        try {
            $notificador->regulacionReEstructurada($regulacion, $usuario, $citaciones);
        } catch (Throwable $e) {
            // El aviso falló. La estructuración fue bien, así que no se deshace nada — pero esto
            // queda con nivel ERROR, no warning: significa que hay trámites que pueden estar mal
            // fundamentados y NADIE lo sabe.
            Log::error('Falló el aviso a los trámites que citan una regulación reestructurada.', [
                'regulacion_id' => $regulacion->id,
                'tramites'      => $citaciones['total'],
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function nombreDelUsuario(): string
    {
        return $this->usuarioId
            ? (User::find($this->usuarioId)?->name ?? 'Usuario desconocido')
            : 'Sistema';
    }

    /**
     * Deja el motivo del fallo DONDE EL USUARIO LO VA A MIRAR: en la ficha de la regulación.
     *
     * Antes de esto, un fallo de estructuración solo dejaba una línea en el log. Y el log no lo
     * lee nadie que esté esperando su articulado.
     *
     * El usuario daba a "Estructurar", veía "la página se actualizará sola cuando termine", y la
     * página se refrescaba eternamente. La conversión sí había ido bien, así que la regulación se
     * veía normal, con su botón de "Estructurar" invitando a darle otra vez. Nada indicaba que
     * algo hubiera fallado.
     *
     * El log sigue escribiéndose —hace falta para depurar—, pero ya no es lo único.
     */
    private function registrarError(Regulacion $regulacion, string $mensaje): void
    {
        $regulacion->update(['estructuracion_error' => $mensaje]);

        Log::warning('No se pudo construir el articulado de una regulación.', [
            'regulacion_id' => $regulacion->id,
            'nombre'        => $regulacion->nombre,
            'motivo'        => $mensaje,
        ]);
    }

    /**
     * Si la estructuración falla, la CONVERSIÓN sigue siendo válida: el texto se extrajo bien,
     * lo que falló fue el parser al construir el árbol.
     *
     * Por eso NO se toca conversion_estatus. Marcarlo como error sería mentir: diría que el
     * archivo no se pudo leer, cuando se leyó perfectamente. El usuario tiraría el archivo y
     * subiría otro sin ninguna necesidad.
     */
    public function failed(Throwable $e): void
    {
        // El modelo puede haber desaparecido mientras el job esperaba en la cola. Recargarlo con
        // cuidado evita que el manejador de errores lance su propia excepción y tumbe al worker.
        $regulacion = Regulacion::find($this->regulacion->id);

        if (! $regulacion) {
            Log::warning('Falló la estructuración, pero la regulación ya no existe.', [
                'regulacion_id' => $this->regulacion->id,
            ]);

            return;
        }

        // ── LA CONVERSIÓN SIGUE SIENDO VÁLIDA ──
        //
        // Aquí NO se toca conversion_estatus, y es importante entender por qué.
        //
        // Si la estructuración falla, el texto se extrajo perfectamente: lo que falló fue el
        // parser al construir el árbol. Marcar la conversión como error sería MENTIR — diría que
        // el archivo no se pudo leer, cuando se leyó bien. Y el usuario tiraría el archivo y
        // subiría otro sin ninguna necesidad.
        //
        // Un mensaje de error que apunta al problema equivocado hace perder más tiempo que no
        // dar ninguno.
        $regulacion->update([
            'estructuracion_error' => 'Error interno al construir el articulado. '
                . 'El contenido del documento sí se extrajo correctamente, así que puede consultarlo '
                . 'y descargarlo con normalidad. Si el problema persiste, capture el articulado a mano '
                . 'desde el editor.',
        ]);

        Log::error('Falló la estructuración de una regulación en segundo plano.', [
            'regulacion_id' => $regulacion->id,
            'nombre'        => $regulacion->nombre,
            'error'         => $e->getMessage(),
        ]);
    }
}
