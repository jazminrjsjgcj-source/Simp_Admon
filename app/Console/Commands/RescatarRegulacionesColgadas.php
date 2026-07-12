<?php

namespace App\Console\Commands;

use App\Models\Regulacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rescata las regulaciones que se quedaron colgadas en "procesando".
 *
 * ── El problema, explicado desde cero ────────────────────────────────
 *
 * Cuando alguien sube un PDF o un Word, el sistema lo convierte a texto. Esa conversión
 * ocurre EN LA MISMA PETICIÓN (no hay cola: QUEUE_CONNECTION=sync y el
 * ConvertirRegulacionJob no se despacha desde ningún sitio). Y hace esto:
 *
 *     $regulacion->update(['conversion_estatus' => 'procesando']);
 *     try {
 *         // ... pdftotext, PHPWord, LibreOffice ...
 *     } catch (Throwable $e) {
 *         $regulacion->update(['conversion_estatus' => 'error', ...]);
 *     }
 *
 * El catch está bien hecho: atrapa cualquier Throwable y deja la regulación en 'error' con
 * un mensaje legible. Cubre todo lo que PHP PUEDE capturar.
 *
 * El agujero es lo que PHP NO puede capturar:
 *
 *   - nginx corta por proxy_read_timeout
 *   - php-fpm corta por request_terminate_timeout
 *   - el kernel mata el proceso porque LibreOffice se comió la RAM con un PDF de 300 páginas
 *   - el contenedor se reinicia a mitad
 *
 * En todos esos casos el proceso MUERE DE GOLPE. No hay catch, no hay finally, no hay nada.
 * Y la fila se queda en 'procesando'. Para siempre.
 *
 * ── Por qué eso es peor de lo que parece ─────────────────────────────
 *
 * 'procesando' NO ES UN ESTADO DE ERROR. La regulación no aparece en ninguna lista de
 * fallos. Nadie recibe un aviso. El usuario recarga la página, ve "procesando", y supone que
 * el sistema sigue trabajando. Mañana también. Y pasado.
 *
 * Nadie va a arreglarlo, porque nadie sabe que está roto.
 *
 * Es el mismo patrón que ya encontramos tres veces en este proyecto: el sistema falla en
 * silencio y deja al usuario esperando algo que no va a pasar nunca.
 *
 * ── Qué hace este comando ────────────────────────────────────────────
 *
 * Busca las regulaciones que llevan más de N minutos en 'procesando' y las pasa a 'error',
 * con un mensaje que explica qué ocurrió y qué hacer.
 *
 * No las reconvierte. Es deliberado: si el archivo tumbó el proceso una vez, volver a
 * intentarlo automáticamente lo tumbaría otra vez, y el comando entraría en un bucle. Lo que
 * hace falta es que una persona vea el error y decida (dividir el PDF, guardarlo como .docx,
 * capturar el articulado a mano).
 *
 * ── Cómo se usa ──────────────────────────────────────────────────────
 *
 *     php artisan regulaciones:rescatar-colgadas
 *     php artisan regulaciones:rescatar-colgadas --minutos=30
 *     php artisan regulaciones:rescatar-colgadas --dry-run
 *
 * Y programado, en routes/console.php o en el Kernel:
 *
 *     Schedule::command('regulaciones:rescatar-colgadas')->everyTenMinutes();
 *
 * ── Y si algún día se usan colas de verdad ───────────────────────────
 *
 * Este comando sigue haciendo falta. Un worker que muere por OOM tampoco ejecuta su
 * failed(): el job se queda a medias exactamente igual. El barredor es independiente de que
 * la conversión sea síncrona o asíncrona.
 */
class RescatarRegulacionesColgadas extends Command
{
    protected $signature = 'regulaciones:rescatar-colgadas
                            {--minutos=15 : Cuántos minutos en "procesando" se consideran colgados}
                            {--dry-run    : Solo enseña qué haría, sin tocar la base}';

    protected $description = 'Marca como error las regulaciones que llevan demasiado tiempo en "procesando".';

    public function handle(): int
    {
        $minutos = max(1, (int) $this->option('minutos'));
        $simular = (bool) $this->option('dry-run');

        // El umbral por defecto son 15 minutos, y no 2. La conversión legítima de un PDF
        // grande con LibreOffice puede tardar varios minutos: un umbral agresivo mataría
        // conversiones que iban a terminar bien. Es mejor que un usuario espere quince
        // minutos de más a que el sistema aborte un trabajo que estaba a punto de acabar.
        $limite = now()->subMinutes($minutos);

        $colgadas = Regulacion::where('conversion_estatus', Regulacion::CONVERSION_PROCESANDO)
            ->where('updated_at', '<', $limite)
            ->get();

        if ($colgadas->isEmpty()) {
            $this->info("Ninguna regulación lleva más de {$minutos} minutos en 'procesando'.");

            return self::SUCCESS;
        }

        $this->warn("{$colgadas->count()} regulación(es) colgada(s) más de {$minutos} minutos:");

        foreach ($colgadas as $regulacion) {
            $desde = $regulacion->updated_at->diffForHumans();

            $this->line("  #{$regulacion->id} — {$regulacion->nombre} (procesando desde {$desde})");

            if ($simular) {
                continue;
            }

            $regulacion->update([
                'conversion_estatus' => Regulacion::CONVERSION_ERROR,
                'conversion_error'   => 'La conversión se interrumpió y no llegó a terminar. '
                    . 'Suele pasar con archivos muy grandes o con PDF escaneados, que agotan la '
                    . 'memoria o el tiempo del servidor. '
                    . 'Sugerencia: abra el archivo en Word y guárdelo como .docx, o divida el '
                    . 'documento en partes, y vuelva a subirlo.',
            ]);

            // El log es la mitad del arreglo, no un adorno.
            //
            // Sin él, este comando pasaría de "procesando" a "error" en silencio y nadie
            // sabría CUÁNTAS regulaciones se están colgando ni con qué frecuencia. Si mañana
            // se cuelgan diez al día, eso no es un problema de esas diez regulaciones: es un
            // problema del servidor (memoria, timeouts), y el log es lo único que lo delata.
            Log::warning('Regulación rescatada de un estado colgado.', [
                'regulacion_id' => $regulacion->id,
                'nombre'        => $regulacion->nombre,
                'colgada_desde' => $regulacion->updated_at->toIso8601String(),
                'minutos'       => $minutos,
            ]);
        }

        if ($simular) {
            $this->comment('(--dry-run: no se modificó nada.)');

            return self::SUCCESS;
        }

        $this->info("Listo. {$colgadas->count()} regulación(es) marcada(s) como error.");

        return self::SUCCESS;
    }
}
