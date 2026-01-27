<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $organizationName
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $organization = trim($this->organizationName) !== ''
            ? $this->organizationName
            : 'jouw organisatie';

        $greeting = $name !== '' ? "Hallo {$name}!" : 'Hallo!';
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Wachtwoord aanmaken voor jouw Zookr-account')
            ->greeting($greeting)
            ->line("Je ontvangt deze e-mail omdat er voor jou een gebruikersaccount is aangemaakt binnen de omgeving van {$organization}.")
            ->line('Klik op onderstaande knop om jouw (gratis) gebruikersaccount te activeren.')
            ->action('Wachtwoord aanmaken', $url)
            ->salutation("Met vriendelijke groet,\nZookr");
    }
}
