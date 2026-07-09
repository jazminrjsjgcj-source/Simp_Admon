<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\RegulacionNodo;

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
