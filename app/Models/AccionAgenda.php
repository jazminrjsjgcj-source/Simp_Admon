<?php
namespace App\Models;
use App\Models\Concerns\CongelaCatalogos;
use App\Models\Concerns\GeneraFolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccionAgenda extends Model
{
    use CongelaCatalogos, GeneraFolio, HasFactory, SoftDeletes;

    // Estatus del flujo de una acción de agenda.
    // Homologados con Tramite: ambos módulos comparten vocabulario.
    public const ESTATUS_BORRADOR       = 'borrador';
    public const ESTATUS_EN_OBSERVACION = 'en_observacion';
    public const ESTATUS_EN_CORRECCION  = 'en_correccion';
    public const ESTATUS_EN_FIRMA       = 'en_firma';
    public const ESTATUS_COMPLETADO     = 'completado';

    public const ESTATUS_TODOS = [
        self::ESTATUS_BORRADOR,
        self::ESTATUS_EN_OBSERVACION,
        self::ESTATUS_EN_CORRECCION,
        self::ESTATUS_EN_FIRMA,
        self::ESTATUS_COMPLETADO,
    ];

    /**
     * Transiciones de estatus permitidas DESDE EL ENDPOINT MANUAL
     * (AgendaController::actualizarEstatus). Los flujos automáticos —creación,
     * revisión y firma— no pasan por aquí: son transiciones internas de confianza
     * que cambian el estatus directamente.
     */
    public const TRANSICIONES = [
        self::ESTATUS_BORRADOR       => [self::ESTATUS_EN_OBSERVACION],
        self::ESTATUS_EN_CORRECCION  => [self::ESTATUS_EN_OBSERVACION],
        self::ESTATUS_EN_OBSERVACION => [self::ESTATUS_EN_FIRMA],
        self::ESTATUS_EN_FIRMA       => [],
        self::ESTATUS_COMPLETADO     => [],
    ];

    /** ¿Se puede pasar de mi estatus actual a $destino desde el endpoint manual? */
    public function puedeTransicionarA(string $destino): bool
    {
        $permitidos = self::TRANSICIONES[$this->estatus] ?? [];
        return in_array($destino, $permitidos, true);
    }
    protected $table   = 'acciones_agenda';

    /**
     * Columnas asignables en masa (sin id ni timestamps). Incluye folio y
     * periodo_id (los asigna el modelo en booted()/GeneraFolio) y los JSON
     * de acciones. Reconstruido desde las migraciones de acciones_agenda.
     */
    protected $fillable = [
        'activa',
        'catalogos_al_firmar',
        'tramite_id',
        'tipo',
        'descripcion',
        'meta',
        'fecha_inicio',
        'fecha_compromiso',
        'responsable',
        'dependencia_id',
        'unidad_id',
        'indicador',
        'indicador_avance',
        'estatus',
        'created_by',
        'folio',
        'periodo_id',
        'acciones_simplificacion',
        'acciones_digitalizacion',
        'nivel_actual',
        'nivel_meta',
    ];

    // Paquete 3: catálogos oficiales guardados como JSON.
    protected $casts = [
        'catalogos_al_firmar' => 'array',
        'acciones_simplificacion' => 'array',
        'acciones_digitalizacion' => 'array',
        'fecha_inicio'            => 'date',
        'fecha_compromiso'        => 'date',
    ];

    /**
     * #12: al crear una acción se le asigna automáticamente el periodo SyD
     * activo, salvo que ya venga uno explícito.
     */
    protected static function booted(): void
    {
        static::creating(function (AccionAgenda $accion) {
            if (empty($accion->periodo_id)) {
                $accion->periodo_id = Periodo::activo()->syd()->value('id');
            }
        });

        // #29: genera el folio automáticamente cuando la acción deja de ser
        // borrador (se envía a revisión) y aún no tiene uno. Mismo patrón
        // que PropuestaRegulatoria::booted(). Al vivir en el modelo, funciona
        // sin importar quién cambie el estatus (controlador, servicio, tinker).
        static::updating(function (AccionAgenda $accion) {
            $dejaBorrador = $accion->isDirty('estatus')
                && $accion->getOriginal('estatus') === self::ESTATUS_BORRADOR
                && $accion->estatus !== self::ESTATUS_BORRADOR;

            if ($dejaBorrador && empty($accion->folio)) {
                $accion->load('dependencia');
                $accion->folio = $accion->generarFolio();
            }
        });
    }

    /** #12: periodo (Agenda SyD) al que pertenece la acción. */
    public function periodo() { return $this->belongsTo(Periodo::class); }

    /**
     * Prefijo de tipo del folio según el alcance de la acción:
     * simplificación → SIM, digitalización → DIG, ambas → SYD.
     * Así el folio dice qué es (LPZ-SYD-DGGD-2026-001) en vez de un AGD genérico.
     */
    protected function folioTipo(): string
    {
        return match ($this->tipo) {
            'simplificacion' => 'SIM',
            'digitalizacion' => 'DIG',
            'ambas'          => 'SYD',
            default          => 'SYD',
        };
    }

    public function dependencia() { return $this->belongsTo(Dependencia::class); }

    public function unidad() { return $this->belongsTo(UnidadAdministrativa::class, 'unidad_id'); }

    public function tramite() { return $this->belongsTo(Tramite::class); }

    public function creador() { return $this->belongsTo(User::class, 'created_by'); }

    public function observaciones() { return $this->morphMany(Observacion::class, 'observable'); }

    public function firmas() { return $this->morphMany(Firma::class, 'firmable'); }

    public function hitos() { return $this->hasMany(HitoAgenda::class, 'accion_agenda_id')->orderBy('orden'); }


    /**
     * ── Acciones activas ─────────────────────────────────────────────────
     *
     * Una acción está INACTIVA mientras el trámite sobre el que se apoya no esté
     * completado. Ocurre cuando el trámite se registra desde la propia agenda: la
     * mejora no puede darse por comprometida sobre algo que aún no existe de forma
     * oficial.
     *
     * Mientras tanto la acción no aparece en los listados de las demás personas, ni
     * en el calendario, ni en los indicadores. Su autor sí la ve, marcada como
     * pendiente, para que sepa que existe y por qué todavía no cuenta.
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Las acciones que un usuario debe ver: las activas, más las suyas propias
     * aunque estén pendientes del trámite (si no, habría escrito una acción que no
     * aparece en ninguna parte y no sabría por qué).
     */
    public function scopeVisiblesPara($query, User $usuario)
    {
        return $query->where(function ($q) use ($usuario) {
            $q->where('activa', true)
              ->orWhere('created_by', $usuario->id);
        });
    }

    /** ¿Está esperando a que su trámite quede completado? */
    public function estaPendienteDelTramite(): bool
    {
        return ! $this->activa;
    }

    /**
     * ¿Debería estar activa? Lo está si su trámite ya está completado. Una acción sin
     * trámite (caso raro, pero posible en un borrador) se considera activa: no hay
     * nada a lo que esperar.
     */
    public function deberiaEstarActiva(): bool
    {
        if (! $this->tramite_id) {
            return true;
        }

        return $this->tramite?->estatus === Tramite::ESTATUS_COMPLETADO;
    }

    /**
     * Catálogos que se congelan al firmar la acción. Son los nombres que aparecen en
     * el acuse: si la dependencia se renombra después, la acción firmada sigue
     * diciendo lo que decía (ver el trait CongelaCatalogos).
     */
    public function catalogosCongelables(): array
    {
        return [
            'dependencia' => 'nombre',
            'unidad'      => 'nombre',
        ];
    }
}