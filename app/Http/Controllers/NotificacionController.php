<?php

namespace App\Http\Controllers;

use App\Notifications\AvisoPunta;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * Marca todas las notificaciones no leídas del usuario como leídas.
     * Lo usa el botón "Marcar todas leídas" del panel.
     */
    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * Marca una notificación como leída y redirige a su enlace (si tiene).
     * Lo usa el clic en una notificación del panel.
     */
    public function abrir(Request $request, string $id)
    {
        $noti = $request->user()->notifications()->where('id', $id)->first();

        if (!$noti) {
            return back();
        }

        $noti->markAsRead();

        $url = $noti->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    /**
     * Envía una notificación de prueba al usuario actual.
     * Sirve para verificar que la campanita y el correo funcionan.
     * (Entrega 1 — luego estas se dispararán solas desde el flujo.)
     */
    public function enviarPrueba(Request $request)
    {
        $request->user()->notify(new AvisoPunta(
            icono:   'ti-bell',
            titulo:  'Notificación de prueba',
            mensaje: 'Si ves esto en la campanita, las notificaciones funcionan correctamente.',
            url:     route('dashboard'),
        ));

        return back()->with('success', 'Notificación de prueba enviada. Revisa la campanita.');
    }
}
