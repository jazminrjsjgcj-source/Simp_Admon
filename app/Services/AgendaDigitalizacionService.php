<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\Reingenieria;
use App\Models\Tramite;
use App\Models\User;
use App\Notifications\AvisoPunta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Servicio que conecta la Agenda de Digitalización con la Biblioteca
 * del Digitalizador.
 *
 * Cuando una acción de agenda de tipo "digitalización" se completa
 * (firma de enlace + sujeto obligado), este servicio:
 *
 *   1. Marca el trámite vinculado con digitalizacion_origen = 'agenda'
 *   2. Crea una reingeniería v1 vinculada a la acción de agenda
 *   3. Notifica a los digitalizadores que hay un nuevo trámite en la biblioteca
 *
 * También gestiona las solicitudes de reingeniería directa (fuera de Agenda):
 *   1. El enlace/digitalizador solicita la reingeniería directa
 *   2. La revisora aprueba o rechaza
 *   3. Si aprueba, se crea la reingeniería y se marca el trámite
 *
 * El servicio se llama desde:
 *   - FirmaController (al completar firmas de AccionAgenda de tipo digitalización)
 *   - DigitalizacionController (al solicitar/aprobar reingeniería directa)
 */
class AgendaDigitalizacionService
{
    /**
     * Vincula un trámite a la biblioteca del digitalizador desde una
     * acción de agenda completada.
     *
     * Se llama cuando la AccionAgenda pasa a estatus 'completado' y
     * su tipo es 'digitalizacion'.
     *
     * @param  AccionAgenda $accion  La acción completada.
     * @return Reingenieria|null     La reingeniería creada, o null si no aplica.
     */
    public function vincularDesdeAgenda(AccionAgenda $accion): ?Reingenieria
    {
        // Solo aplica a acciones de digitalización con trámite vinculado
        if ($accion->tipo !== 'digitalizacion' || !$accion->tramite_id) {
            return null;
        }

        $tramite = $accion->tramite;
        if (!$tramite) {
            return null;
        }

        // No duplicar si ya existe una reingeniería vinculada a esta acción
        $existente = Reingenieria::where('agenda_accion_id', $accion->id)->first();
        if ($existente) {
            return $existente;
        }

        // Marcar el trámite como proveniente de agenda
        $tramite->update([
            'digitalizacion_origen' => 'agenda',
        ]);

        // Crear la reingeniería v1 vinculada a la acción
        $ultimaVersion = $tramite->reingenierias()->max('version') ?? 0;

        $reingenieria = Reingenieria::create([
            'tramite_id'       => $tramite->id,
            'agenda_accion_id' => $accion->id,
            'origen'           => Reingenieria::ORIGEN_AGENDA,
            'version'          => $ultimaVersion + 1,
            'estado'           => Reingenieria::ESTADO_EN_REINGENIERIA,
            'created_by'       => $accion->created_by,
        ]);

        // Notificar a los digitalizadores. El texto usa la naturaleza real del
        // registro (trámite o servicio), no "trámite" fijo: un servicio que llega
        // a la biblioteca debe leerse como servicio.
        $tipoTexto = mb_strtolower($tramite->naturalezaLegible()); // 'trámite' | 'servicio'

        $this->notificarDigitalizadores(
            $tramite,
            'Nuevo ' . $tipoTexto . ' en biblioteca',
            'El ' . $tipoTexto . ' "' . $tramite->nombre_oficial . '" fue enviado a la Biblioteca de Digitalización '
            . 'desde la Agenda de Digitalización (acción ' . ($accion->folio ?? '#' . $accion->id) . ').',
        );

        Log::info('AgendaDigitalizacion: vinculado trámite #{id} desde acción #{accion}', [
            'id'     => $tramite->id,
            'accion' => $accion->id,
        ]);

        return $reingenieria;
    }

    /**
     * Procesa la solicitud de reingeniería directa.
     *
     * Se llama desde DigitalizacionController cuando el digitalizador
     * solicita una reingeniería fuera de la Agenda.
     *
     * @param  Tramite $tramite  El trámite a digitalizar.
     * @param  array   $datos    Datos de la solicitud (motivo, justificación, etc.)
     * @param  User    $solicitante  Usuario que solicita.
     * @return Reingenieria
     */
    public function solicitarDirecta(Tramite $tramite, array $datos, User $solicitante): Reingenieria
    {
        // Marcar el trámite como reingeniería directa
        $tramite->update([
            'digitalizacion_origen' => 'directa',
        ]);

        $ultimaVersion = $tramite->reingenierias()->max('version') ?? 0;

        $reingenieria = Reingenieria::create([
            'tramite_id'       => $tramite->id,
            'origen'           => Reingenieria::ORIGEN_DIRECTA,
            'version'          => $ultimaVersion + 1,
            'estado'           => Reingenieria::ESTADO_EN_REINGENIERIA,
            'motivo_directa'   => $datos['motivo_directa'],
            'justificacion'    => $datos['justificacion'],
            'area_solicitante' => $datos['area_solicitante'] ?? null,
            'fecha_limite'     => $datos['fecha_limite'] ?? null,
            'documento_soporte'=> $datos['documento_soporte'] ?? null,
            'created_by'       => $solicitante->id,
        ]);

        // Notificar a revisoras y digitalizadores
        $this->notificarDigitalizadores(
            $tramite,
            'Reingeniería directa solicitada',
            'Se solicitó reingeniería directa para "' . $tramite->nombre_oficial . '". '
            . 'Motivo: ' . ucfirst(str_replace('_', ' ', $datos['motivo_directa'])) . '.',
        );

        return $reingenieria;
    }

    /**
     * Notifica a todos los usuarios con rol digitalizador.
     */
    private function notificarDigitalizadores(Tramite $tramite, string $titulo, string $mensaje): void
    {
        try {
            $digitalizadores = User::where('activo', true)
                ->whereHas('roles', fn ($q) => $q->where('codigo', 'digitalizador'))
                ->get();

            if ($digitalizadores->isEmpty()) {
                // Fallback: buscar por campo 'rol' directo si no usan la tabla pivot
                $digitalizadores = User::where('activo', true)
                    ->where('rol', 'digitalizador')
                    ->get();
            }

            if ($digitalizadores->isNotEmpty()) {
                Notification::send($digitalizadores, new AvisoPunta(
                    icono:   'ti-device-laptop',
                    titulo:  $titulo,
                    mensaje: $mensaje,
                    url:     route('digitalizacion.show', $tramite->id),
                ));
            }
        } catch (Throwable $e) {
            // Las notificaciones nunca deben reventar el flujo principal
            Log::warning('AgendaDigitalizacion: error al notificar', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
