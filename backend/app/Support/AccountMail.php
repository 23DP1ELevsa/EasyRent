<?php

namespace App\Support;

use App\Models\Persona;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Throwable;

class AccountMail
{
    use Localizable;

    public static function available(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return ($transport === 'array' && app()->environment('local', 'testing'))
            || ($transport === 'resend' && filled(config('services.resend.key')));
    }

    public function send(string $type, Persona $persona, #[\SensitiveParameter] Notification $notification, string $locale, ?string $requestId): bool
    {
        $address = $persona->routeNotificationForMail();
        try {
            // Transport exceptions can include the full message and credentials.
            $sent = $this->withLocale($locale, fn () => app(MailChannel::class)->send($persona, $notification));
        } catch (Throwable) {
            self::logDelivery($type, $address, $requestId, error: 'send_failed');

            return false;
        }

        if (! $sent) {
            self::logDelivery($type, $address, $requestId, error: 'not_accepted');

            return false;
        }

        $id = $sent->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString();
        self::logDelivery($type, $address, $requestId, $id);

        return true;
    }

    public static function logDelivery(string $type, ?string $address, ?string $requestId, ?string $resendId = null, ?string $error = null): void
    {
        $separator = strrpos($address ?? '', '@');
        $domain = $separator === false ? '' : mb_strtolower(substr($address, $separator + 1));
        Log::log($error === null ? 'info' : 'warning', 'Account email send result.', [
            'mail_type' => $type,
            'recipient_domain' => preg_match('/\A[\p{L}\p{N}.-]+\z/u', $domain) ? $domain : null,
            'resend_id' => $resendId !== null && Str::isUuid($resendId) ? $resendId : null,
            'outcome' => $error === null ? 'accepted' : 'error',
            'error' => $error,
            'request_id' => $requestId,
        ]);
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
