<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de bitácora del buscador — Capas 1 y 2 de preparación para IA.
 *
 * Registra cada búsqueda y el feedback de los usuarios sobre los resultados.
 * Estos datos son los inputs directos para un futuro modelo de ranking:
 *
 *   - busqueda_log  → qué busca la gente, qué filtra, qué encuentra
 *   - busqueda_feedback → qué resultado sirvió y cuál no (training labels)
 *
 * El servicio no sabe nada de HTTP ni de vistas (Clean Code §11).
 * Todas las escrituras usan DB::table() directo para evitar el overhead
 * de un modelo Eloquent en una operación que solo hace INSERT.
 *
 * Cada método está envuelto en try-catch para que un error en la bitácora
 * NUNCA reviente la búsqueda principal — la bitácora es observación
 * pasiva, no parte del flujo crítico.
 */
class BusquedaLogService
{
    /**
     * Registra una búsqueda en la bitácora.
     *
     * Se llama DESPUÉS de que BuscadorService devolvió los resultados,
     * así que no agrega latencia perceptible a la respuesta del usuario.
     *
     * @param  string     $consulta        Texto que el usuario escribió.
     * @param  array|null $regulacionIds   IDs de regulaciones filtradas.
     * @param  array|null $tipos           Tipos de fuente filtrados.
     * @param  string     $modo            Modo de búsqueda (completo|enfocado|filtrado).
     * @param  int        $totalResultados Cantidad de resultados encontrados.
     * @param  int        $tiempoMs        Tiempo de respuesta en milisegundos.
     * @param  bool       $tieneDestacada  Si se construyó una respuesta destacada.
     * @return int|null   ID del registro insertado (para vincular feedback después).
     */
    public function registrarBusqueda(
        string $consulta,
        ?array $regulacionIds,
        ?array $tipos,
        string $modo,
        int $totalResultados,
        int $tiempoMs,
        bool $tieneDestacada
    ): ?int {
        try {
            return DB::table('busqueda_log')->insertGetId([
                'user_id'           => Auth::id(),
                'consulta'          => mb_substr($consulta, 0, 500),
                'regulacion_ids'    => $regulacionIds ? json_encode($regulacionIds) : null,
                'tipos'             => $tipos ? json_encode($tipos) : null,
                'modo'              => $modo,
                'total_resultados'  => $totalResultados,
                'tiempo_ms'         => $tiempoMs,
                'tiene_destacada'   => $tieneDestacada,
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            // La bitácora nunca debe reventar la búsqueda.
            Log::warning('BusquedaLog: no se pudo registrar búsqueda', [
                'error' => $e->getMessage(),
                'consulta' => $consulta,
            ]);
            return null;
        }
    }

    /**
     * Registra que el usuario hizo clic en un resultado.
     *
     * Se llama vía AJAX desde el frontend cuando el usuario abre un
     * resultado. Actualiza el registro de la bitácora con el tipo e ID
     * del resultado clickeado.
     *
     * @param  int    $logId           ID del registro en busqueda_log.
     * @param  string $tipoResultado   Tipo del resultado (articulo|tramite|...).
     * @param  int    $resultadoId     ID del resultado en su tabla de origen.
     */
    public function registrarClic(int $logId, string $tipoResultado, int $resultadoId): void
    {
        try {
            DB::table('busqueda_log')
                ->where('id', $logId)
                ->update([
                    'resultado_clickeado_tipo' => $tipoResultado,
                    'resultado_clickeado_id'   => $resultadoId,
                ]);
        } catch (\Throwable $e) {
            Log::warning('BusquedaLog: no se pudo registrar clic', [
                'error' => $e->getMessage(),
                'log_id' => $logId,
            ]);
        }
    }

    /**
     * Registra feedback (👍 o 👎) sobre un resultado.
     *
     * @param  int         $busquedaLogId  ID del registro en busqueda_log.
     * @param  string      $consulta       La consulta que produjo este resultado.
     * @param  string      $tipoResultado  Tipo del resultado.
     * @param  int         $resultadoId    ID del resultado en su tabla de origen.
     * @param  string|null $titulo         Título del resultado (para contexto).
     * @param  bool        $util           true = 👍, false = 👎.
     * @return bool
     */
    public function registrarFeedback(
        int $busquedaLogId,
        string $consulta,
        string $tipoResultado,
        int $resultadoId,
        ?string $titulo,
        bool $util
    ): bool {
        try {
            DB::table('busqueda_feedback')->insert([
                'user_id'           => Auth::id(),
                'busqueda_log_id'   => $busquedaLogId,
                'consulta'          => mb_substr($consulta, 0, 500),
                'tipo_resultado'    => $tipoResultado,
                'resultado_id'      => $resultadoId,
                'titulo_resultado'  => $titulo ? mb_substr($titulo, 0, 500) : null,
                'util'              => $util,
                'created_at'        => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('BusquedaLog: no se pudo registrar feedback', [
                'error' => $e->getMessage(),
                'tipo' => $tipoResultado,
                'id' => $resultadoId,
            ]);
            return false;
        }
    }
}
