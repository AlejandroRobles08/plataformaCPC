<?php

namespace App\Notifications;

use App\Models\Postulacion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de confirmación para quien envía una postulación abierta.
 * Se manda a la dirección capturada (la persona aún no tiene cuenta).
 */
class PostulacionRecibida extends Notification
{
    public function __construct(private Postulacion $postulacion)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $consejo = $this->postulacion->consejoSolicitado?->nombre;

        return (new MailMessage)
            ->subject('Hemos recibido su postulación')
            ->greeting('Estimado(a) ' . $this->postulacion->nombre . ':')
            ->line('Le confirmamos que recibimos su postulación'
                . ($consejo ? ' para el ' . $consejo : '') . '.')
            ->line('El equipo de los Consejos de Participación Ciudadana la revisará y le escribirá a este correo con el resultado.')
            ->line('Si su postulación avanza, recibirá un acceso a la plataforma para subir sus documentos.')
            ->salutation('Gracias por su interés en participar.');
    }
}
