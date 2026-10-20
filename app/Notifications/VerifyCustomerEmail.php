<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyCustomerEmail extends Notification
{
    use Queueable;

    public function __construct(
        protected string $redirect = '/mi-cuenta'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'redirect' => $this->redirect,
            ]
        );

        return (new MailMessage)
            ->subject('Verificá tu email - ' . config('app.name'))
            ->greeting('¡Hola ' . $notifiable->nombre . '!')
            ->line('Gracias por registrarte en ' . config('app.name') . '.')
            ->line('Para continuar, verificá tu dirección de email haciendo clic en el siguiente botón.')
            ->action('Verificar mi email', $verificationUrl)
            ->line('Este enlace es válido durante 60 minutos.')
            ->line('Si no creaste esta cuenta, podés ignorar este email.');
    }
}