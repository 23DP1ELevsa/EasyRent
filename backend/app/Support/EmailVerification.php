<?php

namespace App\Support;

use App\Models\Persona;
use App\Notifications\VerifyPersonaEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Throwable;

class EmailVerification
{
    use Localizable;

    public const VALID_HOURS = 24;

    public const COOLDOWN = 60;

    private const PENDING_SESSION = 'email_verification.pending';

    public function requestFor(Request $request, Persona $persona): array
    {
        $this->rememberPending($request, $persona);
        $status = $this->send($persona->epasts, app()->getLocale(), $persona->getKey());

        return [
            'code' => 'verification_required',
            'message' => __($status === 'unavailable' ? 'email-verification.mail_unavailable' : 'email-verification.registered'),
            'email' => $persona->epasts,
            'mail_status' => $status,
            'retry_after' => self::COOLDOWN,
        ];
    }

    public function rememberPending(Request $request, Persona $persona): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::PENDING_SESSION, [
                'id' => $persona->getKey(),
                'email' => mb_strtolower($persona->epasts),
                'expires_at' => now()->addMinutes(30)->timestamp,
            ]);
        }
    }

    public function pendingId(Request $request, string $email): ?int
    {
        $pending = $request->hasSession() ? $request->session()->get(self::PENDING_SESSION) : null;

        return $pending && $pending['expires_at'] > now()->timestamp && $pending['email'] === $email
            ? $pending['id'] : null;
    }

    public function send(string $email, string $locale, ?int $personaId = null): string
    {
        $delivery = null;
        try {
            if (! AccountMail::available()) {
                Log::warning('Email verification requires a configured Resend mailer.');

                return 'unavailable';
            }

            $prepared = DB::transaction(function () use ($email, $personaId) {
                $query = Persona::forEmail($email)->whereNull('email_verified_at');
                if ($personaId !== null) {
                    $query->whereKey($personaId);
                }
                $persona = $query->lockForUpdate()->first();
                if (! $persona) {
                    return 'not_eligible';
                }

                $address = $persona->getEmailForVerification();
                $key = 'email-verification:'.hash_hmac('sha256', mb_strtolower($address), config('app.key'));
                if (! Cache::add($key, true, self::COOLDOWN)) {
                    return 'rate_limited';
                }

                $token = bin2hex(random_bytes(32));
                $hash = $this->digest($persona, $token);
                $tokens = DB::table('email_verification_tokens');
                $previous = $tokens->where('persona_id', $persona->getKey())->first();
                DB::table('email_verification_tokens')->updateOrInsert(['persona_id' => $persona->getKey()], [
                    'email' => $address,
                    'token_hash' => $hash,
                    'expires_at' => now()->addHours(self::VALID_HOURS),
                ]);

                return [$persona, $token, $hash, $previous];
            }, 3);

            if (is_string($prepared)) {
                return $prepared;
            }
            [$persona, $token, $hash, $previous] = $prepared;
            $delivery = [$persona->getKey(), $hash, $previous];
            $sent = $this->withLocale($locale, fn () => app(MailChannel::class)->send($persona, new VerifyPersonaEmail($token)));
            if (! $sent) {
                throw new RuntimeException('The mailer did not accept the email verification message.');
            }

            $resendId = $sent->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString();
            if (is_string($resendId) && preg_match('/\A[\da-f-]{36}\z/i', $resendId)) {
                Log::info('Email verification accepted by Resend.', ['resend_id' => $resendId]);
            }

            return 'sent';
        } catch (Throwable) {
            // A transport exception may include the whole message, including the secret link.
            Log::warning('Email verification message could not be sent.');
            if ($delivery !== null) {
                $this->restorePreviousLink(...$delivery);
            }

            return 'unavailable';
        }
    }

    private function restorePreviousLink(int $id, string $hash, ?object $previous): void
    {
        try {
            DB::transaction(function () use ($id, $hash, $previous) {
                if (! Persona::whereKey($id)->lockForUpdate()->first()) {
                    return;
                }

                $attempt = DB::table('email_verification_tokens')->where('persona_id', $id)->where('token_hash', $hash);
                if ($previous) {
                    $attempt->update((array) $previous);
                } else {
                    $attempt->delete();
                }
            }, 3);
        } catch (Throwable) {
            Log::warning('Email verification token could not be restored after a delivery failure.');
        }
    }

    public function confirm(int $id, #[\SensitiveParameter] string $token): string
    {
        return DB::transaction(function () use ($id, $token) {
            $persona = Persona::whereKey($id)->lockForUpdate()->first();
            if (! $persona || $persona->hasVerifiedEmail()) {
                return 'invalid_verification_link';
            }

            $pending = DB::table('email_verification_tokens')->where('persona_id', $id)->first();
            if (! $pending || $pending->email !== $persona->getEmailForVerification()
                || ! hash_equals($pending->token_hash, $this->digest($persona, $token))) {
                return 'invalid_verification_link';
            }

            if (Carbon::parse($pending->expires_at)->lessThanOrEqualTo(now())) {
                return 'verification_link_expired';
            }

            $this->markVerified($persona);

            return 'email_verified';
        }, 3);
    }

    /** Call within the transaction holding the account lock. */
    public function markVerified(Persona $persona): void
    {
        if ($persona->hasVerifiedEmail()) {
            return;
        }
        if (! $persona->markEmailAsVerified()) {
            throw new RuntimeException('The email address could not be verified.');
        }

        $this->forget($persona);
        $persona->tokens()->delete();
        $persona->purgeDatabaseSessions();
        DB::afterCommit(fn () => event(new Verified($persona)));
    }

    public function forget(Persona $persona): void
    {
        DB::table('email_verification_tokens')->where('persona_id', $persona->getKey())->delete();
    }

    public function clearWebSessionFor(Request $request, int $id): void
    {
        $guard = Auth::guard('web');
        if ($request->hasSession() && (int) $guard->id() === $id) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function digest(Persona $persona, #[\SensitiveParameter] string $token): string
    {
        return hash_hmac('sha256', $persona->getKey().':'.$persona->getEmailForVerification().':'.$token, config('app.key'));
    }
}
