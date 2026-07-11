<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tramite extends Model
{
    use SoftDeletes;

    /**
     * Columnas asignables en masa (sin id, timestamps ni deleted_at).
     * Reconstruido desde TODAS las migraciones de la tabla tramites, incluyendo
     * las fj_* (add_fundamento) y sujeto_obligado_id/enlace_id (add_sujeto_enlace,
     * bug #B5). Agrupado por propósito para legibilidad.
     */
    protected $fillable = [
        // Identificación
        'nombre_oficial',
        'naturaleza',       // ENUM: tramite | servicio
        'tipo_servicio',    // VARCHAR(200), solo cuando naturaleza = 'servicio'
        'tipo_tramite_id',
        'dependencia_id',
        'unidad_id',
        'unidad_responsable_id',
        'sector_id',
        'subsector_id',
        'servidor_publico',
        'tiene_homoclave',
        'homoclave',
        'sujeto_obligado_id',
        'enlace_id',
        'periodo_id',
        'created_by',
        'estatus',
        // Descripción y alcance
        'objetivo',
        'poblacion_objetivo',
        'dirigido_a',
        'grupo_prioritario',
        'grupo_prioritario_detalle',
        'frecuencia',
        'volumen_anual',
        // Relación / redundancia / interoperabilidad
        'tipo_relacion',
        'tiene_relacionados',
        'relacionados_detalle',
        'tiene_redundantes',
        'redundantes_detalle',
        'requiere_interop',
        'interop_detalle',
        'simplificacion_ref',
        // Plazo de resolución
        'plazo_resolucion_cantidad',
        'plazo_resolucion_unidad',
        // Áreas y operación
        'num_areas',
        'areas_participantes',
        'visitas_requeridas',
        // Tiempos (traslado / espera / atención)
        'tiempo_traslado_horas',
        'tiempo_traslado_min',
        'tiempo_espera_horas',
        'tiempo_espera_min',
        'tiempo_atencion_horas',
        'tiempo_atencion_min',
        // Derechos y copias
        'monto_derechos',
        'monto_derechos_variable',
        'monto_derechos_referencia',
        'copias_cantidad',
        'copias_precio',
        'monto_requisitos_con_costo',
        'salario_hora_w',
        // Digitalización
        'nivel_digitalizacion',
        // Costo burocrático (calculados por el servicio)
        'cbd_directo',
        'cbi_indirecto',
        'cbi_requisitos',
        'cbi_resolucion',
        'cbu_unitario',
        'cbt_total',
        'impacto',
        'resultado_air',
        // Catálogos oficiales ATDT (JSON)
        'acciones_simplificacion',
        'grupos_atencion',
        'etapa_operacion',
        // Fundamento jurídico opcional del costo (add_fundamento)
        'fj_norma',
        'fj_capitulo',
        'fj_articulo',
        // Digitalización — estados del flujo de digitalización
        'flujo_estado',
        'digitalizacion_estado',
        'digitalizacion_origen',
        'flujo_enviado_en',
        'flujo_aprobado_en',
        'flujo_aprobado_por',
    ];
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

    // ── Naturaleza del registro (Art. 3 LNETB) ─────────────────────────
    //
    // Trámite: solicitud o entrega de información que una persona realiza
    //          ante un Sujeto Obligado para acceder a un derecho, cumplir
    //          una obligación u obtener un beneficio.
    //
    // Servicio: beneficio, programa social o actividad que el Sujeto
    //           Obligado brinda a las personas, previo cumplimiento de
    //           requisitos.
    //
    // Son conceptos jurídicamente distintos pero comparten el mismo flujo
    // operativo en el sistema (wizard, observaciones, firmas, costo).
    //   - Trámite  → tipo viene de tipo_tramite_id (FK al catálogo editable)
    //   - Servicio → tipo viene de tipo_servicio   (string, lista fija LNETB)
    public const NATURALEZA_TRAMITE  = 'tramite';
    public const NATURALEZA_SERVICIO = 'servicio';

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
        // Bandera de pago de derechos variable.
        'monto_derechos_variable' => 'boolean',
        // Catálogos de selección múltiple guardados como JSON.
        'acciones_simplificacion' => 'array',
        'grupos_atencion'         => 'array',
        // Fase 4: timestamps de revisión del flujo
        'flujo_enviado_en'  => 'datetime',
        'flujo_aprobado_en' => 'datetime',
    ];

    /**
     * Al crear un trámite se le asigna automáticamente el periodo SyD
     * activo, salvo que ya venga uno explícito. Así cada trámite queda ligado
     * a su periodo sin que el usuario tenga que elegirlo.
     */
    protected static function booted(): void
    {
        static::creating(function (Tramite $tramite) {
            if (empty($tramite->periodo_id)) {
                $tramite->periodo_id = Periodo::activo()->syd()->value('id');
            }
        });
    }

    /** Periodo (Agenda SyD) al que pertenece el trámite. */
    public function periodo()
    {
        return $this->belongsTo(Periodo::class);
    }

    // ========== Relaciones ==========

    public function dependencia()      { return $this->belongsTo(Dependencia::class); }
    public function unidad()           { return $this->belongsTo(UnidadAdministrativa::class, 'unidad_id'); }
    public function sector()           { return $this->belongsTo(SectorScian::class, 'sector_id'); }
    public function subsector()        { return $this->belongsTo(SubsectorScian::class, 'subsector_id'); }
    public function tipoTramite()      { return $this->belongsTo(TipoTramite::class); }

    // ── Naturaleza ───────────────────────────────────────────────────────

    /** ¿Este registro es un servicio municipal (no un trámite)? */
    public function esServicio(): bool
    {
        return $this->naturaleza === self::NATURALEZA_SERVICIO;
    }

    /** ¿Este registro es un trámite (no un servicio)? */
    public function esTramite(): bool
    {
        return $this->naturaleza !== self::NATURALEZA_SERVICIO;
    }

    /**
     * Devuelve la etiqueta legible del tipo, sin importar si es trámite o servicio.
     * Trámite  → nombre del tipo del catálogo (ej. "Licencia")
     * Servicio → tipo_servicio tal como se guardó (ej. "Servicio catastral o territorial")
     */
    public function tipoLegible(): string
    {
        if ($this->esServicio()) {
            return $this->tipo_servicio ?? 'Servicio';
        }
        return $this->tipoTramite?->nombre ?? 'Sin tipo';
    }

    /**
     * Etiqueta corta para badges y tablas: "Trámite" o "Servicio".
     */
    public function naturalezaLegible(): string
    {
        return $this->esServicio() ? 'Servicio' : 'Trámite';
    }

    /** Scope: solo trámites (excluye servicios). */
    public function scopeTramites($query)
    {
        return $query->where('naturaleza', self::NATURALEZA_TRAMITE);
    }

    /** Scope: solo servicios (excluye trámites). */
    public function scopeServicios($query)
    {
        return $query->where('naturaleza', self::NATURALEZA_SERVICIO);
    }
    public function creador()          { return $this->belongsTo(User::class, 'created_by'); }
    public function requisitos()       { return $this->hasMany(Requisito::class)->orderBy('orden'); }
    public function procesosAtencion() { return $this->hasMany(ProcesoAtencion::class); }
    public function fundamentos()      { return $this->hasMany(FundamentoJuridico::class); }
    public function fichaPortal()      { return $this->hasOne(FichaPortal::class); }
    public function derechos()         { return $this->hasMany(TramiteDerecho::class); }
    public function acciones()         { return $this->hasMany(AccionAgenda::class); }
    public function observaciones()    { return $this->morphMany(Observacion::class, 'observable'); }
    public function firmas()           { return $this->morphMany(Firma::class, 'firmable'); }

    /**
     * Trámites del catálogo que este trámite cita como relacionados (rubro 10.2).
     * La tabla pivot tramite_relacionados usa la columna "relacionado_id" para
     * el lado "citado", a diferencia del nombre estándar que usaría "tramite_id"
     * en ambos lados. Se declara explícitamente para evitar ambigüedad.
     */
    public function relacionados()
    {
        return $this->belongsToMany(
            Tramite::class,
            'tramite_relacionados',
            'tramite_id',
            'relacionado_id'
        )->withTimestamps();
    }

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
    public static function formatearHomoclave(string $naturaleza, string $siglasDependencia, string $siglasUnidad, int $consecutivo): string
    {
        $prefijo = config('punta.prefijo_homoclave', 'LPZ');

        // $naturaleza: 'T' (trámite) o 'S' (servicio). Queda LPZ-T-DGGD-CM-5.
        return sprintf('%s-%s-%s-%s-%d', $prefijo, $naturaleza, $siglasDependencia, $siglasUnidad, $consecutivo);
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
        // El consecutivo es el último segmento de la homoclave (LPZ-T-DEP-UNI-42 → 42).
        // Se extrae en PHP y no con SQL crudo, porque SUBSTRING_INDEX y
        // CAST(... AS UNSIGNED) son sintaxis exclusiva de MySQL: así el cálculo
        // funciona igual en MySQL y en PostgreSQL.
        $maxConsecutivo = static::withTrashed()
            ->whereNotNull('homoclave')
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->pluck('homoclave')
            ->map(function (string $homoclave): int {
                $ultimo = substr((string) strrchr($homoclave, '-'), 1);
                return ctype_digit($ultimo) ? (int) $ultimo : 0;
            })
            ->max();

        return ((int) $maxConsecutivo) + 1;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Digitalización — estados, relaciones y helpers
    // ═══════════════════════════════════════════════════════════════════════

    // ── Estados del flujo (levantamiento AS-IS) ──────────────────────────

    public const FLUJO_SIN_FLUJO       = 'sin_flujo';
    public const FLUJO_EN_CAPTURA      = 'flujo_en_captura';
    public const FLUJO_EN_REVISION     = 'flujo_en_revision';
    public const FLUJO_OBSERVADO       = 'flujo_observado';
    public const FLUJO_APROBADO        = 'flujo_aprobado';

    public const FLUJO_ESTADOS = [
        self::FLUJO_SIN_FLUJO,
        self::FLUJO_EN_CAPTURA,
        self::FLUJO_EN_REVISION,
        self::FLUJO_OBSERVADO,
        self::FLUJO_APROBADO,
    ];

    // ── Estados de digitalización ────────────────────────────────────────

    public const DIG_NO_INICIADA       = 'no_iniciada';
    public const DIG_LISTA             = 'lista_para_digitalizacion';
    public const DIG_EN_DIGITALIZACION = 'en_digitalizacion';
    public const DIG_DIGITALIZADO      = 'digitalizado';
    public const DIG_REQUIERE_REVISION = 'requiere_revision_por_cambio';

    public const DIG_ESTADOS = [
        self::DIG_NO_INICIADA,
        self::DIG_LISTA,
        self::DIG_EN_DIGITALIZACION,
        self::DIG_DIGITALIZADO,
        self::DIG_REQUIERE_REVISION,
    ];

    // ── Relaciones de digitalización ─────────────────────────────────────

    /** Todas las reingenierías (historial de versiones). */
    public function reingenierias()
    {
        return $this->hasMany(Reingenieria::class)->orderByDesc('version');
    }

    /** Reingeniería activa (última versión no eliminada). */
    public function reingenieriaActiva()
    {
        return $this->hasOne(Reingenieria::class)->latestOfMany('version');
    }

    /** Todos los diagramas del trámite. */
    public function diagramas()
    {
        return $this->hasMany(Diagrama::class);
    }

    // ── Helpers de digitalización ────────────────────────────────────────

    public function tieneFlujoAprobado(): bool
    {
        return $this->flujo_estado === self::FLUJO_APROBADO;
    }

    public function puedeIniciarDigitalizacion(): bool
    {
        return $this->tieneFlujoAprobado()
            && $this->reingenieriaActiva
            && $this->reingenieriaActiva->estaFirmada();
    }

    public function flujoEstadoLegible(): string
    {
        return match ($this->flujo_estado) {
            self::FLUJO_SIN_FLUJO   => 'Sin flujo',
            self::FLUJO_EN_CAPTURA  => 'En captura',
            self::FLUJO_EN_REVISION => 'En revisión',
            self::FLUJO_OBSERVADO   => 'Observado',
            self::FLUJO_APROBADO    => 'Aprobado',
            default => ucfirst(str_replace('_', ' ', $this->flujo_estado ?? 'sin_flujo')),
        };
    }

    public function digitalizacionEstadoLegible(): string
    {
        return match ($this->digitalizacion_estado) {
            self::DIG_NO_INICIADA       => 'No iniciada',
            self::DIG_LISTA             => 'Lista',
            self::DIG_EN_DIGITALIZACION => 'En digitalización',
            self::DIG_DIGITALIZADO      => 'Digitalizado',
            self::DIG_REQUIERE_REVISION => 'Requiere revisión',
            default => ucfirst(str_replace('_', ' ', $this->digitalizacion_estado ?? 'no_iniciada')),
        };
    }
}