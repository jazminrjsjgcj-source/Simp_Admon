<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nodo del árbol de articulado de una regulación (editor del Jurídico).
 *
 * Cada nodo es un elemento de la estructura legal: capítulo, título, sección,
 * artículo, fracción, inciso o párrafo. La jerarquía se arma con parent_id y el
 * orden entre hermanos con `orden`. Un nodo derogado se marca (estado =
 * derogado) pero NO se borra ni se renumera: permanece en su lugar.
 *
 * Reglas de negocio confirmadas:
 *  - Numeración SUGERIDA (no impuesta): ver siguienteNumeroSugerido().
 *  - Derogación SIN cascada: derogar un nodo no toca a sus hijos.
 *  - Permisos por dependencia: se controlan en el controlador, vía la
 *    regulación dueña del nodo.
 */
class RegulacionNodo extends Model
{
    use SoftDeletes;

    protected $table = 'regulacion_nodos';

    protected $fillable = [
        'regulacion_id',
        'parent_id',
        'tipo',
        'numero',
        'texto',

        // El texto de los ancestros estructurales (título, capítulo, sección).
        //
        // Existe porque UN ARTÍCULO NO REPITE EL TÍTULO DE SU CAPÍTULO. El artículo 26 nunca dice
        // "patrimonio": ya está dentro del capítulo "IMPUESTOS SOBRE EL PATRIMONIO". Sin esta
        // columna, el buscador —que solo mira el texto del nodo— es incapaz de encontrarlo.
        //
        // Lo rellena RegulacionEstructuradorService al construir el árbol.
        'contexto',
        'orden',
        'estado',
        'derogado_nota',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * Longitud máxima de la columna `numero` (espejo del VARCHAR(60) de la migración).
     * Toda asignación al campo `numero` debe pasar por mb_substr($valor, 0, self::LARGO_MAXIMO_NUMERO)
     * o por RegulacionEstructuradorService::truncarNumero() para garantizar que no explota
     * con SQLSTATE[22001] si el parser captura texto demasiado largo.
     */
    public const LARGO_MAXIMO_NUMERO = 60;

    // ── Tipos de nodo ───────────────────────────────────────────────────────
    public const TIPO_TITULO   = 'titulo';
    public const TIPO_CAPITULO = 'capitulo';
    public const TIPO_SECCION  = 'seccion';
    public const TIPO_ARTICULO = 'articulo';
    public const TIPO_FRACCION = 'fraccion';
    public const TIPO_INCISO   = 'inciso';
    public const TIPO_PARRAFO  = 'parrafo';

    public const TIPOS = [
        self::TIPO_TITULO,
        self::TIPO_CAPITULO,
        self::TIPO_SECCION,
        self::TIPO_ARTICULO,
        self::TIPO_FRACCION,
        self::TIPO_INCISO,
        self::TIPO_PARRAFO,
    ];

    /** Etiqueta legible de cada tipo. */
    public const ETIQUETAS_TIPO = [
        self::TIPO_TITULO   => 'Título',
        self::TIPO_CAPITULO => 'Capítulo',
        self::TIPO_SECCION  => 'Sección',
        self::TIPO_ARTICULO => 'Artículo',
        self::TIPO_FRACCION => 'Fracción',
        self::TIPO_INCISO   => 'Inciso',
        self::TIPO_PARRAFO  => 'Párrafo',
    ];

    // ── Estados ─────────────────────────────────────────────────────────────
    public const ESTADO_VIGENTE  = 'vigente';
    public const ESTADO_DEROGADO = 'derogado';

    /**
     * Reglas de anidamiento: para cada tipo de padre, los tipos de hijo
     * permitidos. `null` representa la raíz de la regulación (parent_id null).
     * El editor solo ofrece como destino los tipos válidos, evitando árboles
     * inconsistentes (p. ej. una fracción colgando de un capítulo sin artículo).
     */
    public const ANIDAMIENTO = [
        'raiz'              => [self::TIPO_TITULO, self::TIPO_CAPITULO, self::TIPO_SECCION, self::TIPO_ARTICULO, self::TIPO_PARRAFO],
        self::TIPO_TITULO   => [self::TIPO_CAPITULO, self::TIPO_SECCION, self::TIPO_ARTICULO, self::TIPO_PARRAFO],
        self::TIPO_CAPITULO => [self::TIPO_SECCION, self::TIPO_ARTICULO, self::TIPO_PARRAFO],
        self::TIPO_SECCION  => [self::TIPO_ARTICULO, self::TIPO_PARRAFO],
        self::TIPO_ARTICULO => [self::TIPO_FRACCION, self::TIPO_PARRAFO],
        self::TIPO_FRACCION => [self::TIPO_INCISO, self::TIPO_PARRAFO],
        self::TIPO_INCISO   => [self::TIPO_PARRAFO],
        self::TIPO_PARRAFO  => [], // hoja
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function regulacion()
    {
        return $this->belongsTo(Regulacion::class);
    }

    public function padre()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden');
    }

    public function hijosVigentes()
    {
        return $this->hijos()->where('estado', self::ESTADO_VIGENTE);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** Nodos raíz de la regulación (sin padre). */
    public function scopeRaices($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function estaDerogado(): bool
    {
        return $this->estado === self::ESTADO_DEROGADO;
    }

    public function etiquetaTipo(): string
    {
        return self::ETIQUETAS_TIPO[$this->tipo] ?? ucfirst($this->tipo);
    }

    /**
     * ¿Un nodo de este tipo puede contener un hijo del tipo dado? Si este nodo
     * es la raíz conceptual (sin tipo), se consulta la regla 'raiz'.
     */
    public function puedeContener(string $tipoHijo): bool
    {
        $permitidos = self::ANIDAMIENTO[$this->tipo] ?? [];
        return in_array($tipoHijo, $permitidos, true);
    }

    /** Tipos de hijo permitidos directamente bajo la raíz de una regulación. */
    public static function tiposEnRaiz(): array
    {
        return self::ANIDAMIENTO['raiz'];
    }

    /**
     * Sugiere el número del siguiente nodo de un tipo dado, bajo un padre dado.
     * Es solo una AYUDA (decisión de negocio): el usuario puede sobrescribirla,
     * porque las derogaciones rompen la secuencia y no se puede imponer.
     *
     * - articulo: numeración arábiga (1, 2, 3...).
     * - fraccion: numeración romana (I, II, III...).
     * - inciso:   letras (a, b, c...).
     * - otros:    cadena vacía (capítulo/título/sección los rotula el usuario).
     *
     * Toma el número del último hermano del mismo tipo y avanza desde ahí; si no
     * hay hermanos, arranca en el primero de la serie.
     */
    public static function siguienteNumeroSugerido(int $regulacionId, ?int $parentId, string $tipo): string
    {
        $serie = match ($tipo) {
            self::TIPO_ARTICULO => 'arabigo',
            self::TIPO_FRACCION => 'romano',
            self::TIPO_INCISO   => 'letra',
            default             => null,
        };

        if ($serie === null) {
            return '';
        }

        // Último hermano del mismo tipo (incluye derogados: la serie no se reinicia).
        $ultimo = self::where('regulacion_id', $regulacionId)
            ->where('parent_id', $parentId)
            ->where('tipo', $tipo)
            ->orderByDesc('orden')
            ->value('numero');

        $previo = $ultimo !== null ? self::serieAEntero($ultimo, $serie) : 0;
        return self::enteroASerie($previo + 1, $serie);
    }

    /** Convierte un valor de la serie a entero (para avanzar). */
    private static function serieAEntero(string $valor, string $serie): int
    {
        $valor = trim($valor);
        return match ($serie) {
            'arabigo' => (int) preg_replace('/\D/', '', $valor) ?: 0,
            'romano'  => self::romanoAEntero(strtoupper($valor)),
            'letra'   => strlen($valor) === 1 ? (ord(strtolower($valor)) - ord('a') + 1) : 0,
            default   => 0,
        };
    }

    /** Convierte un entero al formato de la serie. */
    private static function enteroASerie(int $n, string $serie): string
    {
        return match ($serie) {
            'arabigo' => (string) $n,
            'romano'  => self::enteroARomano($n),
            'letra'   => $n >= 1 && $n <= 26 ? chr(ord('a') + $n - 1) : (string) $n,
            default   => (string) $n,
        };
    }

    private static function romanoAEntero(string $romano): int
    {
        $mapa = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
        $total = 0;
        $prev = 0;
        for ($i = strlen($romano) - 1; $i >= 0; $i--) {
            $val = $mapa[$romano[$i]] ?? 0;
            $total += $val < $prev ? -$val : $val;
            $prev = $val;
        }
        return $total;
    }

    private static function enteroARomano(int $n): string
    {
        if ($n <= 0) {
            return '';
        }
        $tabla = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100  => 'C', 90  => 'XC', 50  => 'L', 40  => 'XL',
            10   => 'X', 9   => 'IX', 5   => 'V', 4   => 'IV', 1 => 'I',
        ];
        $resultado = '';
        foreach ($tabla as $valor => $simbolo) {
            while ($n >= $valor) {
                $resultado .= $simbolo;
                $n -= $valor;
            }
        }
        return $resultado;
    }
}
