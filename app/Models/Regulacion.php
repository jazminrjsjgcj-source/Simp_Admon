<?php

namespace App\Models;

use App\Models\Concerns\GeneraFolio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Regulacion extends Model
{
    /**
     * HasFactory es lo que habilita Regulacion::factory() en las pruebas.
     *
     * Lo tienen Tramite, AccionAgenda, Dependencia, UnidadAdministrativa y User;
     * a este modelo se le había pasado. Sin el trait, Regulacion::factory() no
     * existe como método, y cualquier prueba que necesite una regulación tendría
     * que escribir sus veinte columnas a mano.
     *
     * No cambia nada en producción: solo añade el punto de entrada de las factories.
     */
    use GeneraFolio, HasFactory, SoftDeletes;

    protected $table   = 'regulaciones';

    /**
     * Columnas asignables en masa (sin id, timestamps ni deleted_at). Incluye
     * los campos de conversión a Markdown y el índice JSON. folio lo asigna
     * GeneraFolio. Reconstruido desde las migraciones de regulaciones.
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'dependencia_id',
        'sector_id',
        'fecha_publicacion',
        'fecha_vigencia',
        'estatus',
        'archivo_pdf',
        'resumen',
        'created_by',
        'folio',
        'archivo_original',
        'archivo_markdown',
        'conversion_estatus',
        'conversion_error',

        // Por qué falló la construcción del articulado. Null si fue bien o si nunca se intentó.
        //
        // Existe por el mismo motivo que conversion_error: un fallo que solo queda en el log no
        // lo lee nadie que esté esperando su articulado. Desde que la estructuración ocurre en
        // segundo plano, el usuario daba a "Estructurar", veía "la página se actualizará sola", y
        // la página se refrescaba eternamente sin que nada indicara que algo salió mal.
        'estructuracion_error',

        'extension_original',
        'materia',
        'fundamento_juridico',
        'objetivo',
        'palabras_clave',
        'deroga_otra',
        'regulacion_derogada',
        'indice',
        'estructurada',
    ];

    /** Prefijo de tipo para el folio: LPZ-REG-... */
    protected function folioTipo(): string { return 'REG'; }

    /**
     * #8: genera el folio automáticamente al crear la regulación.
     * A diferencia de AccionAgenda y PropuestaRegulatoria (que generan folio
     * al dejar borrador), las regulaciones no tienen estado "borrador" — se
     * crean directamente como vigentes o en revisión, así que el folio se
     * asigna al momento de la creación.
     */
    protected static function booted(): void
    {
        static::creating(function (Regulacion $regulacion) {
            if (empty($regulacion->folio)) {
                $regulacion->load('dependencia');
                $regulacion->folio = $regulacion->generarFolio();
            }
        });
    }

    /** Estatus de la regulación. */
    public const ESTATUS_VIGENTE     = 'vigente';
    public const ESTATUS_EN_REVISION = 'en_revision';
    public const ESTATUS_DEROGADA    = 'derogada';

    /**
     * Estado CALCULADO al vuelo (no se guarda en BD). Una regulación 'vigente'
     * cuya fecha de vencimiento ya pasó se reporta como 'vencida' mediante
     * estatusEfectivo(). No forma parte de ESTATUS_TODOS porque no es un valor
     * que el usuario asigne ni que se almacene.
     */
    public const ESTATUS_VENCIDA     = 'vencida';

    public const ESTATUS_TODOS = [
        self::ESTATUS_VIGENTE,
        self::ESTATUS_EN_REVISION,
        self::ESTATUS_DEROGADA,
    ];

    /** Conversión a Markdown. */
    public const CONVERSION_PENDIENTE  = 'pendiente';
    public const CONVERSION_PROCESANDO = 'procesando';
    public const CONVERSION_LISTO      = 'listo';
    public const CONVERSION_ERROR      = 'error';

    public const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'doc'];

    /**
     * MIME types válidos para archivos de regulación. Se usan como segunda
     * capa de validación (la primera es la extensión). Un archivo .jpg
     * renombrado a .docx pasaría la validación de extensión pero fallaría
     * la de MIME type — este array lo atrapa.
     *
     * application/msword              → .doc (Word 97–2003, formato binario OLE)
     * application/vnd.openxml...      → .docx (Word 2007+, formato ZIP/XML)
     * application/pdf                 → .pdf
     * application/octet-stream        → fallback de Windows para .doc en algunos casos
     */
    public const MIME_TYPES_PERMITIDOS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream',
    ];

    /** Mensaje de error centralizado para validación de archivos. */
    public const ARCHIVO_ERROR_TIPO = 'El archivo debe ser Word (.doc, .docx) o PDF (.pdf). No se permiten otros formatos.';

    /** Materias disponibles (Art. 153 fracc. II Lineamientos). */
    public const MATERIAS = [
        'Comercio',
        'Desarrollo Urbano',
        'Protección Civil',
        'Seguridad',
        'Medio Ambiente',
        'Hacienda',
        'Gobierno',
        'Digitalización',
        'Servicios Públicos',
        'Tránsito y Transporte',
        'Salud',
        'Educación',
        'Otra',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'fecha_vigencia'    => 'date',
        'deroga_otra'       => 'boolean',
        'indice'            => 'array',
        'estructurada'      => 'boolean',
    ];

    // ========== Relaciones ==========

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
    public function creador()     { return $this->belongsTo(User::class, 'created_by'); }
    public function sector()      { return $this->belongsTo(SectorScian::class, 'sector_id'); }

    /** Todos los nodos del articulado (árbol del editor). */
    public function nodos()
    {
        return $this->hasMany(RegulacionNodo::class)->orderBy('orden');
    }

    /** Solo los nodos raíz (sin padre), ordenados; punto de entrada del árbol. */
    public function nodosRaiz()
    {
        return $this->hasMany(RegulacionNodo::class)->whereNull('parent_id')->orderBy('orden');
    }

    /**
     * Usuarios que marcaron esta regulación como favorita (tabla pivot
     * regulacion_favorita). Relación inversa de User::regulacionesFavoritas().
     */
    public function usuariosQueLaFavoritaron()
    {
        return $this->belongsToMany(User::class, 'regulacion_favorita')
            ->withTimestamps();
    }

    // ========== Helpers de estado ==========

    public function conversionListaParaCitar(): bool
    {
        return $this->conversion_estatus === self::CONVERSION_LISTO
            && !empty($this->archivo_markdown);
    }

    /**
     * ¿La regulación ya venció? Es decir, tiene fecha de vigencia (entendida
     * como fecha de VENCIMIENTO) y esa fecha ya quedó en el pasado.
     *
     * Se calcula al vuelo desde la fecha real: no depende de ningún proceso
     * programado ni de un campo que pueda quedar desincronizado.
     */
    public function estaVencida(): bool
    {
        return $this->fecha_vigencia !== null
            && $this->fecha_vigencia->isPast();
    }

    /**
     * Estatus EFECTIVO de la regulación, considerando el vencimiento.
     *
     * Una regulación marcada 'vigente' cuya fecha de vencimiento ya pasó deja
     * de estar vigente de hecho: se reporta como 'vencida'. El estatus
     * almacenado en BD no se modifica (cálculo al vuelo); esta es la fuente
     * única de verdad para mostrar el estado real en cualquier vista.
     */
    public function estatusEfectivo(): string
    {
        if ($this->estatus === self::ESTATUS_VIGENTE && $this->estaVencida()) {
            return self::ESTATUS_VENCIDA;
        }
        return $this->estatus;
    }

    public function estaVigente(): bool
    {
        return $this->estatus === self::ESTATUS_VIGENTE && !$this->estaVencida();
    }

    public function tieneIndice(): bool
    {
        return !empty($this->indice) && is_array($this->indice);
    }

    /**
     * ¿Falló el último intento de construir el articulado?
     *
     * Ojo con lo que esto NO significa: no significa que la CONVERSIÓN haya fallado. Son dos
     * cosas distintas y hay que mantenerlas separadas.
     *
     *   conversion_error       → el texto del archivo no se pudo extraer.
     *   estructuracion_error   → el texto está bien, pero no se pudo construir el árbol de
     *                            artículos a partir de él.
     *
     * En el segundo caso, la regulación es perfectamente utilizable: se puede leer, descargar y
     * consultar. Lo único que falta es el articulado navegable.
     *
     * Confundir las dos cosas tiene un coste concreto: si el sistema dijera "error de conversión"
     * cuando lo que falló fue el parser, el usuario tiraría el archivo y subiría otro sin ninguna
     * necesidad. Un mensaje de error que apunta al problema equivocado hace perder más tiempo que
     * no dar ninguno.
     */
    public function estructuracionFallo(): bool
    {
        return ! empty($this->estructuracion_error);
    }

    /**
     * Devuelve las palabras clave como array limpio.
     */
    public function palabrasClaveComoArray(): array
    {
        if (empty($this->palabras_clave)) {
            return [];
        }

        return array_map('trim', explode(',', $this->palabras_clave));
    }

    // ── Análisis de impacto ────────────────────────────────────────────────

    /**
     * Devuelve un resumen de qué trámites citan esta regulación como
     * fundamento jurídico y qué artículos/fracciones referencian.
     *
     * Se usa para:
     * - Bloquear el borrado si hay trámites que dependen de ella.
     * - Mostrar un aviso de impacto en el editor antes de re-estructurar.
     * - Avisar al reemplazar el archivo.
     *
     * @return array{total: int, tramites: Collection, articulos: array}
     */
    public function citacionesEnTramites(): array
    {
        $fundamentos = FundamentoJuridico::where('regulacion_id', $this->id)
            ->with('tramite:id,nombre_oficial,homoclave,enlace_id,created_by')
            ->get();

        if ($fundamentos->isEmpty()) {
            return ['total' => 0, 'tramites' => collect(), 'articulos' => []];
        }

        $tramites = $fundamentos
            ->pluck('tramite')
            ->filter()
            ->unique('id')
            ->values();

        $articulos = $fundamentos
            ->pluck('articulo_fraccion')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return [
            'total'     => $tramites->count(),
            'tramites'  => $tramites,
            'articulos' => $articulos,
        ];
    }
}
