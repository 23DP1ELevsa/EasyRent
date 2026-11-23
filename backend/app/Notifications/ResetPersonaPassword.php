<?php

namespace App\Notifications;

use App\Models\Persona;
use App\Support\AccountMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPersonaPassword extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    public function via(Persona $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Persona $notifiable): MailMessage
    {
        return AccountMail::message('password-reset.mail', $notifiable->vards, '/reset-password', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]);
    }
}
