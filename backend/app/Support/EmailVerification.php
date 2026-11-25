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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmailVerification
{
    public const VALID_HOURS = 24;

    public const AUTO_LOGIN_MINUTES = 15;

    public const COOLDOWN = 60;

    private const PENDING_SESSION = 'email_verification.pending';

    private const INITIATOR_SESSION = 'email_verification.initiator';

    public function requestFor(Request $request, Persona $persona, string $requestId): array
    {
        $this->rememberPending($request, $persona);
        if ($request->hasSession()) {
            $request->session()->put(self::INITIATOR_SESSION, $this->initiatorProof($persona));
        }
        $status = $this->send($persona->epasts, app()->getLocale(), $persona->getKey(), $requestId);

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
                'email' => Persona::normalizeEmail($persona->epasts),
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

    public function send(string $email, string $locale, ?int $personaId = null, ?string $requestId = null): string
    {
        $delivery = null;
        try {
            if (! AccountMail::available()) {
                AccountMail::logDelivery('email_verification', $email, $requestId, error: 'mailer_unavailable');

                return 'unavailable';
            }

            $prepared = DB::transaction(function () use ($email, $personaId) {
                $query = Persona::forEmail($email)->whereNull('email_verified_at');
                if ($personaId !== null) {
                    $query->whereKey($personaId);
                }
                $persona = $query->lockForUpdate()->first();
                if (! $persona || AccountRestrictions::block($persona)) {
                    return 'not_eligible';
                }

                $address = $persona->getEmailForVerification();
                $key = 'email-verification:'.hash_hmac('sha256', Persona::normalizeEmail($address), config('app.key'));
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
            if (! app(AccountMail::class)->send('email_verification', $persona, new VerifyPersonaEmail($token), $locale, $requestId)) {
                $this->restorePreviousLink(...$delivery);

                return 'unavailable';
            }

            return 'sent';
        } catch (Throwable) {
            AccountMail::logDelivery('email_verification', $email, $requestId, error: 'preparation_failed');
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

    public function confirm(Request $request, int $id, #[\SensitiveParameter] string $token, bool $interactive, bool $autoLogin): array
    {
        $initiator = $request->hasSession() ? $request->session()->get(self::INITIATOR_SESSION, '') : '';
        $signedIn = $request->user('sanctum') !== null || GoogleOAuth::pending($request) !== null;

        return DB::transaction(function () use ($id, $token, $initiator, $interactive, $autoLogin, $signedIn) {
            $persona = Persona::whereKey($id)->lockForUpdate()->first();
            if (! $persona || $persona->hasVerifiedEmail() || AccountRestrictions::block($persona)) {
                return ['code' => 'invalid_verification_link'];
            }

            $pending = DB::table('email_verification_tokens')->where('persona_id', $id)->first();
            if (! $pending || $pending->email !== $persona->getEmailForVerification()
                || ! hash_equals($pending->token_hash, $this->digest($persona, $token))) {
                return ['code' => 'invalid_verification_link'];
            }

            $expiresAt = Carbon::parse($pending->expires_at);
            $now = now();
            if ($expiresAt->lessThanOrEqualTo($now)) {
                return ['code' => 'verification_link_expired'];
            }

            $sameBrowser = hash_equals($this->initiatorProof($persona), $initiator);
            if (! $sameBrowser && ! $interactive) {
                return ['code' => 'verification_interaction_required'];
            }

            // A password chosen before proving mailbox ownership is unsafe in another browser.
            $resetRequired = ! $sameBrowser && $persona->google_id === null && $persona->parole !== null
                && $persona->email_verification_password_reset_at === null;
            if ($resetRequired) {
                $persona->parole = Hash::make(Str::random(64));
            }
            $this->markVerified($persona);

            $result = ['code' => 'email_verified'];
            if ($autoLogin && ! $signedIn && $sameBrowser && $persona->loma !== 'administrators'
                && $expiresAt->subHours(self::VALID_HOURS)->addMinutes(self::AUTO_LOGIN_MINUTES)->isAfter($now)) {
                return $result + [
                    'token' => $persona->createToken('frontend')->plainTextToken,
                    'persona' => $persona->profileData(),
                ];
            }

            return $result + ['password_reset_required' => $resetRequired, 'active_session' => $signedIn];
        }, 3);
    }

    /** Call within the transaction holding the account lock. */
    public function markVerified(Persona $persona): void
    {
        if ($persona->hasVerifiedEmail()) {
            return;
        }
        $persona->email_verification_password_reset_at = null;
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

    private function initiatorProof(Persona $persona): string
    {
        return hash_hmac('sha256', serialize([
            $persona->getKey(), $persona->getEmailForVerification(), $persona->getAuthPassword(),
        ]), config('app.key'));
    }

    private function digest(Persona $persona, #[\SensitiveParameter] string $token): string
    {
        return hash_hmac('sha256', $persona->getKey().':'.$persona->getEmailForVerification().':'.$token, config('app.key'));
    }
}
