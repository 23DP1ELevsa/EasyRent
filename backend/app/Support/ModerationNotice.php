<?php

namespace App\Support;

use App\Models\Persona;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ModerationNotice extends Notification
{
    public function __construct(
        private readonly string $event,
        private readonly string $reason,
        private readonly ?string $endsAt,
        private readonly bool $ongoing,
        private readonly ?string $reasonCode,
    ) {}

    public static function afterCommit(Persona $recipient, string $event, string $reason, ?string $endsAt = null, bool $ongoing = false, ?string $reasonCode = null): void
    {
        if ($recipient->trashed() || ! $recipient->hasVerifiedEmail() || ! $recipient->epasts) {
            return;
        }

        $locale = in_array($recipient->preferred_locale, ['lv', 'ru', 'en'], true)
            ? $recipient->preferred_locale : 'lv';
        DB::afterCommit(function () use ($recipient, $event, $reason, $endsAt, $ongoing, $locale, $reasonCode) {
            try {
                if (! AccountMail::available()) {
                    AccountMail::logDelivery('moderation_'.$event, $recipient->epasts, null, error: 'mailer_unavailable');

                    return;
                }
                app(AccountMail::class)->send('moderation_'.$event, $recipient, new self($event, $reason, $endsAt, $ongoing, $reasonCode), $locale, null);
            } catch (\Throwable) {
                // The decision and its audit record are already committed.
                AccountMail::logDelivery('moderation_'.$event, $recipient->epasts, null, error: 'send_failed');
            }
        });
    }

    public function toMail(Persona $recipient): MailMessage
    {
        $subject = __('admin.mail.'.$this->event);
        $ends = match (true) {
            $this->endsAt !== null => $this->endsAt,
            $this->ongoing => __('admin.indefinite'),
            default => __('admin.not_applicable'),
        };
        $reason = $this->reasonCode ? __('admin.reasons.'.$this->reasonCode).' — '.$this->reason : $this->reason;
        $supportEmail = config('app.support_email');
        $support = $supportEmail ? __('admin.support_email', ['email' => $supportEmail]) : __('admin.support_form');

        return (new MailMessage)->subject($subject)->view(
            ['html' => 'emails.moderation', 'text' => 'emails.moderation-text'],
            [
                'locale' => app()->getLocale(),
                'subject' => $subject,
                'reason' => __('admin.mail.reason', ['reason' => $reason]),
                'term' => __('admin.mail.term', ['term' => $ends]),
                'support' => $support,
                'supportUrl' => rtrim((string) config('app.frontend_url'), '/').'/support',
            ]
        );
    }
}
