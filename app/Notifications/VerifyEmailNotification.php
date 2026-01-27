<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $greeting = $name !== '' ? "Hallo {$name}!" : 'Hallo!';
        $url = $this->verificationUrl($notifiable);
        return (new MailMessage)
            ->subject('Bevestig je e-mailadres voor Zookr')
            ->greeting($greeting)
            ->line('Je hebt een gebruikersaccount aangemaakt. Bevestig je e-mailadres om je account te activeren.')
            ->action('E-mailadres bevestigen', $url)
            ->line('Deze verificatielink is 24 uur geldig.')
            ->line('Heb je geen account aangemaakt? Dan hoef je verder niets te doen.')
            ->salutation("Met vriendelijke groet,\nZookr");
    }
}
