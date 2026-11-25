<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireVerifiedEmail;
use App\Models\Persona;
use App\Support\EmailVerification;
use App\Support\AccountRestrictions;
use App\Support\GoogleOAuth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleOAuthController extends Controller
{
    private const ROLES = ['klients', 'pakalpojumu_sniedzejs'];

    public function __construct(private readonly EmailVerification $verification) {}

    public function start(Request $request, GoogleOAuth $google): RedirectResponse
    {
        if (! $google->configured()) {
            return $this->redirect('/auth?google=unavailable');
        }

        $role = $request->query('role');
        if ($role !== null && ! in_array($role, self::ROLES, true)) {
            return $this->redirect('/auth?google=invalid');
        }

        return $this->redirect($google->start($request, $role));
    }

    public function callback(Request $request, GoogleOAuth $google): RedirectResponse
    {
        $flow = $google->consumeFlow($request);
        if (! $flow) {
            return $this->redirect('/auth?google=invalid');
        }

        app()->setLocale($flow['locale'] ?? app()->getLocale());
        $deletionAccount = $flow['deletion_account'] ?? null;
        $returnPath = $deletionAccount === null ? '/auth?google=' : '/profile?delete_google=';

        if ($request->query('error') === 'access_denied') {
            return $this->redirect($returnPath.'cancelled');
        }

        $code = $request->query('code');
        if ($request->has('error') || ! is_string($code) || $code === '' || strlen($code) > 4096) {
            return $this->redirect($returnPath.'invalid');
        }

        try {
            $identity = $google->profile($code, $flow['verifier']);
        } catch (ConnectionException|RuntimeException $exception) {
            Log::warning('Google sign-in could not retrieve the user profile.', ['exception_type' => $exception::class]);

            return $this->redirect($returnPath.'failed');
        }

        if ($deletionAccount !== null) {
            return $this->redirect($returnPath.($google->confirmDeletion($request, $deletionAccount, $identity['google_id']) ? 'ok' : 'mismatch'));
        }

        return $this->redirect($this->continueSignIn($request, $identity, $flow['role']));
    }

    public function startDeletion(Request $request, GoogleOAuth $google): JsonResponse
    {
        $persona = Auth::guard('web')->user();
        abort_unless($persona?->google_id, 403, __('app.googleDeletionSignIn'));
        if ($error = AccountRestrictions::authenticationError($request, $persona)) {
            return $error;
        }
        if (! $persona->hasVerifiedEmail()) {
            return RequireVerifiedEmail::response();
        }
        abort_unless($google->configured(), 503, __('app.googleDeletionUnavailable'));

        return response()->json([
            'authorization_url' => $google->start($request, null, (int) $persona->getKey()),
        ])->header('Cache-Control', 'no-store');
    }

    public function chooseRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loma' => ['required', Rule::in(self::ROLES)],
        ], [
            'loma.required' => __('app.chooseRole'),
            'loma.in' => __('app.invalidRole'),
        ]);

        $pending = $this->requirePending($request, 'role');
        $next = $this->continueSignIn($request, $pending['identity'], $data['loma']);

        return response()->json(['next' => $next])->header('Cache-Control', 'no-store');
    }

    public function link(Request $request): JsonResponse
    {
        $pending = $this->requirePending($request, 'link');
        $identity = $pending['identity'];
        $data = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ], [
            'password.required' => __('app.googleLinkPasswordRequired'),
            'password.string' => __('app.passwordInvalid'),
            'password.max' => __('app.passwordInvalid'),
        ]);

        $limitKey = 'google-link:'.$pending['persona_id'];
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return response()->json([
                'message' => __('app.retryLater'),
            ], 429)->header('Retry-After', RateLimiter::availableIn($limitKey));
        }
        RateLimiter::hit($limitKey);

        try {
            $persona = DB::transaction(function () use ($pending, $identity, $data) {
                $persona = Persona::whereKey($pending['persona_id'])->lockForUpdate()->first();
                if (! $persona || ! $persona->parole || strcasecmp($persona->epasts ?? '', $identity['epasts']) !== 0
                    || ($persona->google_id !== null && $persona->google_id !== $identity['google_id'])
                    || ! Hash::check($data['password'], $persona->parole)) {
                    throw ValidationException::withMessages(['password' => __('app.googleLinkWrongPassword')]);
                }

                AccountRestrictions::assertUnblocked($persona);
                $persona->forceFill(['google_id' => $identity['google_id']])->save();
                $this->verification->markVerified($persona);

                return $persona;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $request->session()->forget(GoogleOAuth::PENDING);

            return response()->json(['message' => __('app.googleLinkedElsewhere')], 409);
        }

        RateLimiter::clear($limitKey);
        $next = $this->signIn($request, $persona);
        if ($next === '/auth?google=blocked' && ($block = AccountRestrictions::block($persona))) {
            return response()->json(AccountRestrictions::blockDetails($block), 403)->header('Cache-Control', 'no-store');
        }
        abort_unless($next === '/profile?google=ok', 409, __('app.authenticationRequired'));

        return response()->json(['persona' => $persona->refresh()->profileData()])->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request): JsonResponse
    {
        $request->session()->forget(GoogleOAuth::PENDING);

        return response()->json(['message' => 'OK']);
    }

    private function continueSignIn(Request $request, array $identity, ?string $role): string
    {
        $persona = Persona::where('google_id', $identity['google_id'])->first();
        if ($persona) {
            return $this->signIn($request, $persona, $identity['epasts']);
        }

        $owner = $this->emailOwner($identity);
        if ($owner) {
            return $this->confirmOwner($request, $identity, $owner);
        }

        if ($role === null) {
            $this->setPending($request, $identity, 'role');

            return '/profile?google=role';
        }

        try {
            $persona = DB::transaction(function () use ($identity, $role) {
                $persona = new Persona(['loma' => $role]);
                $persona->forceFill($identity + [
                    'profile_completed' => false,
                    'email_verified_at' => $identity['epasts'] === null ? null : now(),
                ])->save();
                $relation = $role === 'klients' ? $persona->klients() : $persona->pakalpojumuSniedzejs();
                $relation->create([]);

                return $persona;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Another callback or a password registration can win the database write.
            $persona = Persona::where('google_id', $identity['google_id'])->first();
            if (! $persona) {
                $owner = $this->emailOwner($identity);
                if (! $owner) {
                    throw $exception;
                }

                return $this->confirmOwner($request, $identity, $owner);
            }
        }

        return $this->signIn($request, $persona, $identity['epasts']);
    }

    private function emailOwner(array $identity): ?Persona
    {
        return $identity['epasts'] === null ? null : Persona::forEmail($identity['epasts'])->first();
    }

    private function confirmOwner(Request $request, array $identity, Persona $owner): string
    {
        if (AccountRestrictions::block($owner)) {
            return $this->blockedSignIn($request, $owner);
        }
        if ($owner->google_id !== null || ! $owner->parole) {
            $request->session()->forget(GoogleOAuth::PENDING);

            return '/auth?google=conflict';
        }

        $this->setPending($request, $identity, 'link', $owner->getKey());

        return '/auth?google=link';
    }

    private function setPending(Request $request, array $identity, string $purpose, ?int $owner = null): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(GoogleOAuth::PENDING, [
            'purpose' => $purpose,
            'identity' => $identity,
            'persona_id' => $owner,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    private function requirePending(Request $request, string $purpose): array
    {
        $pending = GoogleOAuth::pending($request);
        abort_unless(($pending['purpose'] ?? null) === $purpose, 410, __('app.googleSessionExpired'));

        return $pending;
    }

    private function signIn(Request $request, Persona $persona, ?string $googleEmail = null): string
    {
        if ($googleEmail !== null && ($persona->epasts === null || ! $persona->hasVerifiedEmail()) && ! AccountRestrictions::block($persona)) {
            $persona = $this->applyVerifiedGoogleEmail($persona, $googleEmail);
            if (! $persona) {
                return '/auth?google=invalid';
            }
        }

        return DB::transaction(function () use ($request, $persona) {
            $active = Persona::whereKey($persona->getKey())->lockForUpdate()->first();
            if (! $active || $active->google_id !== $persona->google_id) {
                return '/auth?google=invalid';
            }
            if (AccountRestrictions::block($active)) {
                return $this->blockedSignIn($request, $active);
            }

            $guard = Auth::guard('web');
            if (! $active->hasVerifiedEmail() && ($active->epasts !== null || $active->profile_completed)) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return '/verify-email';
            }

            $active->forceFill(['preferred_locale' => app()->getLocale()])->save();
            $request->session()->invalidate();
            $guard->login($active);
            $request->session()->put([
                'password_hash_web' => $guard->hashPasswordForCookie($active->getAuthPassword()),
                'account_session_generation' => (int) $active->session_generation,
            ]);
            $request->session()->regenerateToken();

            return '/profile?google=ok';
        }, 3);
    }

    private function blockedSignIn(Request $request, Persona $persona): string
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('blocked_login_account', $persona->getKey());

        return '/auth?google=blocked';
    }

    private function applyVerifiedGoogleEmail(Persona $persona, string $email): ?Persona
    {
        $account = Persona::whereKey($persona->getKey())->where('google_id', $persona->google_id);

        try {
            return DB::transaction(function () use ($account, $email) {
                $active = (clone $account)->lockForUpdate()->first();
                if (! $active) {
                    return null;
                }
                if (AccountRestrictions::block($active)) {
                    return $active;
                }
                if ($active->epasts === null && ! Persona::forEmail($email)->exists()) {
                    $active->forceFill(['epasts' => $email])->save();
                }
                if (strcasecmp($active->epasts ?? '', $email) === 0) {
                    $this->verification->markVerified($active);
                }

                return $active;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Another account claimed the address while this callback was updating the profile.
            return $account->first();
        }
    }

    private function redirect(string $destination): RedirectResponse
    {
        return (new RedirectResponse($destination))->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
