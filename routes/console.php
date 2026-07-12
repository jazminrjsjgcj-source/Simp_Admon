<?php

use App\Models\RegulacionNodo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Limpieza de la papelera del articulado: borra DEFINITIVAMENTE los nodos que
 * llevan más de 7 días en la papelera (soft-deleted). Es idempotente y seguro de
 * correr a diario. forceDelete dispara la cascada física de la FK, así que los
 * descendientes en papelera también se eliminan.
 */
Artisan::command('regulaciones:limpiar-papelera', function () {
    $limite = now()->subDays(7);

    // forceDelete dispara la cascada física de la FK (parent_id), que ya elimina
    // los descendientes. Por eso se borran primero los "topes" (sin padre en
    // papelera) y se recuenta lo que realmente quede, para no inflar el total.
    $antes = RegulacionNodo::onlyTrashed()->where('deleted_at', '<', $limite)->count();

    RegulacionNodo::onlyTrashed()
        ->where('deleted_at', '<', $limite)
        ->get()
        ->each(function ($nodo) {
            // Si la cascada de un tope ya lo borró, withTrashed()->find lo confirma.
            if (RegulacionNodo::withTrashed()->whereKey($nodo->id)->exists()) {
                $nodo->forceDelete();
            }
        });

    $this->info("Papelera del articulado: {$antes} elemento(s) eliminado(s) definitivamente.");
})->purpose('Borra definitivamente los nodos con más de 7 días en la papelera');

// Correr la limpieza una vez al día.
Schedule::command('regulaciones:limpiar-papelera')->daily();

/*
 * Rescate de las conversiones colgadas.
 *
 * Cuando alguien sube un PDF, el worker lo convierte a texto. Si ese proceso muere de
 * golpe —el kernel mata a LibreOffice por memoria, el contenedor se reinicia, el worker
 * se cae—, failed() no llega a ejecutarse y la regulación se queda en 'procesando'.
 *
 * Y 'procesando' NO ES UN ESTADO DE ERROR: la regulación no sale en ninguna lista de
 * fallos, nadie recibe un aviso, y el usuario recarga la página y supone que el sistema
 * sigue trabajando. Mañana también. Nadie va a arreglarlo porque nadie sabe que está roto.
 *
 * Este comando es lo único que rompe ese silencio. Cada diez minutos, y no cada día, porque
 * un usuario esperando su regulación no puede esperar veinticuatro horas a que el sistema
 * le diga que algo falló.
 *
 * ── OJO: ESTO SOLO CORRE SI HAY UN SCHEDULER VIVO ──
 *
 * Schedule::command() no ejecuta nada por sí solo: necesita un proceso que llame a
 * `php artisan schedule:run` cada minuto. En docker-compose hay un servicio `scheduler`
 * para eso.
 *
 * Sin él, esta línea es decorativa — y la de arriba también lo ha sido desde que se
 * escribió: los nodos de la papelera con más de 7 días nunca se han borrado.
 */
Schedule::command('regulaciones:rescatar-colgadas')->everyTenMinutes();
