<?php

namespace App\Notifications;

use App\Models\Postulacion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo para la persona cuya postulación no fue aprobada, ya sea en el primer
 * filtro (noAprobar) o en la revisión final (rechazar).
 *
 * Incluye el motivo que captura el administrador (comentarios_revision) cuando existe.
 * El tono es de agradecimiento e invita a participar en futuras convocatorias.
 */
class PostulacionNoAprobada extends Notification
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
        $motivo = trim((string) $this->postulacion->comentarios_revision);

        $mail = (new MailMessage)
            ->subject('Resultado de su postulación - Consejo de Participación Ciudadana')
            ->greeting('Estimado(a) ' . $this->postulacion->nombre . ':')
            ->line('Agradecemos sinceramente el tiempo y el interés que dedicó a postularse'
                . ($consejo ? ' al Consejo de Participación Ciudadana de ' . $consejo : ' al Consejo de Participación Ciudadana')
                . '.')
            ->line('Después de revisar cuidadosamente todas las postulaciones recibidas, le informamos que en esta ocasión su postulación **no fue aprobada**.');

        if ($motivo !== '') {
            $mail->line('**Motivo:**')
                ->line($motivo);
        }

        return $mail
            ->line('Este resultado no demerita su trayectoria ni su compromiso con la comunidad. Los lugares en cada consejo son limitados y la selección responde a los perfiles requeridos en esta convocatoria.')
            ->line('**Le invitamos a seguir participando.** Las convocatorias para integrar los Consejos de Participación Ciudadana se abren de manera periódica, y nos dará mucho gusto recibir nuevamente su postulación en una próxima ocasión.')
            ->line('La participación ciudadana se construye con personas como usted. Gracias por querer sumar a su comunidad.')
            ->salutation('Atentamente: Los Consejos de Participación Ciudadana');
    }
}
