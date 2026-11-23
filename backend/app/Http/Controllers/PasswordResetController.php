<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

use function Illuminate\Support\defer;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'locale' => ['sometimes', 'required', Rule::in(['lv', 'ru', 'en'])],
        ]);

        $email = mb_strtolower($data['email']);
        $locale = app()->getLocale();
        defer(function () use ($email, $locale) {
            try {
                $key = 'password-reset-email:'.hash_hmac('sha256', $email, config('app.key'));
                if (Cache::add($key, true, 60)) {
                    $this->sendLink($email, $locale);
                }
            } catch (Throwable) {
                // Transport exceptions can contain the message body or authentication headers.
                Log::warning('Password reset email could not be sent.');
            }
        });

        $response = response()->json([
            'message' => __('password-reset.requested'),
        ])->header('Cache-Control', 'no-store');
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));

        return $response;
    }

    private function sendLink(string $email, string $locale): void
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        $testTransport = $transport === 'array' && app()->environment('local', 'testing');
        if (! $testTransport && ($transport !== 'resend' || blank(config('services.resend.key')))) {
            Log::warning('Password reset email requires a configured Resend mailer.');

            return;
        }

        $delivery = DB::transaction(function () use ($email) {
            $persona = Persona::forEmail($email)->whereNotNull('parole')->lockForUpdate()->first();
            if (! $persona || Password::getRepository()->recentlyCreatedToken($persona)) {
                return null;
            }

            return [$persona, Password::createToken($persona)];
        }, 3);

        if ($delivery !== null) {
            [$persona, $token] = $delivery;
            $persona->sendPasswordResetNotification($token, $locale);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:64'],
            'password' => [
                'bail', 'required', 'string', 'confirmed', PasswordRule::defaults(),
                function (string $attribute, string $value, \Closure $fail): void {
                    if (str_contains($value, "\0") || (config('hashing.driver') === 'bcrypt' && strlen($value) > 72)) {
                        $fail(__('password-reset.passwordLength'));
                    }
                },
            ],
        ], [
            'password.mixed' => __('password-reset.passwordMixed'),
        ]);

        $persona = DB::transaction(function () use ($data) {
            // Serialize token issuance, consumption and account deletion on the same account row.
            $persona = Persona::forEmail($data['email'])->whereNotNull('parole')->lockForUpdate()->first();
            // A null primary key still goes through the broker's timing protection.
            Password::getTimebox()->dontReturnEarly();
            $status = Password::reset([
                'persona_id' => $persona?->getKey(),
                'epasts' => $persona?->epasts ?? $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
            ], function (Persona $account, #[\SensitiveParameter] string $password) {
                $account->forceFill(['parole' => Hash::make($password)])->save();
                $account->tokens()->delete();
                $account->purgeDatabaseSessions();
            });

            return $status === Password::PASSWORD_RESET ? $persona : null;
        }, 3);

        if (! $persona) {
            return response()->json([
                'message' => __('password-reset.invalidLink'),
                'code' => 'invalid_reset_link',
            ], 422)->header('Cache-Control', 'no-store');
        }

        $guard = Auth::guard('web');
        if ($request->hasSession() && (string) $guard->id() === (string) $persona->getKey()) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        event(new PasswordReset($persona->refresh()));

        return response()->json(['message' => __('password-reset.updated')])->header('Cache-Control', 'no-store');
    }
}
