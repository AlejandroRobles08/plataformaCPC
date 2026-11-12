<?php

namespace App\Notifications;

use App\Models\Postulacion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo para la persona cuya postulación pasó el primer filtro.
 * Incluye su acceso temporal y la lista de documentos que debe subir.
 *
 * Se envía de forma síncrona a propósito: si se encolara, la contraseña temporal
 * quedaría en texto plano en la tabla de jobs.
 */
class PostulacionPreAprobada extends Notification
{
    public function __construct(
        private Postulacion $postulacion,
        private string $passwordTemporal,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $consejo = $this->postulacion->consejoSolicitado?->nombre;

        $mail = (new MailMessage)
            ->subject('Su postulación avanzó a la siguiente etapa - Consejo de Participación Ciudadana')
            ->greeting('Estimado(a) ' . $this->postulacion->nombre . ':')
            ->line('Nos da gusto informarle que su postulación'
                . ($consejo ? ' al Consejo de Participación Ciudadana de ' . $consejo : ' al Consejo de Participación Ciudadana')
                . ' fue revisada y avanzó a la siguiente etapa.')
            ->line('Para continuar, le pedimos ingresar a la plataforma y subir su documentación.')
            ->line('**Sus datos de acceso:**')
            ->line('Usuario: ' . $this->postulacion->correo)
            ->line('Contraseña temporal: **' . $this->passwordTemporal . '**')
            ->line('**Pasos para continuar:**')
            ->line('1. Ingrese con los datos anteriores.')
            ->line('2. Cambie su contraseña temporal por una propia.')
            ->line('3. Lea y acepte nuevamente el aviso de privacidad. Este paso es necesario porque en la plataforma se tratarán sus datos personales y documentos de identificación.')
            ->line('4. En la sección de Postulaciones, use "Cargar documentos" y suba los siguientes archivos en formato PDF (máximo 2 MB cada uno):');

        foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
            $mail->line('- ' . (Postulacion::NOMBRES_DOCUMENTOS[$tipo] ?? $tipo));
        }

        return $mail
            ->line('Una vez que cargue sus documentos, el Consejo de Participación Ciudadana realizará la revisión final y le informará el resultado a este correo.')
            ->action('Ingresar a la plataforma', route('login'))
            ->line('Por su seguridad, no comparta esta contraseña. Si usted no reconoce esta postulación, ignore este correo.')
            ->salutation('Atentamente: Los Consejos de Participación Ciudadana');
    }
}
