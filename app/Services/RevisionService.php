<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\Observacion;
use App\Models\PropuestaRegulatoria;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Servicio para gestionar el flujo de revisión:
 *   - Registrar observaciones por sección
 *   - Marcar observaciones como atendidas
 *   - Aprobar o regresar un registro a corrección
 *   - Resolver el tipo de registro a partir de un string
 */
class RevisionService
{
    public const TIPO_TRAMITE              = 'tramite';
    public const TIPO_AGENDA               = 'agenda';
    public const TIPO_PROPUESTA_REGULATORIA = 'propuesta_regulatoria';

    public function __construct(
        private BitacoraService $bitacora,
    ) {}

    /**
     * Registra una observación sobre una sección específica del registro,
     * dirigida a un usuario destinatario.
     */
    public function registrarObservacion(
        Model $registro,
        User $revisor,
        string $seccion,
        string $texto,
        ?int $destinatarioId = null,
        ?string $campo = null
    ): Observacion {
        $observacion = $registro->observaciones()->create([
            'seccion'          => $seccion,
            'campo'            => $campo,
            'texto'            => $texto,
            'estatus'          => Observacion::ESTATUS_PENDIENTE,
            'realizada_por'    => $revisor->id,
            'destinatario_id'  => $destinatarioId,
            'atendida'         => false,
        ]);

        // Registrar la observación en la bitácora del REGISTRO PADRE (trámite/agenda/etc.)
        // para que aparezca en su timeline. Observacion es un modelo polimórfico
        // secundario que el AuditObserver no escucha, de ahí el registro manual
        // centralizado en BitacoraService.
        $this->bitacora->registrar(
            $registro,
            'revision',
            'observacion',
            'Observación en "' . $seccion . '": ' . Str::limit($texto, 80),
            $campo ? 'Campo: ' . $campo : null,
            $revisor->id
        );

        return $observacion;
    }

    /**
     * Marca una observación como atendida.
     */
    public function marcarObservacionAtendida(Observacion $observacion): void
    {
        $observacion->update([
            'atendida' => true,
            'estatus'  => Observacion::ESTATUS_ATENDIDA,
        ]);
    }

    /**
     * Aprueba un registro. Solo permite aprobar si todas las observaciones
     * activas están marcadas como atendidas.
     */
    public function aprobar(Model $registro, ?User $aprobador = null, ?string $justificacion = null): bool
    {
        if ($this->tieneObservacionesPendientes($registro)) {
            // Hay observaciones vivas. El aprobador (revisor/admin) puede aprobar por
            // encima SI justifica; entonces esas observaciones quedan SOBRESEÍDAS con el
            // motivo y quién lo decidió (no se borran: quedan trazables). Sin
            // justificación, no se aprueba.
            if ($justificacion === null || trim($justificacion) === '') {
                return false;
            }

            $registro->observaciones()->pendientes()->update([
                'estatus'               => Observacion::ESTATUS_SOBRESEIDA,
                'resuelta_por'          => $aprobador?->id,
                'motivo_sobreseimiento' => $justificacion,
                'resuelta_en'           => now(),
            ]);
        }

        $registro->update(['estatus' => $this->estatusAprobado($registro)]);
        return true;
    }

    /**
     * Sella una observación como VALIDADA: el revisor confirma que la subsanación del
     * enlace quedó bien. Es el cierre "positivo" (distinto del sobreseimiento, que es
     * saltarse una observación sin resolver). Opcional: como 'atendida' ya no bloquea,
     * validar deja constancia pero no es obligatorio para aprobar.
     */
    public function validarObservacion(Observacion $observacion, ?User $revisor = null): void
    {
        $observacion->update([
            'estatus'      => Observacion::ESTATUS_VALIDADA,
            'resuelta_por' => $revisor?->id,
            'resuelta_en'  => now(),
        ]);
    }

    /** El tipo (string) de un registro observable — inverso de resolverRegistro(). */
    public function tipoDe(Model $registro): string
    {
        return match (true) {
            $registro instanceof Tramite               => self::TIPO_TRAMITE,
            $registro instanceof AccionAgenda           => self::TIPO_AGENDA,
            $registro instanceof PropuestaRegulatoria   => self::TIPO_PROPUESTA_REGULATORIA,
            default                                     => abort(404, 'Tipo de registro no soportado.'),
        };
    }

    public function tieneObservacionesPendientes(Model $registro): bool
    {
        return $registro->observaciones()->pendientes()->exists();
    }

    public function resolverRegistro(string $tipo, int $id): Model
    {
        return match($tipo) {
            self::TIPO_TRAMITE               => Tramite::findOrFail($id),
            self::TIPO_AGENDA                => AccionAgenda::findOrFail($id),
            self::TIPO_PROPUESTA_REGULATORIA => PropuestaRegulatoria::findOrFail($id),
            default                          => abort(404, 'Tipo de registro no soportado.'),
        };
    }

    /**
     * Devuelve el catálogo de secciones para un tipo de registro.
     */
    public function seccionesDeTipo(string $tipo): array
    {
        return config("revision.secciones.{$tipo}", []);
    }

    /**
     * Permisos requeridos por tipo de registro.
     */
    public function permisoObservar(string $tipo): string
    {
        return match($tipo) {
            self::TIPO_TRAMITE               => 'tramites.observar',
            self::TIPO_AGENDA                => 'agenda.observar',
            self::TIPO_PROPUESTA_REGULATORIA => 'agenda_regulatoria.observar',
            default                          => 'tramites.observar',
        };
    }

    public function permisoAprobar(string $tipo): string
    {
        return match($tipo) {
            self::TIPO_TRAMITE               => 'tramites.aprobar',
            self::TIPO_AGENDA                => 'agenda.aprobar',
            self::TIPO_PROPUESTA_REGULATORIA => 'agenda_regulatoria.aprobar',
            default                          => 'tramites.aprobar',
        };
    }

    // ========== Internos ==========

    private function marcarComoObservado(Model $registro): void
    {
        // Solo actualiza si el modelo tiene columna `estatus`.
        if (in_array('estatus', $registro->getFillable()) || array_key_exists('estatus', $registro->getAttributes())) {
            // Trámites y agenda comparten vocabulario: ambos usan 'en_observacion'.
            $registro->update(['estatus' => Tramite::ESTATUS_EN_OBSERVACION]);
        }
    }

    /**
     * Estado al que pasa un registro cuando la revisora lo aprueba.
     *
     * Para trámites, aprobar significa enviar a firma (no completar):
     * el trámite todavía debe recibir las firmas antes de quedar completado.
     */
    private function estatusAprobado(Model $registro): string
    {
        // Trámites y agenda comparten vocabulario: aprobar = enviar a firma.
        // El registro pasa a 'completado' cuando recibe las firmas.
        return Tramite::ESTATUS_EN_FIRMA;
    }
}
