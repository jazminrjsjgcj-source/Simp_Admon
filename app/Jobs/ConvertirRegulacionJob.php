<?php

namespace App\Jobs;

use App\Models\Regulacion;
use App\Services\RegulacionConversorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Convierte el archivo original de una regulación (PDF o Word) a texto (Markdown).
 *
 * ── Por qué esto ocurre en segundo plano ─────────────────────────────
 *
 * Convertir un PDF puede tardar minutos: primero se intenta con pdftotext, y si el
 * resultado no es legible, con LibreOffice — que es lento y come memoria.
 *
 * Hasta ahora eso pasaba DENTRO de la petición del usuario, que se quedaba esperando. Y si
 * el proceso moría a medias (nginx cortaba, php-fpm cortaba, el kernel mataba a LibreOffice
 * por RAM), la regulación se quedaba en 'procesando' PARA SIEMPRE: sin error, sin aparecer
 * en ninguna lista de fallos, sin que nadie se enterara.
 *
 * Ahora lo hace el worker. Si el trabajo revienta o se pasa del timeout, failed() deja la
 * regulación en 'error' con un mensaje. Un fallo controlado deja rastro; un proceso muerto
 * de golpe, no.
 *
 * ══════════════════════════════════════════════════════════════════════
 * QUÉ SE ENDURECIÓ RESPECTO A LA VERSIÓN ANTERIOR
 * ══════════════════════════════════════════════════════════════════════
 *
 * El job ya existía, escrito y correcto en lo esencial. Pero NADIE LO DESPACHABA: la
 * conversión se hacía en el hilo de la petición, directamente desde el controlador. Era
 * código muerto. Tres cosas se le arreglaron antes de ponerlo a trabajar de verdad:
 *
 * ── 1. Reventaba si borraban la regulación mientras estaba en cola ──
 *
 *     public function failed(Throwable $e): void
 *     {
 *         $this->regulacion->update([...]);   // ← si ya no existe, esto explota
 *     }
 *
 * El job usa SerializesModels: guarda solo el ID y vuelve a cargar el modelo al ejecutarse.
 * Si el usuario borra la regulación mientras el trabajo espera en la cola, la carga lanza
 * ModelNotFoundException... y entonces failed() intenta actualizar un modelo que no existe.
 * Una excepción DENTRO del manejador de excepciones: eso tumba al worker.
 *
 * Ahora failed() recarga el modelo con cuidado y, si ya no está, lo registra y sale.
 *
 * ── 2. Se podían encolar diez conversiones de la misma regulación ──
 *
 * Nada impedía que un usuario diera cinco veces a "Reintentar conversión" y encolara cinco
 * trabajos idénticos. Los cinco convertirían el mismo archivo, uno detrás de otro, pisándose
 * el resultado y ocupando al worker durante media hora.
 *
 * ShouldBeUnique lo impide: mientras haya una conversión de esta regulación en cola o en
 * marcha, las demás se descartan solas.
 *
 * ── 3. El timeout no dejaba margen ──
 *
 * Eran 120 segundos. LibreOffice con un PDF grande se pasa de ahí sin despeinarse, y un
 * timeout demasiado corto convierte conversiones legítimas en errores. Ahora son 300, que es
 * el mismo valor que lleva el worker en docker-compose (si el del job fuera mayor que el del
 * worker, el worker mataría el proceso sin que failed() llegara a correr, y volveríamos al
 * problema del principio).
 */
class ConvertirRegulacionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Un reintento y ya. Si un PDF tumbó el proceso, el segundo intento probablemente lo
     * tumbe igual: reintentar diez veces solo alarga la agonía y llena el log. El primer
     * reintento sí vale la pena — cubre los fallos transitorios (disco ocupado, LibreOffice
     * que no arrancó).
     */
    public int $tries = 2;

    /**
     * Cinco minutos. Tiene que ser IGUAL O MENOR que el --timeout del worker en
     * docker-compose. Si fuera mayor, el worker mataría el proceso antes de que el job
     * pudiera reaccionar, y failed() no llegaría a ejecutarse: la regulación se quedaría en
     * 'procesando' — exactamente el bug que este job viene a evitar.
     */
    public int $timeout = 300;

    /**
     * Cuánto tiempo se considera "único" este trabajo. Pasado ese plazo, se puede volver a
     * encolar una conversión de la misma regulación aunque la anterior siga marcada como
     * activa (por ejemplo, si el worker murió sin liberar el candado).
     *
     * Va un poco por encima del timeout: lo justo para que no se solapen, sin dejar la
     * regulación bloqueada media hora si algo va mal.
     */
    public int $uniqueFor = 360;

    public function __construct(public Regulacion $regulacion) {}

    /** El candado de unicidad es por regulación: dos regulaciones distintas sí se convierten a la vez. */
    public function uniqueId(): string
    {
        return (string) $this->regulacion->id;
    }

    public function handle(RegulacionConversorService $conversor): void
    {
        $conversor->convertirAMarkdown($this->regulacion);
    }

    /**
     * Se ejecuta cuando el job agota sus reintentos o se pasa del timeout.
     *
     * Es la red de seguridad entera: sin esto, un fallo dejaría la regulación en 'procesando'
     * para siempre, que no es un estado de error y por tanto no aparece en ninguna lista de
     * fallos ni avisa a nadie.
     */
    public function failed(Throwable $e): void
    {
        // El modelo puede haber desaparecido mientras el job esperaba en la cola. Recargarlo
        // desde la base, en vez de fiarse del que viene serializado, evita que el manejador de
        // errores lance su propia excepción — y tumbe al worker con ella.
        $regulacion = Regulacion::find($this->regulacion->id);

        if (! $regulacion) {
            Log::warning('La conversión falló, pero la regulación ya no existe (se borró mientras estaba en cola).', [
                'regulacion_id' => $this->regulacion->id,
            ]);

            return;
        }

        $mensaje = $e->getMessage();

        // Un timeout no le dice nada a nadie. Se traduce a algo accionable.
        if (stripos($mensaje, 'timed out') !== false
            || stripos($mensaje, 'timeout') !== false
            || stripos($mensaje, 'exceeded') !== false) {
            $mensaje = 'El archivo es demasiado grande o pesado para convertirlo automáticamente '
                . '(se agotó el tiempo de procesamiento). '
                . 'Sugerencia: ábralo en Word y guárdelo como .docx, o divida el documento en partes, '
                . 'y vuelva a subirlo.';
        }

        $regulacion->update([
            'conversion_estatus' => Regulacion::CONVERSION_ERROR,
            'conversion_error'   => $mensaje,
        ]);

        Log::error('Falló la conversión de una regulación.', [
            'regulacion_id' => $regulacion->id,
            'nombre'        => $regulacion->nombre,
            'error'         => $e->getMessage(),
        ]);
    }
}
