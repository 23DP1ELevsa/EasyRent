<?php

namespace App\Support;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleOAuth
{
    public const PENDING = 'google_oauth.pending';

    private const FLOW = 'google_oauth.flow';
    private const DELETION_PROOF = 'google_oauth.deletion_proof';

    public function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function start(Request $request, ?string $role, ?int $deletionAccount = null): string
    {
        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->forget([self::PENDING, self::DELETION_PROOF]);
        $request->session()->put(self::FLOW, [
            'state' => $state,
            'verifier' => $verifier,
            'role' => $role,
            'deletion_account' => $deletionAccount,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'prompt' => $deletionAccount === null ? 'select_account' : 'select_account consent',
            'hl' => app()->getLocale(),
            'state' => $state,
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function confirmDeletion(Request $request, int $account, string $subject): bool
    {
        $persona = Auth::guard('web')->user();
        if (! $persona || (int) $persona->getKey() !== $account || $persona->google_id !== $subject) {
            return false;
        }

        $request->session()->put(self::DELETION_PROOF, [
            'persona_id' => $account,
            'subject' => $subject,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return true;
    }

    public function deletionConfirmed(Request $request, Persona $persona): bool
    {
        $proof = $request->hasSession() ? $request->session()->get(self::DELETION_PROOF) : null;

        return $proof && $persona->google_id !== null
            && $proof['persona_id'] === (int) $persona->getKey()
            && (int) Auth::guard('web')->id() === (int) $persona->getKey()
            && $proof['subject'] === $persona->google_id
            && $proof['expires_at'] > now()->timestamp;
    }

    public function consumeFlow(Request $request): ?array
    {
        $flow = $request->session()->get(self::FLOW);
        $state = $request->query('state');

        if (! $flow || $flow['expires_at'] <= now()->timestamp
            || ! is_string($state) || ! hash_equals($flow['state'], $state)) {
            return null;
        }

        $request->session()->forget(self::FLOW);

        return $flow;
    }

    public static function pending(Request $request): ?array
    {
        $pending = $request->hasSession() ? $request->session()->get(self::PENDING) : null;

        return $pending && $pending['expires_at'] > now()->timestamp ? $pending : null;
    }

    public function profile(string $code, string $verifier): array
    {
        $token = Http::asForm()->acceptJson()->timeout(5)->withoutRedirecting()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $verifier,
            ]);

        $accessToken = $token->json('access_token');
        if (! $token->ok() || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google token exchange failed.');
        }

        $profile = Http::withToken($accessToken)->acceptJson()->timeout(5)->withoutRedirecting()
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        $subject = $profile->json('sub');

        if (! $profile->ok() || ! is_string($subject) || ! preg_match('/\A[\x21-\x7E]{1,255}\z/D', $subject)) {
            throw new RuntimeException('Google returned an invalid user profile.');
        }

        $email = $profile->json('email');
        $email = $profile->json('email_verified') === true && is_string($email)
            && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? strtolower($email) : null;

        return [
            'google_id' => $subject,
            'vards' => $this->name($profile->json('given_name')),
            'uzvards' => $this->name($profile->json('family_name')),
            'epasts' => $email,
        ];
    }

    private function name(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 50) : null;
    }
}
