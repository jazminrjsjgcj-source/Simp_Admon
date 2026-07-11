<?php

namespace App\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Punto único para registrar eventos manuales en la tabla `bitacora`.
 *
 * El AuditObserver solo audita cambios automáticos de modelos Eloquent
 * (created/updated/deleted). Pero hay eventos de negocio que deben quedar en
 * el timeline del registro PADRE aunque ocurran sobre una entidad secundaria:
 *
 *   - una observación de revisión (modelo Observacion polimórfico) que debe
 *     verse en el historial del trámite/agenda al que pertenece;
 *   - un evento de hito (subir evidencia, aprobar, rechazar) que debe verse
 *     en el historial de la acción de agenda padre.
 *
 * Antes, cada lugar armaba su propio DB::table('bitacora')->insert([...]) con
 * el mismo formato copiado. Este servicio centraliza ese formato y su manejo
 * de errores, de modo que la estructura de la tabla se conozca en un solo
 * sitio. Nota: NO cubre la tabla `acl_bitacora` (AclService), que tiene un
 * esquema distinto y es un sistema de auditoría aparte.
 */
class BitacoraService
{
    /**
     * Inserta un evento en la bitácora, apuntando al registro padre.
     *
     * @param  Model        $padre    Registro al que se asocia el evento (su
     *                                clase e id se guardan como auditable).
     * @param  string       $modulo   Módulo de origen ('agenda', 'revision'...).
     * @param  string       $tipo     Tipo de evento ('hito', 'observacion'...).
     * @param  string       $accion   Texto legible de lo ocurrido.
     * @param  string|null  $detalle  Texto adicional opcional.
     * @param  int|null     $usuarioId Quién lo hizo; por defecto, el autenticado.
     */
    public function registrar(
        Model $padre,
        string $modulo,
        string $tipo,
        string $accion,
        ?string $detalle = null,
        ?int $usuarioId = null
    ): void {
        try {
            DB::table('bitacora')->insert([
                'auditable_type' => get_class($padre),
                'auditable_id'   => $padre->id,
                'usuario_id'     => $usuarioId ?? Auth::id(),
                'modulo'         => $modulo,
                'tipo'           => $tipo,
                'accion'         => $accion,
                'detalle'        => $detalle,
                'ip_address'     => Request::ip(),
                'created_at'     => now(),
            ]);
        } catch (Exception $e) {
            // Un fallo al auditar no debe tumbar la operación principal; se
            // registra en el log para diagnóstico y la petición continúa.
            Log::error('BitacoraService error: ' . $e->getMessage());
        }
    }

    // ── Lectura ────────────────────────────────────────────────────────────

    /**
     * Mapa de tipos legibles a clases Eloquent, usado por el timeline y el
     * historial para resolver qué modelo auditar.
     */
    public const MAPA_MODELOS = [
        'tramite'    => \App\Models\Tramite::class,
        'agenda'     => \App\Models\AccionAgenda::class,
        'propuesta'  => \App\Models\PropuestaRegulatoria::class,
        'regulacion' => \App\Models\Regulacion::class,
        'air'        => \App\Models\AnalisisImpactoRegulatorio::class,
    ];

    /**
     * Devuelve los eventos más recientes de la bitácora para un registro.
     *
     * Centraliza la query que antes se repetía en tres lugares: el partial
     * timeline, el HistorialController::index() y el HistorialController::json().
     * Al vivir aquí, la estructura de la tabla se conoce en un solo sitio.
     *
     * @param  string  $tipo   Clave legible: 'tramite', 'propuesta', etc.
     * @param  int     $id     ID del registro auditado.
     * @param  int     $limite Máximo de eventos a devolver (default 10).
     * @return array{eventos: \Illuminate\Support\Collection, total: int}
     */
    public static function eventosRecientes(string $tipo, int $id, int $limite = 10): array
    {
        $claseModelo = self::MAPA_MODELOS[$tipo] ?? null;

        if (!$claseModelo) {
            return ['eventos' => collect(), 'total' => 0];
        }

        $base = DB::table('bitacora')
            ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
            ->where('bitacora.auditable_type', $claseModelo)
            ->where('bitacora.auditable_id', $id);

        $total = (clone $base)->count();

        $eventos = $base
            ->orderByDesc('bitacora.created_at')
            ->limit($limite)
            ->select(
                'bitacora.accion',
                'bitacora.tipo',
                'bitacora.detalle',
                'bitacora.created_at',
                'users.name as usuario_nombre'
            )
            ->get();

        return ['eventos' => $eventos, 'total' => $total];
    }
}
