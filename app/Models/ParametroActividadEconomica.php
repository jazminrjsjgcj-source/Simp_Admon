<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos económicos de una actividad SCIAN, para calcular cuánto le cuesta a una
 * empresa de esa actividad esperar la resolución de un trámite.
 *
 * ── Para qué sirve, explicado desde cero ─────────────────────────────
 *
 * Cuando una empresa espera un permiso, no puede operar. Ese tiempo tiene un precio:
 * lo que habría ganado si el permiso hubiera salido de inmediato. La metodología lo
 * llama COSTO DE OPORTUNIDAD, y no es igual para todos:
 *
 *   - Una tienda de barrio pierde poco por cada día parada.
 *   - Una industria alimentaria con una planta detenida pierde mucho más.
 *
 * Para saber cuánto, la metodología necesita seis datos de la actividad económica
 * completa (todos los del mismo giro en la región, no de la empresa concreta):
 *
 *   valor_produccion   VProd  → cuánto produce la actividad al año
 *   gasto_consumo      GaC    → cuánto gasta en insumos
 *   remuneraciones     Rem    → cuánto paga en sueldos
 *   inversion          Inv    → cuánto invierte
 *   activos_fijos      Act    → cuánto valen sus activos (solo cuenta en APERTURA)
 *   num_empresas       NumE   → cuántas empresas hay en la actividad
 *
 * Con eso salen dos cosas (Ecuaciones 9 a 12):
 *
 *   1. La TASA DE PRODUCTIVIDAD: cuánto rinde cada peso invertido.
 *          TProdAnual = VProd / (GaC + Rem + Inv) − 1
 *
 *   2. El CAPITAL DIARIO de una empresa media de esa actividad.
 *          Capital = GaC + Rem + Inv           (operación y cierre)
 *          Capital = GaC + Rem + Inv + Act     (apertura: además pone los activos)
 *          CapitalDiarioPorEmpresa = (Capital / NumE) / 365
 *
 *   Y multiplicando ambas: lo que esa empresa deja de ganar cada día que espera.
 *
 * ── De dónde salen estos datos ───────────────────────────────────────
 *
 * De los Censos Económicos del INEGI, por sector y subsector SCIAN. Se cargan a mano
 * (o por seeder) y se actualizan cuando el INEGI publica. La columna `fuente` existe
 * para que dentro de tres años se sepa de dónde salió cada cifra.
 *
 * @extends Model
 */
class ParametroActividadEconomica extends Model
{
    use HasFactory;

    protected $table = 'parametros_actividad_economica';

    protected $fillable = [
        'sector_id',
        'subsector_id',
        'valor_produccion',
        'gasto_consumo',
        'remuneraciones',
        'inversion',
        'activos_fijos',
        'num_empresas',
        'anio',
        'fuente',
        'activo',
        'actualizado_por',
    ];

    protected $casts = [
        'valor_produccion' => 'float',
        'gasto_consumo'    => 'float',
        'remuneraciones'   => 'float',
        'inversion'        => 'float',
        'activos_fijos'    => 'float',
        'num_empresas'     => 'integer',
        'activo'           => 'boolean',
    ];

    /** Etapas de la persona moral. Coinciden con lo que guarda Tramite::etapa_operacion. */
    public const ETAPA_APERTURA  = 'APERTURA';
    public const ETAPA_OPERACION = 'OPERACIÓN';
    public const ETAPA_CIERRE    = 'CIERRE';

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function sector()
    {
        return $this->belongsTo(SectorScian::class, 'sector_id');
    }

    public function subsector()
    {
        return $this->belongsTo(SubsectorScian::class, 'subsector_id');
    }

    /**
     * Tasa de productividad ANUAL de la actividad (Ecuación 9).
     *
     *     TProdAnual = ValorProducción / (Gasto + Remuneraciones + Inversión) − 1
     *
     * Mide cuánto rinde cada peso que la actividad mete en el negocio. Si produce 1.05
     * por cada 1.00 que gasta, la tasa es 0.05 (un 5%).
     *
     * Devuelve null si el denominador es cero: sin gasto, sin sueldos y sin inversión,
     * la tasa no significa nada. Devolver null y no cero es deliberado — cero diría
     * "esta actividad no rinde", y lo cierto es "no lo sabemos".
     */
    public function tasaProductividadAnual(): ?float
    {
        // Sin la inversión no se puede calcular, y punto. Tratarla como cero daría una
        // tasa de productividad MÁS ALTA de la real (el denominador sería menor), y el
        // resultado parecería perfectamente razonable. Nadie lo notaría.
        //
        // Es el mismo error que este módulo entero viene a arreglar: rellenar un hueco
        // con una cifra plausible en vez de dejarlo a la vista.
        if ($this->inversion === null) {
            return null;
        }

        $denominador = $this->gasto_consumo + $this->remuneraciones + $this->inversion;

        if ($denominador <= 0) {
            return null;
        }

        return ($this->valor_produccion / $denominador) - 1;
    }

    /**
     * ¿Están los seis datos que la metodología necesita?
     *
     * Hoy la respuesta es NO para todos los sectores sembrados: la monografía del INEGI
     * publica cinco de los seis. Falta la Formación Bruta de Capital Fijo, que está en el
     * SAIC (inegi.org.mx/app/saic) y hay que cargarla a mano.
     *
     * Este método existe para que una pantalla de administración pueda decir "faltan estos
     * datos" en vez de que el usuario se encuentre con trámites cuyo costo de espera sale
     * cero sin explicación.
     */
    public function datosCompletos(): bool
    {
        return $this->inversion !== null && $this->num_empresas > 0;
    }

    /** Tasa de productividad DIARIA (Ecuación 10): la anual repartida entre los 365 días. */
    public function tasaProductividadDiaria(): ?float
    {
        $anual = $this->tasaProductividadAnual();

        return $anual === null ? null : $anual / 365;
    }

    /**
     * Capital anual de la actividad, según la etapa en que está la empresa
     * (Ecuaciones 11 y 12).
     *
     * En APERTURA la empresa todavía tiene que poner los activos fijos (la nave, la
     * maquinaria), así que su capital comprometido es mayor. En OPERACIÓN y CIERRE ya
     * los tiene: solo cuenta el gasto corriente.
     *
     * Cualquier etapa desconocida —o vacía— se trata como OPERACIÓN, que es la más
     * conservadora de las tres (no suma los activos).
     */
    public function capitalAnual(?string $etapa): float
    {
        $base = $this->gasto_consumo + $this->remuneraciones + $this->inversion;

        return $etapa === self::ETAPA_APERTURA
            ? $base + $this->activos_fijos
            : $base;
    }

    /**
     * Costo de oportunidad DIARIO de una empresa media de esta actividad
     * (Ecuación 13):
     *
     *     CO_diario = TasaProductividadDiaria × CapitalDiarioPorEmpresa
     *
     * Es lo que una empresa de este giro deja de ganar por cada día que espera la
     * resolución. En los ejemplos de la metodología va de $1.37 (industria alimentaria
     * en operación) a $4.08 (comercio al por menor en apertura).
     *
     * Devuelve null si faltan datos para calcularlo. Ese null viaja hacia arriba y hace
     * que el trámite se marque como "resolución no calculable" — nunca se sustituye por
     * una cifra inventada.
     */
    public function costoOportunidadDiario(?string $etapa): ?float
    {
        $tasaDiaria = $this->tasaProductividadDiaria();

        if ($tasaDiaria === null || $this->num_empresas <= 0) {
            return null;
        }

        $capitalPorEmpresa       = $this->capitalAnual($etapa) / $this->num_empresas;
        $capitalPorEmpresaDiario = $capitalPorEmpresa / 365;

        return $tasaDiaria * $capitalPorEmpresaDiario;
    }
}
