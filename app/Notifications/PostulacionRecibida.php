<?php

namespace App\Notifications;

use App\Models\Postulacion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de confirmación para quien envía una postulación abierta.
 * Se manda a la dirección capturada (la persona aún no tiene cuenta), por eso
 * no incluye contraseña ni botón de acceso: esos llegan en el correo de
 * bienvenida (BienvenidaIntegrante) si la postulación avanza.
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
        $fecha = $this->postulacion->fecha_postulacion?->format('d/m/Y');

        $mail = (new MailMessage)
            ->subject('Confirmación de postulación - Consejo de Participación Ciudadana')
            ->greeting('Estimado(a) ' . $this->postulacion->nombre . ':')
            ->line('Le confirmamos que su postulación'
                . ($consejo ? ' al Consejo de Participación Ciudadana de ' . $consejo : ' al Consejo de Participación Ciudadana')
                . ' se registró correctamente.')
            ->line('**Datos de su postulación:**')
            ->line('Correo de contacto: ' . $this->postulacion->correo);

        if ($fecha) {
            $mail->line('Fecha de postulación: ' . $fecha);
        }

        return $mail
            ->line('**Pasos del proceso:**')
            ->line('1. El Consejo de Participación Ciudadana revisará su postulación.')
            ->line('2. Le escribiremos a este mismo correo con el resultado.')
            ->line('3. Si su postulación avanza, recibirá en otro correo sus datos de acceso a la plataforma.')
            ->line('4. Al ingresar, cambiará su contraseña temporal, aceptará el aviso de privacidad y capturará sus datos personales.')
            ->line('5. Finalmente, subirá los documentos solicitados para su revisión.')
            ->line('Mientras tanto, no es necesario que realice ningún otro trámite. Si usted no realizó esta postulación, ignore este correo.')
            ->salutation('Atentamente: Los Consejos de Participación Ciudadana');
    }
}
