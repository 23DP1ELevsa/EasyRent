<?php

namespace App\Http\Controllers;

use App\Models\Persona;
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
        abort_unless($persona?->google_id, 403, 'Lai apstiprinātu dzēšanu, pierakstieties ar piesaistīto Google kontu.');
        abort_unless($google->configured(), 503, 'Google apstiprināšana pašlaik nav pieejama.');

        return response()->json([
            'authorization_url' => $google->start($request, null, (int) $persona->getKey()),
        ])->header('Cache-Control', 'no-store');
    }

    public function chooseRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loma' => ['required', Rule::in(self::ROLES)],
        ], [
            'loma.required' => 'Izvēlieties konta veidu.',
            'loma.in' => 'Izvēlieties klienta vai pakalpojumu sniedzēja kontu.',
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
            'password.required' => 'Ievadiet sava EasyRent konta paroli.',
            'password.string' => 'Ievadiet derīgu paroli.',
            'password.max' => 'Ievadiet derīgu paroli.',
        ]);

        $limitKey = 'google-link:'.$pending['persona_id'];
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return response()->json([
                'message' => 'Pārāk daudz mēģinājumu. Lūdzu, uzgaidiet un mēģiniet vēlreiz.',
            ], 429)->header('Retry-After', RateLimiter::availableIn($limitKey));
        }
        RateLimiter::hit($limitKey);

        try {
            $persona = DB::transaction(function () use ($pending, $identity, $data) {
                $persona = Persona::whereKey($pending['persona_id'])->lockForUpdate()->first();
                if (! $persona || ! $persona->parole || strcasecmp($persona->epasts ?? '', $identity['epasts']) !== 0
                    || ($persona->google_id !== null && $persona->google_id !== $identity['google_id'])
                    || ! Hash::check($data['password'], $persona->parole)) {
                    throw ValidationException::withMessages(['password' => 'Konta paroli neizdevās apstiprināt.']);
                }

                $persona->forceFill(['google_id' => $identity['google_id']])->save();

                return $persona;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $request->session()->forget(GoogleOAuth::PENDING);

            return response()->json(['message' => 'Šis Google konts jau ir piesaistīts citam EasyRent kontam.'], 409);
        }

        RateLimiter::clear($limitKey);
        $this->signIn($request, $persona);

        return response()->json(['persona' => $persona->profileData()])->header('Cache-Control', 'no-store');
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
            $this->signIn($request, $persona);

            return '/profile?google=ok';
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
                $persona->forceFill($identity + ['profile_completed' => false])->save();
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

        $this->signIn($request, $persona);

        return '/profile?google=ok';
    }

    private function emailOwner(array $identity): ?Persona
    {
        return $identity['epasts'] === null ? null : Persona::forEmail($identity['epasts'])->first();
    }

    private function confirmOwner(Request $request, array $identity, Persona $owner): string
    {
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
        abort_unless(($pending['purpose'] ?? null) === $purpose, 410, 'Google autorizācijas sesija ir beigusies. Mēģiniet vēlreiz.');

        return $pending;
    }

    private function signIn(Request $request, Persona $persona): void
    {
        $request->session()->invalidate();
        Auth::guard('web')->login($persona);
        $request->session()->regenerateToken();
    }

    private function redirect(string $destination): RedirectResponse
    {
        return (new RedirectResponse($destination))->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
