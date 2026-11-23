<?php

namespace App\Notifications;

use App\Models\Persona;
use App\Support\AccountMail;
use App\Support\EmailVerification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyPersonaEmail extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    public function via(Persona $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Persona $notifiable): MailMessage
    {
        return AccountMail::message('email-verification.mail', $notifiable->vards, '/verify-email', [
            'id' => $notifiable->getKey(),
            'token' => $this->token,
        ], ['hours' => EmailVerification::VALID_HOURS]);
    }
}
