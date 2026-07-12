<?php

namespace App\Services;

use App\Models\AccionAgenda;
use App\Models\PropuestaRegulatoria;
use App\Models\Regulacion;
use App\Models\Tramite;
use App\Models\User;
use App\Notifications\AvisoPunta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Punto único que dispara las notificaciones del flujo de trabajo.
 *
 * Cada método corresponde a un evento del flujo (observar, reenviar, aprobar)
 * y resuelve a quién avisar según el diseño confirmado:
 *
 *   - Se observa  → creador + enlace/sujeto + jurídico de la dependencia
 *   - Se reenvía  → revisora(s) + sujeto + jurídico de la dependencia
 *   - Se aprueba  → creador + enlace/sujeto + jurídico de la dependencia
 *
 * Reglas transversales:
 *   - Al creador del registro se le avisa siempre (es el dueño).
 *   - A quien ejecuta la acción NUNCA se le avisa (no te avisas a ti mismo).
 *   - No se duplica: un mismo usuario recibe una sola notificación por evento.
 *
 * La bitácora NO se toca aquí: el AuditObserver ya registra el cambio de
 * estatus automáticamente cuando el registro se actualiza.
 */
class NotificadorService
{
    /**
     * La revisora observó el registro: avisa al creador, al enlace/sujeto del
     * área y al jurídico de la dependencia.
     */
    public function observado(Model $registro, User $autor): void
    {
        $destinatarios = $this->participantesDelArea($registro)
            ->push($this->creador($registro))
            ->merge($this->porRolYDependencia(User::ROL_JURIDICO, $registro));

        $this->enviar(
            $destinatarios,
            $autor,
            'ti-eye',
            'Nueva observación',
            'Hay una observación en "' . $this->nombre($registro) . '" que requiere atención.',
            $registro
        );
    }

    /**
     * El enlace/sujeto reenvió el registro corregido: avisa a la(s) revisora(s),
     * al sujeto y al jurídico de la dependencia.
     */
    public function reenviado(Model $registro, User $autor): void
    {
        $destinatarios = $this->todasLasRevisoras()
            ->merge($this->porRolYDependencia(User::ROL_SUJETO, $registro))
            ->merge($this->porRolYDependencia(User::ROL_JURIDICO, $registro))
            ->push($this->creador($registro));

        $this->enviar(
            $destinatarios,
            $autor,
            'ti-writing',
            'Registro corregido',
            'El registro "' . $this->nombre($registro) . '" fue corregido y reenviado.',
            $registro
        );
    }

    /**
     * Se aprobó el registro (pasa a firma): avisa al creador, al enlace/sujeto
     * del área y al jurídico de la dependencia.
     */
    public function aprobado(Model $registro, User $autor): void
    {
        $destinatarios = $this->participantesDelArea($registro)
            ->push($this->creador($registro))
            ->merge($this->porRolYDependencia(User::ROL_JURIDICO, $registro));

        $this->enviar(
            $destinatarios,
            $autor,
            'ti-check',
            'Listo para firma',
            'El registro "' . $this->nombre($registro) . '" fue aprobado y está esperando firma.',
            $registro
        );
    }

    /**
     * Una regulación fue re-estructurada: avisa a los enlaces de los trámites
     * que la citan como fundamento jurídico. El enlace recibe la alerta en la
     * campanita (visible desde cualquier pantalla) y por correo electrónico.
     *
     * NO cambia el estatus de ningún trámite — solo notifica.
     *
     * @param  Regulacion  $regulacion  La regulación que fue re-estructurada.
     * @param  User        $autor       Quien ejecutó la re-estructuración.
     * @param  array       $citaciones  Resultado de $regulacion->citacionesEnTramites().
     */
    public function regulacionReEstructurada(Regulacion $regulacion, User $autor, array $citaciones): void
    {
        if ($citaciones['total'] === 0) {
            return;
        }

        $articulosTexto = !empty($citaciones['articulos'])
            ? ' Artículos referenciados: ' . implode(', ', $citaciones['articulos']) . '.'
            : '';

        foreach ($citaciones['tramites'] as $tramite) {
            // Se avisa al ENLACE (quien mantiene el trámite) y al CREADOR (quien puso la cita
            // y sabe por qué). A los dos: el enlace puede haber cambiado desde que se registró.
            $enlace  = $tramite->enlace_id  ? User::find($tramite->enlace_id)  : null;
            $creador = $tramite->created_by ? User::find($tramite->created_by) : null;

            $destinatarios = collect([$enlace, $creador])->filter()->unique('id');

            if ($destinatarios->isEmpty()) {
                // ── POR QUÉ ESTE LOG NO ES OPCIONAL ──
                //
                // Antes aquí solo había un `continue;`. Sin log, sin aviso, sin rastro.
                //
                // Y resultó no ser un caso borde: era EL caso. citacionesEnTramites() cargaba
                // el trámite con `tramite:id,nombre_oficial,homoclave` — sin enlace_id ni
                // created_by. Los dos salían null, esta lista salía siempre vacía, y el aviso
                // NO SE ENVIABA NUNCA. Para ningún trámite. Ni una vez.
                //
                // El bug sobrevivió porque este `continue` se lo tragaba todo. Con un log,
                // alguien lo habría visto el primer día.
                //
                // La lección: en un bucle de notificaciones, un `continue` silencioso es un
                // fallo esperando a pasar. Si no hay a quién avisar, eso ES la noticia —
                // significa que un trámite quedó afectado y nadie va a revisarlo.
                Log::warning(
                    'Regulación re-estructurada: no se pudo avisar a nadie de un trámite afectado '
                    . '(sin destinatarios). El trámite puede quedar con un fundamento jurídico que '
                    . 'apunta a un artículo que ya dice otra cosa, y nadie lo revisará.',
                    [
                        'regulacion_id' => $regulacion->id,
                        'tramite_id'    => $tramite->id,
                        'tramite'       => $tramite->nombre_oficial ?? null,
                        'articulos'     => $citaciones['articulos'] ?? [],
                    ]
                );

                continue;
            }

            Notification::send($destinatarios, new AvisoPunta(
                icono:   'ti-alert-triangle',
                titulo:  'Regulación actualizada',
                mensaje: 'La regulación "' . $regulacion->nombre . '" fue re-estructurada.'
                       . $articulosTexto
                       . ' Verifique que los fundamentos jurídicos de "' . ($tramite->nombre_oficial ?? 'Trámite #' . $tramite->id) . '" sigan siendo correctos.',
                url:     route('tramites.show', $tramite->id),
            ));
        }
    }

    /* ----------------------------------------------------------------------
     | Resolución de destinatarios
     |----------------------------------------------------------------------*/

    /** Enlace y sujeto de la dependencia del registro. */
    private function participantesDelArea(Model $registro)
    {
        return $this->porRolYDependencia(User::ROL_ENLACE, $registro)
            ->merge($this->porRolYDependencia(User::ROL_SUJETO, $registro));
    }

    /** Usuarios con un rol dado en la dependencia del registro. */
    private function porRolYDependencia(string $rol, Model $registro)
    {
        $depId = $registro->dependencia_id ?? null;
        if (!$depId) {
            return collect();
        }

        return User::where('rol', $rol)
            ->where('dependencia_id', $depId)
            ->where('activo', true)
            ->get();
    }

    /** Todas las revisoras (la revisora es autoridad evaluadora, ve todo). */
    private function todasLasRevisoras()
    {
        return User::where('rol', User::ROL_REVISORA)
            ->where('activo', true)
            ->get();
    }

    /** El usuario que creó el registro (si existe). */
    private function creador(Model $registro): ?User
    {
        return $registro->created_by ? User::find($registro->created_by) : null;
    }

    /* ----------------------------------------------------------------------
     | Envío
     |----------------------------------------------------------------------*/

    /**
     * Limpia la lista (sin nulos, sin el autor, sin duplicados) y notifica.
     */
    private function enviar($destinatarios, User $autor, string $icono, string $titulo, string $mensaje, Model $registro): void
    {
        $limpios = collect($destinatarios)
            ->filter()                                   // quita nulos
            ->reject(fn ($u) => $u->id === $autor->id)    // nunca a quien actúa
            ->unique('id')                                // sin duplicados
            ->values();

        if ($limpios->isEmpty()) {
            return;
        }

        Notification::send($limpios, new AvisoPunta(
            icono:   $icono,
            titulo:  $titulo,
            mensaje: $mensaje,
            url:     $this->urlDetalle($registro),
        ));
    }

    /** Arma la URL del detalle según el tipo de registro. */
    private function urlDetalle(Model $registro): ?string
    {
        return match (true) {
            $registro instanceof Tramite              => route('tramites.show', $registro->id),
            $registro instanceof AccionAgenda         => route('agenda.show', $registro->id),
            $registro instanceof PropuestaRegulatoria => route('propuestas.show', $registro->id),
            default                                   => null,
        };
    }

    /** Nombre legible del registro para el texto del aviso. */
    private function nombre(Model $registro): string
    {
        return $registro->nombre_oficial
            ?? $registro->nombre
            ?? $registro->descripcion
            ?? ('registro #' . $registro->id);
    }
}