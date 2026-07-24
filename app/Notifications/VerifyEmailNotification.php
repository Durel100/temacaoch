<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Vérifie ton adresse email — TemaCoach')
            ->greeting('Bonjour !')
            ->line('Clique sur le bouton ci-dessous pour vérifier ton adresse email.')
            ->action('Vérifier mon email', $url)
            ->line('Ce lien expire dans 60 minutes.')
            ->line('Si tu n\'as pas créé de compte TemaCoach, ignore cet email.')
            ->salutation('L\'équipe TemaCoach');
    }
}