<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tramite extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $table   = 'tramites';

    /**
     * Constantes de estatus del trámite — fuente única de verdad.
     * Coinciden con el enum de la migración.
     */
    public const ESTATUS_BORRADOR       = 'borrador';
    public const ESTATUS_EN_OBSERVACION = 'en_observacion';
    public const ESTATUS_EN_CORRECCION  = 'en_correccion';
    public const ESTATUS_EN_FIRMA       = 'en_firma';
    public const ESTATUS_COMPLETADO     = 'completado';

    /**
     * Lista completa de estados válidos, en el orden del flujo de vida
     * del trámite: borrador → observación → corrección → firma → completado.
     */
    public const ESTATUS_TODOS = [
        self::ESTATUS_BORRADOR,
        self::ESTATUS_EN_OBSERVACION,
        self::ESTATUS_EN_CORRECCION,
        self::ESTATUS_EN_FIRMA,
        self::ESTATUS_COMPLETADO,
    ];

    /**
     * Constantes para el cálculo de costo burocrático (metodología ATDT).
     * Valores oficiales del Ayuntamiento.
     */
    public const SALARIO_HORA_DEFAULT  = 68.20;
    public const COSTO_COPIA_DEFAULT   = 1.50;
    public const HORAS_JORNADA_LABORAL = 8;
    public const DIAS_PROMEDIO_MES     = 365 / 12;
    public const FACTOR_DIAS_HABILES   = 1.4;

    /**
     * Umbrales para clasificar el costo unitario (CBU) como bajo/medio/alto.
     */
    public const CBU_UMBRAL_BAJO = 1000;
    public const CBU_UMBRAL_ALTO = 10000;

    /**
     * Constantes de impacto (clasificación contra umbral configurado).
     */
    public const IMPACTO_NO_DETERMINADO = 'no_determinado';
    public const IMPACTO_BAJO           = 'bajo';
    public const IMPACTO_MEDIO          = 'medio';
    public const IMPACTO_ALTO           = 'alto';
    public const IMPACTO_CRITICO        = 'critico';

    protected $casts = [
        'tiene_homoclave' => 'boolean',
        'monto_derechos'  => 'decimal:2',
        'cbd_directo'     => 'decimal:2',
        'cbi_indirecto'   => 'decimal:2',
        'cbu_unitario'    => 'decimal:2',
        'cbt_total'       => 'decimal:2',
    ];

    // ========== Relaciones ==========

    public function dependencia()      { return $this->belongsTo(Dependencia::class); }
    public function unidad()           { return $this->belongsTo(UnidadAdministrativa::class, 'unidad_id'); }
    public function sector()           { return $this->belongsTo(SectorScian::class, 'sector_id'); }
    public function subsector()        { return $this->belongsTo(SubsectorScian::class, 'subsector_id'); }
    public function unidadResponsable(){ return $this->belongsTo(UnidadResponsable::class, 'unidad_responsable_id'); }
    public function creador()          { return $this->belongsTo(User::class, 'created_by'); }
    public function requisitos()       { return $this->hasMany(Requisito::class)->orderBy('orden'); }
    public function procesosAtencion() { return $this->hasMany(ProcesoAtencion::class); }
    public function fundamentos()      { return $this->hasMany(FundamentoJuridico::class); }
    public function fichaPortal()      { return $this->hasOne(FichaPortal::class); }
    public function derechos()         { return $this->hasMany(TramiteDerecho::class); }
    public function acciones()         { return $this->hasMany(AccionAgenda::class); }
    public function observaciones()    { return $this->morphMany(Observacion::class, 'observable'); }
    public function firmas()           { return $this->morphMany(Firma::class, 'firmable'); }

    // ========== Lógica de negocio ==========

    /**
     * Calcula el costo burocrático según metodología ATDT.
     *
     * CBD = Monto derechos + Copias × Precio copia
     * CBI = Días equivalentes × Salario hora × Horas jornada
     * CBU = CBD + CBI
     * CBT = CBU × Volumen anual
     *
     * @param  array  $data  Datos del trámite (acepta override de los del modelo)
     * @return array  Costos calculados redondeados a 2 decimales
     */
    public static function calcularCostoDesde(array $data): array
    {
        $salarioHora     = floatval($data['salario_hora_w']            ?? self::SALARIO_HORA_DEFAULT);
        $montoDerechos   = floatval($data['monto_derechos']             ?? 0);
        $costoCopias     = intval($data['copias_cantidad']              ?? 0)
                         * floatval($data['copias_precio']               ?? self::COSTO_COPIA_DEFAULT);
        $costoDirecto    = $montoDerechos + $costoCopias;

        $plazoCantidad   = intval($data['plazo_resolucion_cantidad']    ?? 0);
        $plazoUnidad     = $data['plazo_resolucion_unidad']             ?? 'habiles';
        $diasEquivalentes = self::convertirAPlazoEnDias($plazoCantidad, $plazoUnidad);

        $costoIndirecto  = $diasEquivalentes * $salarioHora * self::HORAS_JORNADA_LABORAL;
        $costoUnitario   = $costoDirecto + $costoIndirecto;
        $volumenAnual    = max(1, intval($data['volumen_anual']         ?? 1));
        $costoTotal      = $costoUnitario * $volumenAnual;

        return [
            'cbd_directo'   => round($costoDirecto,   2),
            'cbi_indirecto' => round($costoIndirecto, 2),
            'cbu_unitario'  => round($costoUnitario,  2),
            'cbt_total'     => round($costoTotal,     2),
        ];
    }

    /**
     * Convierte un plazo expresado en hábiles, naturales o meses
     * a un número equivalente de días para el cálculo de CBI.
     */
    private static function convertirAPlazoEnDias(int $cantidad, string $unidad): float
    {
        return match($unidad) {
            'meses'   => $cantidad * self::DIAS_PROMEDIO_MES,
            'habiles' => $cantidad * self::FACTOR_DIAS_HABILES,
            default   => (float) $cantidad,
        };
    }


    // ========== Helpers del flujo de trámites ==========

    public function puedeSerEditado(): bool
    {
        return in_array($this->estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_EN_CORRECCION]);
    }

    public function puedeSerPublicado(): bool
    {
        return $this->estatus === self::ESTATUS_BORRADOR;
    }

    public function puedeSerRepublicado(): bool
    {
        return $this->estatus === self::ESTATUS_EN_CORRECCION;
    }

    public function estaEnObservacion(): bool
    {
        return $this->estatus === self::ESTATUS_EN_OBSERVACION;
    }

    public function puedeSerFirmado(): bool
    {
        return $this->estatus === self::ESTATUS_EN_FIRMA;
    }

    public function estaCompletado(): bool
    {
        return $this->estatus === self::ESTATUS_COMPLETADO;
    }

    public function tieneObservacionesPendientes(): bool
    {
        return $this->observaciones()->pendientes()->exists();
    }

    public function puedeAvanzarAFirma(): bool
    {
        return $this->estaEnObservacion() && !$this->tieneObservacionesPendientes();
    }

    public function calcularCosto(array $data = []): array
    {
        return self::calcularCostoDesde(array_merge($this->toArray(), $data));
    }

    /**
     * Categoriza el trámite por su costo unitario (CBU): bajo, medio o alto.
     */
    public function categoriaPorCostoUnitario(): string
    {
        $cbu = floatval($this->cbu_unitario ?? 0);
        return match(true) {
            $cbu <  self::CBU_UMBRAL_BAJO => 'bajo',
            $cbu <= self::CBU_UMBRAL_ALTO => 'medio',
            default                       => 'alto',
        };
    }

    /**
     * Devuelve el último snapshot de costo burocrático calculado para este trámite.
     */
    public function ultimoCostoBurocratico()
    {
        return $this->hasOne(TramiteCostoBurocratico::class)->latestOfMany('calculado_en');
    }

    /**
     * Genera la homoclave a partir del código jerárquico de la UR y el correlativo.
     *
     * Formato: T-{P}{SS}-{AA}-{NNN}
     *   P  = poder (1 dígito)
     *   SS = sub-dirección (2 dígitos, posiciones 7-8 del código UR)
     *   AA = área/departamento (2 dígitos, posiciones 9-10 del código UR)
     *   NNN = correlativo secuencial dentro de esa UR
     *
     * Ejemplos:
     *   01000003000000 → T-103-00-001 (Dirección de Atención Ciudadana, 1er trámite)
     *   01000002010000 → T-102-01-001 (Subdirección Jurídica)
     */
    public function generarHomoclave(): ?string
    {
        // Requiere dependencia y unidad administrativa para armar las siglas.
        if (!$this->dependencia_id || !$this->unidad_id) {
            return null;
        }

        $dependencia = $this->dependencia ?? Dependencia::find($this->dependencia_id);
        $unidad      = $this->unidad ?? UnidadAdministrativa::find($this->unidad_id);
        if (!$dependencia || !$unidad) {
            return null;
        }

        $consecutivo = static::siguienteConsecutivoGlobal($this->id);

        // Si la dependencia o la unidad no tienen siglas capturadas, las
        // derivamos de su nombre (iniciales de las primeras palabras), para
        // que la homoclave siempre se pueda generar.
        $siglasDep    = $dependencia->siglas ?: static::siglasDesdeNombre($dependencia->nombre);
        $siglasUnidad = $unidad->siglas      ?: static::siglasDesdeNombre($unidad->nombre);

        return static::formatearHomoclave($siglasDep, $siglasUnidad, $consecutivo);
    }

    /**
     * Deriva unas siglas a partir de un nombre, tomando la inicial de las
     * primeras palabras significativas (ignora artículos y preposiciones).
     * Ejemplo: "Dirección General de Gobierno Digital" -> "DGGD".
     * Se usa como respaldo cuando un catálogo no tiene siglas capturadas.
     */
    public static function siglasDesdeNombre(?string $nombre): string
    {
        if (!$nombre) {
            return 'GRAL';
        }

        $ignoradas = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'para', 'por', 'a'];
        $palabras = preg_split('/\s+/', trim($nombre));
        $iniciales = '';
        foreach ($palabras as $palabra) {
            $limpia = mb_strtolower($palabra);
            if (in_array($limpia, $ignoradas, true)) {
                continue;
            }
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        // Respaldo: si no quedó nada (nombre raro), usa las primeras 4 letras.
        return $iniciales !== '' ? $iniciales : mb_strtoupper(mb_substr($nombre, 0, 4));
    }

    /**
     * Arma la homoclave legible a partir de las siglas y el consecutivo.
     *
     * Formato: {prefijo_municipio}-{siglas_dep}-{siglas_unidad}-{consecutivo}
     * Ejemplo: LPZ-DGSP-VU-5
     *
     * El prefijo del municipio sale de config/punta.php, para que el
     * sistema pueda reusarse en otro municipio sin tocar código.
     *
     * @param  string  $siglasDependencia  Siglas de la dependencia (ej: DGSP).
     * @param  string  $siglasUnidad       Siglas de la unidad administrativa (ej: VU).
     * @param  int     $consecutivo        Número consecutivo global del trámite.
     * @return string  Homoclave completa.
     */
    public static function formatearHomoclave(string $siglasDependencia, string $siglasUnidad, int $consecutivo): string
    {
        $prefijo = config('punta.prefijo_homoclave', 'LPZ');

        return sprintf('%s-%s-%s-%d', $prefijo, $siglasDependencia, $siglasUnidad, $consecutivo);
    }

    /**
     * Devuelve el siguiente consecutivo global del sistema.
     *
     * El consecutivo es único a nivel de TODOS los trámites (no por
     * dependencia ni unidad): LPZ-DGSP-VU-5 significa "quinto trámite
     * registrado en el sistema". Se calcula con MAX sobre el último
     * segmento de la homoclave para no chocar con el índice único,
     * incluso si hay trámites eliminados (SoftDelete).
     *
     * @param  int|null  $excluirId  ID del trámite actual al editar (se excluye).
     * @return int  El siguiente consecutivo disponible.
     */
    public static function siguienteConsecutivoGlobal(?int $excluirId = null): int
    {
        $maxConsecutivo = static::withTrashed()
            ->whereNotNull('homoclave')
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(homoclave, '-', -1) AS UNSIGNED)) as max_n")
            ->value('max_n');

        return ($maxConsecutivo ?? 0) + 1;
    }

}
