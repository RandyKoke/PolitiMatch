<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $rawToken) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Lien vers la SPA (service unique, cf. cahier des charges) : c'est
        // Vue Router qui affichera le formulaire de réinitialisation.
        $url = rtrim(config('app.url'), '/').'/reset-password?token='.$this->rawToken.'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe PolitiMatch')
            ->line('Vous recevez cet email car une réinitialisation de mot de passe a été demandée pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line('Ce lien expire dans 60 minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise.");
    }
}
