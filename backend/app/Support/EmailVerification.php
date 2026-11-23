<?php

namespace App\Support;

use App\Models\Persona;
use App\Notifications\VerifyPersonaEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EmailVerification
{
    public const VALID_HOURS = 24;

    public const COOLDOWN = 60;

    public function requestFor(Persona $persona): array
    {
        $sent = $this->send($persona->epasts, app()->getLocale(), $persona->getKey());

        return [
            'code' => 'verification_required',
            'message' => __($sent ? 'email-verification.registered' : 'email-verification.mail_unavailable'),
            'email' => $persona->epasts,
            'mail_status' => $sent ? 'sent' : 'unavailable',
            'retry_after' => self::COOLDOWN,
        ];
    }

    public function send(string $email, string $locale, ?int $personaId = null): bool
    {
        try {
            if (! AccountMail::available()) {
                Log::warning('Email verification requires a configured Resend mailer.');

                return false;
            }

            $delivery = DB::transaction(function () use ($email, $personaId) {
                $query = Persona::forEmail($email)->whereNull('email_verified_at');
                if ($personaId !== null) {
                    $query->whereKey($personaId);
                }
                $persona = $query->lockForUpdate()->first();
                if (! $persona) {
                    return null;
                }

                $address = $persona->getEmailForVerification();
                $key = 'email-verification:'.hash_hmac('sha256', mb_strtolower($address), config('app.key'));
                if (! Cache::add($key, true, self::COOLDOWN)) {
                    return null;
                }

                $token = bin2hex(random_bytes(32));
                DB::table('email_verification_tokens')->updateOrInsert(['persona_id' => $persona->getKey()], [
                    'email' => $address,
                    'token_hash' => $this->digest($persona, $token),
                    'expires_at' => now()->addHours(self::VALID_HOURS),
                ]);

                return [$persona, $token];
            }, 3);

            if ($delivery === null) {
                return false;
            }
            [$persona, $token] = $delivery;
            $persona->notify((new VerifyPersonaEmail($token))->locale($locale));

            return true;
        } catch (Throwable) {
            // A transport exception may include the whole message, including the secret link.
            Log::warning('Email verification message could not be sent.');

            return false;
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
