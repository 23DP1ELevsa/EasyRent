<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use RuntimeException;

class AccountMail
{
    public static function available(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return ($transport === 'array' && app()->environment('local', 'testing'))
            || ($transport === 'resend' && filled(config('services.resend.key')));
    }

    public static function message(string $translation, ?string $name, string $path, #[\SensitiveParameter] array $fragment, array $expiry): MailMessage
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
        $actionUrl = $frontend.$path.'#'.http_build_query($fragment + ['lang' => $locale], '', '&', PHP_QUERY_RFC3986);
        $subject = __($translation.'.subject');

        return (new MailMessage)
            ->subject($subject)
            ->view(['html' => 'emails.account-action', 'text' => 'emails.account-action-text'], [
                'locale' => $locale,
                'subject' => $subject,
                'greeting' => filled($name) ? __($translation.'.greeting', ['name' => $name]) : __($translation.'.greetingGeneric'),
                'intro' => __($translation.'.intro'),
                'action' => __($translation.'.action'),
                'actionUrl' => $actionUrl,
                'expires' => __($translation.'.expires', $expiry),
                'ignore' => __($translation.'.ignore'),
            ]);
    }
}
