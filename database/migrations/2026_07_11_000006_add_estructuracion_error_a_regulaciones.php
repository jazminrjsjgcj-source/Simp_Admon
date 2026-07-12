<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda por qué falló la construcción del articulado de una regulación.
 *
 * ── El silencio que esto rompe ───────────────────────────────────────
 *
 * Desde que la estructuración se hace en segundo plano, un fallo se comporta así:
 *
 *   1. El usuario da a "Estructurar articulado".
 *   2. Ve el mensaje "la página se actualizará sola cuando termine".
 *   3. El job falla.
 *   4. failed() escribe una línea en el log.
 *   5. ...y ya.
 *
 * La conversión SÍ terminó bien, así que `conversion_estatus` dice 'listo'. La regulación se
 * ve normal. El botón "Estructurar articulado" sigue ahí, invitando a darle otra vez. Nada
 * en toda la pantalla indica que algo salió mal.
 *
 * El usuario recarga. Y recarga. Y supone que el sistema sigue trabajando.
 *
 * Es exactamente el mismo patrón que ya hemos encontrado seis veces en este proyecto: el
 * sistema falla en silencio y deja a alguien esperando algo que no va a pasar nunca.
 *
 * ── Por qué una columna y no una notificación ────────────────────────
 *
 * Se barajó avisar al jurídico con una AvisoPunta. Suena más responsable, y no lo es: el
 * usuario que está MIRANDO LA PANTALLA seguiría sin ver nada. El error tiene que aparecer
 * donde la persona lo está esperando.
 *
 * Y hay simetría: `conversion_error` ya existe, ya se pinta en la ficha, y todo el mundo
 * entiende cómo funciona. Esta columna no introduce un concepto nuevo — añade una instancia
 * de uno que ya está entendido.
 *
 * ── Por qué NO un `estructuracion_estatus` completo ──────────────────
 *
 * Sería el modelo elegante: pendiente / procesando / listo / error, igual que la conversión.
 *
 * Y sería desproporcionado. La estructuración SIEMPRE viene encadenada detrás de la
 * conversión: no existe un "estructurando…" independiente que el usuario pueda observar por
 * separado. Mientras la conversión esté en marcha, la pantalla ya dice "convirtiendo…". Y
 * cuando termina, la regulación o tiene articulado, o tiene un error que explica por qué no.
 *
 * Duplicar una máquina de estados entera para eso sería resolver un problema que nadie tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->text('estructuracion_error')
                ->nullable()
                ->after('conversion_error')
                ->comment('Por qué falló la construcción del articulado. Null si fue bien o no se ha intentado.');
        });
    }

    public function down(): void
    {
        Schema::table('regulaciones', function (Blueprint $table) {
            $table->dropColumn('estructuracion_error');
        });
    }
};
