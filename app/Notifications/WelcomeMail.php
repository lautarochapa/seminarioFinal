<?php

namespace App\Notifications;

use App\Services\TransactionalMailService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeMail extends Notification
{
    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)->subject('Te damos la bienvenida a CocinaComidaControl')
            ->view(['mail.transactional', 'mail.transactional-text'], [
                'title' => "Tu cuenta ya est\u{00e1} creada",
                'paragraphs' => [
                    'Te damos la bienvenida a CocinaComidaControl.',
                    "Ya pod\u{00e9}s organizar tu cocina, planificar comidas y compartir tu grupo familiar.",
                    "Si no creaste esta cuenta, no respondas con contrase\u{00f1}as ni c\u{00f3}digos.",
                ],
                'actionLabel' => 'Ingresar a mi cuenta',
                'actionUrl' => TransactionalMailService::url('/login'),
            ]);
    }
}
