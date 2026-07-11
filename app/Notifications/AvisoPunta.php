<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso genérico de PUNTA.
 *
 * Una sola clase para todos los tipos de notificación: se le pasan el ícono,
 * el título, el mensaje y el enlace, y ella arma tanto la versión de la
 * campanita (canal 'database') como la del correo (canal 'mail').
 *
 * Uso:
 *   $user->notify(new AvisoPunta(
 *       icono:   'ti-eye',
 *       titulo:  'Nueva observación',
 *       mensaje: 'Hay una observación en "Permiso de construcción menor".',
 *       url:     route('tramites.show', $tramite->id),
 *   ));
 */
class AvisoPunta extends Notification
{
    use Queueable;

    public function __construct(
        public string $icono,
        public string $titulo,
        public string $mensaje,
        public ?string $url = null,
    ) {}

    /**
     * Canales por los que se entrega: campanita (database) y correo (mail).
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Versión que se guarda para la campanita en-app.
     * Estos datos quedan en la columna `data` (JSON) de la tabla notifications
     * y son los que lee el panel desplegable.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icono'   => $this->icono,
            'titulo'  => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'     => $this->url,
        ];
    }

    /**
     * Versión que se envía por correo electrónico.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject('PUNTA — ' . $this->titulo)
            ->greeting('Hola ' . ($notifiable->name ?? '') . ',')
            ->line($this->mensaje);

        if ($this->url) {
            $correo->action('Ver en PUNTA', $this->url);
        }

        return $correo->line('Este es un aviso automático del sistema PUNTA.');
    }
}
