<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Klients;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Support\EmailVerification;
use App\Support\GoogleOAuth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\TransientToken;

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

        $data['email'] = strtolower($data['email']);
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

        return response()->json($verification->requestFor($persona), 201)->header('Cache-Control', 'no-store');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required', 'string'],
        ]);

        $persona = Persona::forEmail($data['email'])->first();

        if (!$persona?->parole || !Hash::check($data['password'], $persona->parole)) {
            return response()->json(['message' => __('app.invalidCredentials')], 422);
        }

        return $this->issueToken($request, $persona);
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
        if ($persona && ! $persona->hasVerifiedEmail() && ! $persona->mayCompleteGoogleProfile()) {
            return RequireVerifiedEmail::response();
        }
        $pending = GoogleOAuth::pending($request);
        $googlePending = $pending ? [
            'purpose' => $pending['purpose'],
            'vards' => $pending['identity']['vards'],
            'uzvards' => $pending['identity']['uzvards'],
            'epasts' => $pending['identity']['epasts'],
        ] : null;

        return response()->json([
            'persona' => $persona?->profileData(),
            'google_pending' => $googlePending,
        ], $persona || $pending ? 200 : 401)->header('Cache-Control', 'no-store');
    }

    private function issueToken(Request $request, Persona $persona)
    {
        [$persona, $token] = DB::transaction(function () use ($persona) {
            $active = Persona::whereKey($persona->getKey())->lockForUpdate()->first();
            abort_unless($active, 401, __('app.authenticationRequired'));
            if (! hash_equals($persona->getAuthPassword(), $active->getAuthPassword())) {
                abort(response()->json(['message' => __('app.invalidCredentials')], 422));
            }
            if (! $active->hasVerifiedEmail()) {
                abort(RequireVerifiedEmail::response());
            }
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
