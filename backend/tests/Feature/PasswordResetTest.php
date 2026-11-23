<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Previous123!';
    private const NEW_PASSWORD = 'Replacement123!';
    private const FRONTEND = 'https://rent.example.test';
    private const REQUEST = '/api/auth/forgot-password';
    private const RESET = '/api/auth/reset-password';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => self::FRONTEND.'/',
            'mail.default' => 'array',
        ]);
        $this->withHeader('Accept-Language', 'en');
    }

    #[DataProvider('languages')]
    public function test_real_mail_transport_renders_both_parts_in_the_requested_language(string $locale, string $subject): void
    {
        $persona = $this->account('Rental+QA@example.test');
        $this->postJson(self::REQUEST, ['email' => 'rental+qa@EXAMPLE.TEST', 'locale' => $locale], [
            'Host' => 'untrusted.example.test',
            'Accept-Language' => 'en',
        ])->assertOk()->assertHeader('Content-Language', $locale)
            ->assertExactJson(['message' => __('password-reset.requested', [], $locale)]);

        $this->assertCount(1, $this->messages());
        /** @var Email $message */
        $message = $this->messages()[0]->getOriginalMessage();
        $this->assertSame($subject, $message->getSubject());
        $this->assertSame($persona->epasts, $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('lang="'.$locale.'"', $message->getHtmlBody());
        $this->assertStringContainsString(__('password-reset.mail.expires', ['minutes' => 30], $locale), $message->getHtmlBody());
        $this->assertStringContainsString(__('password-reset.mail.ignore', [], $locale), $message->getTextBody());
        $this->assertStringNotContainsString('untrusted.example.test', $message->toString());

        $token = $this->tokenFromMail($persona, $locale);
        $stored = DB::table('password_reset_tokens')->where('email', $persona->epasts)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }

    public static function languages(): array
    {
        return [
            ['lv', 'Atjaunojiet savu EasyRent paroli'],
            ['ru', 'Сброс пароля EasyRent'],
            ['en', 'Reset your EasyRent password'],
        ];
    }

    public function test_requests_for_missing_deleted_google_only_and_throttled_accounts_have_the_same_response(): void
    {
        $account = $this->account('user@example.test');
        $google = $this->account('google@example.test', googleId: '123', password: null);
        $deleted = $this->account('deleted@example.test');
        $deleted->delete();

        $reply = $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk()->json();
        foreach (['missing@example.test', $deleted->epasts, $google->epasts, strtoupper($account->epasts)] as $email) {
            $this->postJson(self::REQUEST, ['email' => $email])->assertOk()->assertExactJson($reply);
        }
        $this->assertCount(1, $this->messages());
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->postJson(self::REQUEST, ['email' => 'another@example.test'])->assertTooManyRequests();

        $first = $this->tokenFromMail($account);
        $this->travel(60)->seconds();
        $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk()->assertExactJson($reply);
        $this->assertCount(2, $this->messages());
        $this->assertFalse(Password::tokenExists($account, $first));
        $this->assertTrue(Password::tokenExists($account, $this->tokenFromMail($account, index: 1)));
    }

    public function test_switching_ip_cannot_bypass_the_email_limit_after_the_token_has_been_used(): void
    {
        $account = $this->account('user@example.test');
        $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk();
        $this->postJson(self::RESET, $this->resetData($account, $this->tokenFromMail($account)))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->postJson(self::REQUEST, ['email' => strtoupper($account->epasts)])->assertOk();
        $this->assertCount(1, $this->messages());
        $this->assertDatabaseEmpty('password_reset_tokens');
    }

    public function test_success_revokes_existing_tokens_and_web_sessions_and_keeps_the_google_link(): void
    {
        Event::fake([PasswordReset::class]);
        config(['session.driver' => 'database', 'sanctum.stateful' => ['rent.example.test']]);
        $account = $this->account('linked@example.test', googleId: 'linked-google');
        $other = $this->account('other@example.test');
        $bearer = $account->createToken('phone')->plainTextToken;
        $account->createToken('laptop');
        $other->createToken('phone');
        $sessions = $this->createWebSessions($account, $other);
        $headers = ['Referer' => self::FRONTEND.'/'];
        $this->withCredentials()->withCookie(config('session.cookie'), $sessions[0])
            ->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('persona.persona_id', $account->getKey());
        $this->resetRequestState();

        $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk();
        $data = $this->resetData($account, $this->tokenFromMail($account));
        $this->postJson(self::RESET, $data, $headers)->assertOk()->assertExactJson([
            'message' => __('password-reset.updated'),
        ]);
        $this->assertDatabaseEmpty('password_reset_tokens');
        $this->assertDatabaseMissing('sessions', ['user_id' => $account->getKey()]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->getKey()]);
        $this->assertSame(0, $account->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertSame('linked-google', $account->fresh()->google_id);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->fresh()->parole));
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($account));

        foreach ($sessions as $session) {
            $this->resetRequestState();
            $this->withCookie(config('session.cookie'), $session)->getJson('/api/auth/me', $headers)->assertUnauthorized();
        }
        $this->resetRequestState();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$bearer])->assertUnauthorized();
        $this->resetRequestState();
        $this->postJson(self::RESET, $data)->assertUnprocessable()->assertJsonPath('code', 'invalid_reset_link');
        $this->postJson('/api/auth/login', ['email' => $account->epasts, 'password' => self::OLD_PASSWORD])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $account->epasts, 'password' => self::NEW_PASSWORD])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_invalid_expired_and_ineligible_tokens_cannot_change_any_password(): void
    {
        $account = $this->account('user@example.test');
        $other = $this->account('other@example.test');
        $google = $this->account('google@example.test', googleId: '123', password: null);
        $deleted = $this->account('deleted@example.test');
        $deleted->delete();
        $token = Password::createToken($account);

        foreach ([
            $this->resetData($account, 'wrong-token'),
            $this->resetData($other, $token),
            $this->resetData($google, Password::createToken($google)),
            $this->resetData($deleted, Password::createToken($deleted)),
        ] as $data) {
            $this->postJson(self::RESET, $data)->assertUnprocessable()->assertJsonPath('code', 'invalid_reset_link');
        }
        $this->travel(30)->minutes();
        $this->travel(1)->second();
        $this->postJson(self::RESET, $this->resetData($account, $token))->assertUnprocessable()->assertJsonPath('code', 'invalid_reset_link');
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $account->fresh()->parole));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $other->fresh()->parole));
        $this->assertNull($google->fresh()->parole);
    }

    public function test_mismatch_and_weak_passwords_are_rejected_before_consuming_the_token(): void
    {
        $account = $this->account('user@example.test');
        $token = Password::createToken($account);
        $data = $this->resetData($account, $token);
        foreach ([
            ['password_confirmation' => 'Mismatch123!'],
            ['password' => 'Ab!', 'password_confirmation' => 'Ab!'],
            ['password' => 'LOWERLESS!', 'password_confirmation' => 'LOWERLESS!'],
            ['password' => 'Replacement123', 'password_confirmation' => 'Replacement123'],
            ['password' => str_repeat('Ж', 36).'a!', 'password_confirmation' => str_repeat('Ж', 36).'a!'],
        ] as $invalid) {
            $this->postJson(self::RESET, $invalid + $data, ['Accept-Language' => 'ru'])
                ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        }
        $this->assertTrue(Password::tokenExists($account, $token));
        $this->postJson(self::RESET, $data, ['Accept-Language' => 'lv'])->assertOk()
            ->assertExactJson(['message' => __('password-reset.updated', [], 'lv')]);
    }

    public function test_invalid_reset_attempts_are_rate_limited_per_ip(): void
    {
        $account = $this->account('user@example.test');
        $data = $this->resetData($account, 'wrong-token');
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::RESET, $data)->assertUnprocessable();
        }
        $this->postJson(self::RESET, $data)->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])->postJson(self::RESET, $data)->assertUnprocessable();
    }

    public function test_validation_returns_json_without_an_accept_header(): void
    {
        $this->post(self::REQUEST, ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->post(self::RESET, ['token' => 'unused'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_account_deletion_prevents_a_pending_link_from_resetting_a_reused_email(): void
    {
        $account = $this->account('user@example.test');
        $token = Password::createToken($account);
        $this->deleteJson('/api/profile/me', ['confirmation' => 'DZĒST', 'password' => self::OLD_PASSWORD], [
            'Accept-Language' => 'en',
            'Authorization' => 'Bearer '.$account->createToken('account')->plainTextToken,
        ])->assertNoContent();
        $replacement = $this->account('user@example.test');
        $this->postJson(self::RESET, $this->resetData($replacement, $token))
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_reset_link');
    }

    public function test_unconfigured_production_mail_never_falls_back_to_a_logging_transport(): void
    {
        $account = $this->account('user@example.test');
        $this->app->instance('env', 'production');
        Log::spy();
        foreach (['log', 'failover', 'resend'] as $mailer) {
            config(['mail.default' => $mailer, 'services.resend.key' => null]);
            $response = $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk();
            $this->assertDatabaseEmpty('password_reset_tokens');
            Log::shouldHaveReceived('log')->with('warning', 'Account email send result.', [
                'mail_type' => 'password_reset', 'recipient_domain' => 'example.test', 'resend_id' => null,
                'outcome' => 'error', 'error' => 'mailer_unavailable', 'request_id' => $response->headers->get('X-Request-ID'),
            ])->once();
            $this->travel(60)->seconds();
        }
    }

    public function test_resend_driver_resolves_and_transport_failure_keeps_the_generic_response_and_logs_no_secret(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
        $this->assertInstanceOf(ResendTransport::class, Mail::mailer()->getSymfonyTransport());
        Mail::purge('resend');
        Mail::extend('resend', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException($message->toString());
            }

            public function __toString(): string
            {
                return 'failing-transport';
            }
        });
        Log::spy();

        $account = $this->account('user@example.test');
        $reply = $this->postJson(self::REQUEST, ['email' => 'absent@example.test'])->assertOk()->json();
        $response = $this->postJson(self::REQUEST, ['email' => $account->epasts])->assertOk()->assertExactJson($reply);
        Log::shouldHaveReceived('log')->with('warning', 'Account email send result.', [
            'mail_type' => 'password_reset', 'recipient_domain' => 'example.test', 'resend_id' => null,
            'outcome' => 'error', 'error' => 'send_failed', 'request_id' => $response->headers->get('X-Request-ID'),
        ])->once();
    }

    private function account(string $email, ?string $googleId = null, ?string $password = self::OLD_PASSWORD): Persona
    {
        return Persona::forceCreate([
            'epasts' => $email,
            'vards' => 'Anna',
            'uzvards' => 'Ozola',
            'loma' => 'klients',
            'parole' => $password === null ? null : Hash::make($password),
            'google_id' => $googleId,
            'email_verified_at' => now(),
        ]);
    }

    private function messages(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->all();
    }

    private function tokenFromMail(Persona $account, string $locale = 'en', int $index = 0): string
    {
        /** @var Email $message */
        $message = $this->messages()[$index]->getOriginalMessage();
        preg_match('~'.preg_quote(self::FRONTEND, '~').'/reset-password#([^\s]+)~', $message->getTextBody(), $parts);
        $this->assertNotEmpty($parts, 'The plain-text message must contain the frontend reset URL.');
        parse_str($parts[1], $fragment);
        $this->assertSame($account->epasts, $fragment['email']);
        $this->assertSame($locale, $fragment['lang']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fragment['token']);
        $this->assertStringContainsString(e($parts[0]), $message->getHtmlBody());

        return $fragment['token'];
    }

    private function resetData(Persona $account, string $token): array
    {
        return [
            'email' => $account->epasts,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function createWebSessions(Persona $account, Persona $other): array
    {
        $sessions = [];
        foreach ([$account, $account, $other] as $owner) {
            $id = \Illuminate\Support\Str::random(40);
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $owner->getKey(),
                'payload' => base64_encode(serialize([Auth::guard('web')->getName() => $owner->getKey()])),
                'last_activity' => now()->timestamp,
            ]);
            $sessions[] = $id;
        }

        return array_slice($sessions, 0, 2);
    }

    private function resetRequestState(): void
    {
        Auth::forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
