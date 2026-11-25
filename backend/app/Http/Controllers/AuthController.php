<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Klients;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Support\EmailVerification;
use App\Support\AccountRestrictions;
use App\Support\GoogleOAuth;
use App\Support\TurnstileService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends Controller
{
    public function register(Request $request, EmailVerification $verification)
    {
        // Vispirms validēt — ja neiziet, Laravel automātiski atgriež 422
        $data = $request->validate([
            'name' => ['required','string','min:2','max:255'],
            'email' => ['required','email','max:255','unique:persona,epasts'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'loma' => ['required','in:klients,pakalpojumu_sniedzejs'],
            'kontakttalrunis' => ['required', 'string', 'max:20'],
            'bankas_konts' => ['required', 'string', 'max:34'],
            'lietotajvards' => ['required_if:loma,klients', 'string', 'max:30', 'unique:klients,lietotajvards'],
            'registracijas_numurs' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'iela' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:150'],
            'majas_numurs' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'dzivokla_numurs' => ['nullable', 'string', 'max:20'],
            'pilseta' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:100'],
            'pasta_indekss' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'email.unique' => __('app.emailTaken'),
            'lietotajvards.unique' => __('app.usernameTaken'),
            'name.required' => __('app.nameRequired'),
            'password.required' => __('app.passwordRequired'),
            'password.min' => __('app.passwordMin'),
            'password.mixed' => __('app.passwordUppercase'),
            'password.symbols' => __('app.passwordSymbol'),
            'kontakttalrunis.required' => __('app.phoneRequired'),
            'bankas_konts.required' => __('app.ibanRequired'),
        ]);

        $data['email'] = Persona::normalizeEmail($data['email']);
        if (Persona::forEmail($data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => __('app.emailTaken')]);
        }

        $nameParts = explode(' ', trim($data['name']), 2);
        $roleFields = ['lietotajvards' => $data['lietotajvards'] ?? null];

        if ($data['loma'] === 'pakalpojumu_sniedzejs') {
            $apartmentNumber = $this->normalizeOptionalString($data['dzivokla_numurs'] ?? null);

            $coords = [
                'lat' => $data['latitude'] ?? null,
                'lng' => $data['longitude'] ?? null,
            ];

            if ($coords['lat'] === null || $coords['lng'] === null) {
                $coords = $this->geocodeAddress([
                $data['iela'] ?? null,
                $data['majas_numurs'] ?? null,
                $data['pilseta'] ?? null,
                $data['pasta_indekss'] ?? null,
                'Latvia',
            ], [
                $data['iela'] ?? null,
                $data['pilseta'] ?? null,
                $data['pasta_indekss'] ?? null,
                'Latvia',
            ]);
            }

            $roleFields = [
                'registracijas_numurs' => $data['registracijas_numurs'],
                'iela' => $data['iela'],
                'majas_numurs' => $data['majas_numurs'],
                'dzivokla_numurs' => $apartmentNumber,
                'pilseta' => $data['pilseta'],
                'pasta_indekss' => $data['pasta_indekss'],
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
            ];
        }

        try {
            $persona = DB::transaction(function () use ($data, $nameParts, $roleFields) {
                $persona = Persona::create([
                    'vards' => $nameParts[0],
                    'uzvards' => $nameParts[1] ?? '',
                    'epasts' => $data['email'],
                    'parole' => Hash::make($data['password']),
                    'loma' => $data['loma'],
                    'kontakttalrunis' => $data['kontakttalrunis'],
                    'bankas_konts' => $data['bankas_konts'],
                ]);
                $persona->forceFill(['preferred_locale' => app()->getLocale()])->save();

                $relation = $persona->loma === 'klients' ? $persona->klients() : $persona->pakalpojumuSniedzejs();
                $relation->create($roleFields);

                return $persona;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (Persona::forEmail($data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => __('app.emailTaken')]);
            }
            if ($data['loma'] === 'klients' && Klients::where('lietotajvards', $data['lietotajvards'])->exists()) {
                throw ValidationException::withMessages(['lietotajvards' => __('app.usernameTaken')]);
            }
            throw $exception;
        }

        $this->clearWebSession($request);

        $requestId = (string) Str::uuid();

        return response()->json($verification->requestFor($request, $persona, $requestId), 201)
            ->header('Cache-Control', 'no-store')->header('X-Request-ID', $requestId);
    }

    public function login(Request $request, EmailVerification $verification, TurnstileService $turnstile)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required', 'string'],
        ]);

        if (! $turnstile->enabled()) {
            return $this->attemptLogin($request, $data, $verification, $turnstile);
        }

        $key = 'password-login:'.hash_hmac('sha256', Persona::normalizeEmail($data['email']).'|'.$request->ip(), config('app.key'));
        try {
            // Serialize failures for this email and IP so parallel requests cannot all use the first three attempts.
            return Cache::lock($key.':lock', 15)->block(2, fn () => $this->attemptLogin($request, $data, $verification, $turnstile, $key));
        } catch (LockTimeoutException) {
            throw new TooManyRequestsHttpException(2);
        }
    }

    private function attemptLogin(Request $request, #[\SensitiveParameter] array $data, EmailVerification $verification, TurnstileService $turnstile, ?string $key = null)
    {
        $captchaVerified = $key !== null && RateLimiter::tooManyAttempts($key, max(1, config('turnstile.login_attempts')));
        if ($captchaVerified) {
            $turnstile->verify($request, 'login');
        }

        $persona = Persona::forEmail($data['email'])->first();
        $validPassword = $persona?->parole
            ? Hash::check($data['password'], $persona->parole)
            : false;
        if (! $validPassword) {
            if (! $persona?->parole) {
                // Match the password hashing cost for unknown accounts and accounts without a password.
                Hash::make(Str::random(32));
            }
            if ($key !== null) {
                RateLimiter::hit($key, 600);
            }

            return response()->json(['message' => __('app.invalidCredentials')], 422)->header('Cache-Control', 'no-store');
        }

        if ($turnstile->enabled() && ! $captchaVerified && $persona->loma === 'administrators') {
            $turnstile->verify($request, 'login');
            $captchaVerified = true;
        }

        $response = $this->issueToken($request, $persona, $verification, $turnstile, $captchaVerified);
        if ($key !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    public function logout(Request $request)
    {
        $persona = $request->user('sanctum');
        if (!$persona && !GoogleOAuth::pending($request)) {
            return response()->json(['message' => __('app.authenticationRequired')], 401);
        }

        $token = $persona?->currentAccessToken();
        if ($token && !($token instanceof TransientToken)) {
            $token->delete();
        }
        $this->clearWebSession($request);

        return response()->json(['message' => 'OK']);
    }

    public function me(Request $request)
    {
        $persona = $request->user('sanctum');
        if ($persona && ($error = AccountRestrictions::authenticationError($request, $persona))) {
            return $error;
        }
        if (! $persona && $request->hasSession() && ($blockedId = $request->session()->pull('blocked_login_account'))) {
            $account = Persona::find($blockedId);
            if ($account && ($block = AccountRestrictions::block($account))) {
                return response()->json(AccountRestrictions::blockDetails($block), 403)->header('Cache-Control', 'no-store');
            }
        }
        if ($persona && ! $persona->hasVerifiedEmail() && ! $persona->mayCompleteGoogleProfile()) {
            return RequireVerifiedEmail::response();
        }
        $pending = GoogleOAuth::pending($request);
        $googlePending = $pending ? [
            'purpose' => $pending['purpose'],
            'vards' => $pending['identity']['vards'],
            'uzvards' => $pending['identity']['uzvards'],
            'epasts' => $pending['identity']['epasts'],
            'loma' => $pending['loma'] ?? null,
        ] : null;

        return response()->json([
            'persona' => $persona?->profileData(),
            'google_pending' => $googlePending,
        ], $persona || $pending ? 200 : 401)->header('Cache-Control', 'no-store');
    }

    private function issueToken(Request $request, Persona $persona, EmailVerification $verification, TurnstileService $turnstile, bool $captchaVerified)
    {
        [$persona, $token] = DB::transaction(function () use ($persona, $request, $verification, $turnstile, $captchaVerified) {
            $active = Persona::whereKey($persona->getKey())->lockForUpdate()->first();
            abort_unless($active, 401, __('app.authenticationRequired'));
            if (! hash_equals($persona->getAuthPassword(), $active->getAuthPassword())) {
                abort(response()->json(['message' => __('app.invalidCredentials')], 422));
            }
            if ($turnstile->enabled() && ! $captchaVerified && $active->loma === 'administrators') {
                $turnstile->requireChallenge();
            }
            AccountRestrictions::assertUnblocked($active);
            if (! $active->hasVerifiedEmail()) {
                $verification->rememberPending($request, $active);
                abort(RequireVerifiedEmail::response());
            }
            $active->forceFill(['preferred_locale' => app()->getLocale()])->save();
            $active->tokens()->delete();

            return [$active, $active->createToken('frontend')->plainTextToken];
        }, 3);
        $this->clearWebSession($request);

        return response()->json([
            'persona' => $persona->profileData(),
            'token' => $token,
        ])->header('Cache-Control', 'no-store');
    }

    private function clearWebSession(Request $request): void
    {
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function geocodeAddress(array $parts, array $fallbackParts = []): array
    {
        $candidates = [$parts];
        if (!empty($fallbackParts)) {
            $candidates[] = $fallbackParts;
        }

        foreach ($candidates as $candidate) {
            $address = implode(', ', array_filter($candidate, fn ($part) => $part && trim((string) $part) !== ''));
            if ($address === '') {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'EasyRent/1.0 (contact@easyrent.local)',
                    'Accept' => 'application/json',
                ])->timeout(6)->retry(1, 200)->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'limit' => 1,
                    'q' => $address,
                ]);

                if (!$response->ok()) {
                    continue;
                }

                $data = $response->json();
                if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
                    continue;
                }

                return [
                    'lat' => (float) $data[0]['lat'],
                    'lng' => (float) $data[0]['lon'],
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
