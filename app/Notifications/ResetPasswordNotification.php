<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');
        $hours = (int) ceil($minutes / 60);

        $name = trim((string) ($notifiable->name ?? ''));
        $greeting = $name !== '' ? ('Hallo ' . $name . '!') : 'Hallo!';

        return (new MailMessage)
            ->subject('Wachtwoord-reset voor jouw Zookr-account')
            ->greeting($greeting)
            ->line('Je ontvangt deze e-mail omdat we een verzoek hebben ontvangen om het wachtwoord van je account te resetten.')
            ->action('Wachtwoord resetten', $url)
            ->line('Deze link om je wachtwoord te resetten is ' . $hours . ' uur geldig.')
            ->line('Heb je geen wachtwoordreset aangevraagd? Dan hoef je verder niets te doen.')
            ->salutation("Met vriendelijke groet,\nZookr");
    }
}
