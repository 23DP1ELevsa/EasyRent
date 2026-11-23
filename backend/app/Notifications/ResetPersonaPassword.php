<?php

namespace App\Notifications;

use App\Models\Persona;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class ResetPersonaPassword extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    public function via(Persona $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Persona $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = parse_url($frontend);
        $schemes = app()->environment('local', 'testing') ? ['https', 'http'] : ['https'];
        if (! is_array($url) || ! filter_var($frontend, FILTER_VALIDATE_URL)
            || ! in_array($url['scheme'] ?? null, $schemes, true)
            || array_intersect(['path', 'user', 'pass', 'query', 'fragment'], array_keys($url))) {
            throw new RuntimeException('FRONTEND_URL must be the public frontend origin.');
        }

        $locale = app()->getLocale();
        // URL fragments stay in the browser and are omitted from HTTP requests.
        $resetUrl = $frontend.'/reset-password#'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'lang' => $locale,
        ], '', '&', PHP_QUERY_RFC3986);

        return (new MailMessage)
            ->subject(__('password-reset.mail.subject'))
            ->view(['html' => 'emails.password-reset', 'text' => 'emails.password-reset-text'], [
                'name' => $notifiable->vards,
                'resetUrl' => $resetUrl,
                'locale' => $locale,
                'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
