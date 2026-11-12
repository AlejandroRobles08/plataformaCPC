<?php

namespace App\Notifications;

use App\Models\Integrante;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de bienvenida para el integrante que el admin da de alta en la plataforma
 * (ya completó su alta física en IMPLAN). Incluye su contraseña temporal.
 *
 * Se envía de forma síncrona a propósito: si se encolara, la contraseña temporal
 * quedaría en texto plano en la tabla de jobs.
 */
class BienvenidaIntegrante extends Notification
{
    public function __construct(
        private Integrante $integrante,
        private string $passwordTemporal,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $consejo = $this->integrante->consejo?->nombre;

        return (new MailMessage)
            ->subject('Bienvenido(a) a la plataforma del Consejo de Participación Ciudadana')
            ->greeting('Estimado(a) ' . $this->integrante->nombre . ':')
            ->line('Le damos la bienvenida como integrante' 
            . ($consejo ? ' del consejo de Participación Ciudadana de ' . $consejo : '')
                . '. Este es el sistema administrativo del IMPLAN, usted ya forma parte de los consejos
                , y se le está haciendo migración a un ecosistema digital.')
            ->line('**Sus datos de acceso:**')
            ->line('Usuario: ' . $notifiable->email)
            ->line('Contraseña temporal: **' . $this->passwordTemporal . '**')
            ->line('**Pasos para completar su alta en la plataforma:**')
            ->line('1. Ingrese con los datos anteriores.')
            ->line('2. Cambie su contraseña temporal por una propia.')
            ->line('3. Lea y acepte el aviso de privacidad.')
            ->line('4. En el Archivo digital, use "Darme de alta" para capturar sus datos personales.')
            ->line('5. Suba los documentos solicitados. Cuando sean revisados y aprobados, tendrá acceso al resto de la plataforma.')
            ->action('Ingresar a la plataforma', route('login'))
            ->line('Por su seguridad, no comparta esta contraseña. Si usted no reconoce este registro, ignore este correo.')
            ->salutation('Atentamente: Los Consejos de Participación Ciudadana');
    }
}
