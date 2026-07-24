<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        } catch (Throwable $e) {
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
            // ══════════════════════════════════════════════════════════════════
            // EL CANDADO: solo puedes tocar TUS PROPIAS búsquedas
            // ══════════════════════════════════════════════════════════════════
            //
            // El $logId viene del NAVEGADOR. El controlador lo valida como entero... y nada más.
            // Comprueba el TIPO, no la PROPIEDAD.
            //
            // Sin este ->where('user_id'), cualquiera podía escribir con F12:
            //
            //     for (let i = 1; i < 100000; i++) {
            //         fetch('/buscar/clic', { body: JSON.stringify({ log_id: i, ... }) });
            //     }
            //
            // Y sobrescribir el registro de clic de TODAS las búsquedas del Ayuntamiento.
            //
            // ── Por qué esto importa más de lo que parece ──
            //
            // El docblock de esta clase lo dice: estos datos son "los inputs directos para un
            // futuro modelo de ranking" y "training labels".
            //
            // Un buscador que aprende de datos manipulables SE PUEDE ENVENENAR. Alguien marca su
            // trámite como el clicado en cien consultas, y el ranking empieza a favorecerlo. Y
            // como el modelo se entrena solo, NADIE SABRÁ POR QUÉ el buscador se volvió raro.
            //
            // No es un bug de hoy: es un bug de dentro de un año, cuando el modelo esté entrenado
            // y nadie recuerde de dónde salieron los datos.
            $filas = DB::table('busqueda_log')
                ->where('id', $logId)
                ->where('user_id', Auth::id())
                ->update([
                    'resultado_clickeado_tipo' => $tipoResultado,
                    'resultado_clickeado_id'   => $resultadoId,
                ]);

            if ($filas === 0) {
                // Esa búsqueda no es suya. O no existe, o es de otra persona.
                //
                // NO SE ABORTA NADA, y es deliberado: esto es una bitácora pasiva, y reventar la
                // navegación de un ciudadano por un registro de clic sería mucho peor que el bug
                // que estamos cerrando. Simplemente no se escribe.
                //
                // Pero SÍ se registra. Un intento fallido es tan informativo como uno que sale
                // bien: significa que hay alguien probando ids que no son suyos. Sin este log,
                // podría hacerlo mil veces y nadie lo sabría nunca.
                Log::warning('BusquedaLog: intento de registrar un clic en una búsqueda ajena.', [
                    'log_id'     => $logId,
                    'usuario_id' => Auth::id(),
                    'ip'         => request()?->ip(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('BusquedaLog: no se pudo registrar clic', [
                'error' => $e->getMessage(),
                'log_id' => $logId,
            ]);
        }
    }

    /**
     * Registra el voto del usuario sobre un resultado: le sirvió o no le sirvió.
     *
     * @param  int         $busquedaLogId  ID del registro en busqueda_log.
     * @param  string      $consulta       La consulta que produjo este resultado.
     * @param  string      $tipoResultado  Tipo del resultado.
     * @param  int         $resultadoId    ID del resultado en su tabla de origen.
     * @param  string|null $titulo         Título del resultado (para contexto).
     * @param  bool        $util           true = le sirvió, false = no le sirvió.
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
            $usuarioId = Auth::id();

            // ── CANDADO 1: la búsqueda tiene que ser TUYA ──
            //
            // El $busquedaLogId viene del navegador. Sin esta comprobación, cualquiera podía votar
            // sobre las búsquedas de otras personas — y estos datos son las "training labels" de
            // un futuro modelo de ranking. Un buscador entrenado con votos manipulados es un
            // buscador envenenado, y nadie sabría por qué.
            $esSuya = DB::table('busqueda_log')
                ->where('id', $busquedaLogId)
                ->where('user_id', $usuarioId)
                ->exists();

            if (! $esSuya) {
                Log::warning('BusquedaLog: intento de votar sobre una búsqueda ajena.', [
                    'busqueda_log_id' => $busquedaLogId,
                    'usuario_id'      => $usuarioId,
                    'ip'              => request()?->ip(),
                ]);

                return false;
            }

            // ── CANDADO 2: un voto por persona y resultado ──
            //
            // Sin esto, un solo usuario puede votar MIL VECES el mismo resultado con un bucle de
            // fetch(). Y mil votos "útil" sobre un trámite lo empujarían al primer puesto del
            // ranking en cuanto el modelo se entrene.
            //
            // El candado 1 impide votar en búsquedas ajenas. Este impide inflar las propias. Los
            // dos hacen falta: sin el segundo, basta con hacer una búsqueda y votarla diez mil
            // veces.
            //
            // updateOrInsert en vez de insert: si ya votó, se CAMBIA su voto. Es lo que un
            // usuario espera cuando pulsa "Sí" después de haber pulsado "No".
            // ══════════════════════════════════════════════════════════════════
            // OJO CON updateOrInsert Y LAS COLUMNAS NOT NULL
            // ══════════════════════════════════════════════════════════════════
            //
            // La primera versión de esto era un updateOrInsert(), y REVENTABA:
            //
            //     Not null violation: null value in column "consulta"
            //     SQL: insert into busqueda_feedback (user_id, busqueda_log_id,
            //                                         tipo_resultado, resultado_id)
            //
            // Fíjate en ese INSERT: NO LLEVA `consulta`. Y esa columna es NOT NULL.
            //
            // ── Por qué ──
            //
            // Cuando la fila no existe, updateOrInsert hace un INSERT con las claves del PRIMER
            // array (el WHERE) y después un UPDATE con el segundo. Pero el INSERT inicial no
            // incluye las columnas del segundo array — y si alguna es NOT NULL, explota antes de
            // llegar al UPDATE.
            //
            // ── Y la consecuencia era peor que el bug ──
            //
            //     In failed sql transaction: current transaction is aborted
            //
            // Una violación de constraint en PostgreSQL ABORTA LA TRANSACCIÓN ENTERA. A partir
            // de ahí, TODAS las consultas siguientes fallan.
            //
            // El try/catch se tragaba el error... pero la transacción ya estaba muerta. Un
            // registro de feedback —una bitácora pasiva, lo menos importante del sistema— podía
            // tumbar la petición completa de un ciudadano.
            //
            // ── El arreglo ──
            //
            // Comprobar si existe, y hacer update o insert explícitamente. Más verboso, y
            // predecible: el INSERT lleva TODAS las columnas.
            $claves = [
                'user_id'         => $usuarioId,
                'busqueda_log_id' => $busquedaLogId,
                'tipo_resultado'  => $tipoResultado,
                'resultado_id'    => $resultadoId,
            ];

            $datos = [
                'consulta'         => mb_substr($consulta, 0, 500),
                'titulo_resultado' => $titulo ? mb_substr($titulo, 0, 500) : null,
                'util'             => $util,
            ];

            $yaVoto = DB::table('busqueda_feedback')->where($claves)->exists();

            if ($yaVoto) {
                // Cambió de opinión: se actualiza su voto, no se duplica.
                DB::table('busqueda_feedback')->where($claves)->update($datos);
            } else {
                DB::table('busqueda_feedback')->insert(
                    $claves + $datos + ['created_at' => now()]
                );
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('BusquedaLog: no se pudo registrar feedback', [
                'error' => $e->getMessage(),
                'tipo' => $tipoResultado,
                'id' => $resultadoId,
            ]);
            return false;
        }
    }

    /**
     * Últimas búsquedas DISTINTAS de un usuario, de la más reciente a la más vieja.
     *
     * Alimenta la columna "Búsquedas recientes" del buscador. Se agrupa por texto
     * para no repetir la misma consulta cinco veces seguidas, y se ordena por la
     * última vez que la hizo.
     *
     * Nunca lanza: si la consulta falla, la columna sale vacía y el buscador sigue
     * funcionando igual (esto es una comodidad, no una función crítica).
     *
     * @return array<int, string>
     */
    public function recientes(?int $usuarioId, int $limite = 8): array
    {
        if ($usuarioId === null) {
            return [];
        }

        try {
            return DB::table('busqueda_log')
                ->where('user_id', $usuarioId)
                ->whereNotNull('consulta')
                ->where('consulta', '<>', '')
                ->groupBy('consulta')
                ->orderByRaw('MAX(created_at) DESC')
                ->limit($limite)
                ->pluck('consulta')
                ->all();
        } catch (Throwable $e) {
            Log::warning('BusquedaLog: no se pudieron leer las búsquedas recientes', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
