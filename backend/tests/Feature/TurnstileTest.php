<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\Persona;
use App\Support\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Initial123!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => 'https://easyrent.vip',
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'fixture-secret',
            'turnstile.allowed_hostnames' => 'easyrent.vip',
            'turnstile.login_attempts' => 3,
        ]);
        Http::preventStrayRequests();
        $this->withHeader('Accept-Language', 'en');
    }

    public function test_registration_verifies_the_token_before_creating_the_account_and_rejects_replay(): void
    {
        $this->fakeSuccess('register');
        $body = $this->registration();
        $this->postJson('/api/auth/register', $body)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->postJson('/api/auth/register?turnstile_token=fixture-token', $body)->assertUnprocessable()
            ->assertJsonPath('code', 'captcha_required');
        Http::assertNothingSent();
        $this->assertDatabaseEmpty('persona');

        $this->postJson('/api/auth/register', $body + ['turnstile_token' => 'fixture-token'])
            ->assertCreated()->assertJsonPath('mail_status', 'sent')->assertJsonMissingPath('token');
        Http::assertSent(fn (Request $request) => $request->url() === TurnstileService::SITEVERIFY
            && $request->method() === 'POST'
            && $request->data() === ['secret' => 'fixture-secret', 'response' => 'fixture-token']);

        $this->postJson('/api/auth/register', ['email' => 'another@example.test', 'lietotajvards' => 'another']
            + $body + ['turnstile_token' => 'fixture-token'])->assertUnprocessable()->assertJsonPath('code', 'captcha_expired');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('persona', 1);
        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    public function test_a_successful_siteverify_response_cannot_redeem_a_token_consumed_while_the_request_was_in_flight(): void
    {
        $token = 'fixture-concurrent';
        Http::fake([TurnstileService::SITEVERIFY => function () use ($token) {
            Cache::add('turnstile-used:'.hash('sha256', $token), true, 300);

            return Http::response([
                'success' => true, 'action' => 'register', 'hostname' => 'easyrent.vip', 'challenge_ts' => now()->toISOString(),
            ]);
        }]);

        $this->postJson('/api/auth/register', $this->registration() + ['turnstile_token' => $token])
            ->assertUnprocessable()->assertJsonPath('code', 'captcha_expired');
        $this->assertDatabaseEmpty('persona');
    }

    #[DataProvider('invalidChallenges')]
    public function test_siteverify_rejections_cannot_create_an_account(string $code, array $result): void
    {
        $this->fakeSuccess('register', $result);

        $this->postJson('/api/auth/register', $this->registration() + ['turnstile_token' => 'fixture-token'])
            ->assertUnprocessable()->assertJsonPath('code', $code);
        $this->assertDatabaseEmpty('persona');
    }

    public static function invalidChallenges(): array
    {
        return [
            'forged' => ['captcha_invalid', ['success' => false, 'error-codes' => ['invalid-input-response']]],
            'expired or redeemed upstream' => ['captcha_expired', ['success' => false, 'error-codes' => ['timeout-or-duplicate']]],
            'old successful response' => ['captcha_expired', ['challenge_ts' => '2000-01-01T00:00:00.000Z']],
            'malformed timestamp' => ['captcha_invalid', ['challenge_ts' => 'yesterday']],
            'wrong hostname' => ['captcha_invalid', ['hostname' => 'easyrent.vip.example.test']],
            'wrong action' => ['captcha_invalid', ['action' => 'login']],
            'missing action' => ['captcha_invalid', ['action' => null]],
        ];
    }

    public function test_oversized_and_non_string_tokens_are_rejected_before_network_access(): void
    {
        foreach ([str_repeat('x', 2049), ['token']] as $token) {
            $this->postJson('/api/auth/register', $this->registration() + ['turnstile_token' => $token])
                ->assertUnprocessable()->assertJsonPath('code', 'captcha_invalid');
        }
        Http::assertNothingSent();
        $this->assertDatabaseEmpty('persona');
    }

    public function test_cloudflare_unavailability_fails_closed(): void
    {
        Http::fake([TurnstileService::SITEVERIFY => Http::failedConnection()]);
        $this->postJson('/api/auth/register', $this->registration() + ['turnstile_token' => 'fixture-token'])
            ->assertServiceUnavailable()->assertJsonPath('code', 'captcha_unavailable');
        $this->assertDatabaseEmpty('persona');
    }

    public function test_production_rejects_missing_and_dummy_secrets(): void
    {
        $this->app->instance('env', 'production');
        foreach (['', '1x0000000000000000000000000000000AA'] as $secret) {
            config(['turnstile.secret_key' => $secret]);
            $this->postJson('/api/auth/register', $this->registration())
                ->assertServiceUnavailable()->assertJsonPath('code', 'captcha_configuration');
        }
        Http::assertNothingSent();
        $this->assertDatabaseEmpty('persona');
    }

    public function test_official_dummy_response_is_supported_locally(): void
    {
        config(['turnstile.secret_key' => '1x0000000000000000000000000000000AA']);
        Http::fake([TurnstileService::SITEVERIFY => Http::response([
            'success' => true,
            'hostname' => 'example.com',
            'challenge_ts' => '2022-02-28T15:14:30.096Z',
        ])]);

        $this->postJson('/api/auth/register', $this->registration() + ['turnstile_token' => 'XXXX.DUMMY.TOKEN.XXXX'])
            ->assertCreated();
    }

    public function test_login_challenges_after_three_failures_for_the_normalized_email_and_ip_and_resets_on_success(): void
    {
        $persona = $this->account();
        $this->fakeSuccess('login');
        $login = ['email' => $persona->epasts, 'password' => self::PASSWORD];
        $incorrect = ['email' => strtoupper($persona->epasts), 'password' => 'incorrect'];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/auth/login', $incorrect)->assertUnprocessable()->assertJsonMissingPath('code');
        }
        $this->postJson('/api/auth/login', $login)->assertUnprocessable()
            ->assertJsonPath('code', 'captcha_required')->assertJsonMissingPath('token');
        Http::assertNothingSent();
        $this->assertDatabaseEmpty('personal_access_tokens');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/auth/login', $incorrect)->assertUnprocessable()->assertJsonMissingPath('code');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/auth/login', $login + ['turnstile_token' => 'fixture-login'])->assertOk()
            ->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', $incorrect)->assertUnprocessable()->assertJsonMissingPath('code');
        $this->postJson('/api/auth/login', $login)->assertOk()->assertJsonStructure(['token']);
        Http::assertSentCount(1);
    }

    public function test_admin_password_login_requires_captcha_without_disclosing_role_on_wrong_credentials(): void
    {
        $admin = $this->account('administrators');
        $this->fakeSuccess('login');
        $credentials = ['email' => $admin->epasts, 'password' => self::PASSWORD];
        $wrong = $this->postJson('/api/auth/login', ['password' => 'incorrect'] + $credentials)->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])
            ->assertUnprocessable()->assertExactJson($wrong->json());

        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable()
            ->assertJsonPath('code', 'captcha_required')->assertJsonMissingPath('token');
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->postJson('/api/auth/login', $credentials + ['turnstile_token' => 'fixture-admin'])
            ->assertOk()->assertJsonPath('persona.loma', 'administrators')->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        Http::assertSentCount(1);
    }

    public function test_provider_login_counter_expires_without_locking_the_account(): void
    {
        $account = $this->account('pakalpojumu_sniedzejs');
        $credentials = ['email' => $account->epasts, 'password' => self::PASSWORD];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/auth/login', ['password' => 'incorrect'] + $credentials)
                ->assertUnprocessable()->assertJsonMissingPath('code');
        }
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->travel(10)->minutes();
        $this->postJson('/api/auth/login', $credentials)->assertOk()->assertJsonStructure(['token']);
        Http::assertNothingSent();
    }

    public function test_password_reset_requires_captcha_only_when_requesting_the_link_and_admin_still_needs_captcha_to_sign_in(): void
    {
        $admin = $this->account('administrators');
        $this->fakeSuccess('forgot_password');
        $this->postJson('/api/auth/forgot-password', ['email' => $admin->epasts])->assertUnprocessable()
            ->assertJsonPath('code', 'captcha_required');
        $this->assertDatabaseEmpty('password_reset_tokens');
        $this->postJson('/api/auth/forgot-password', ['email' => $admin->epasts, 'turnstile_token' => 'fixture-reset'])->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $admin->epasts]);

        Password::deleteToken($admin);
        $newCredentials = ['email' => $admin->epasts, 'password' => 'Replacement123!'];
        $this->postJson('/api/auth/reset-password', $newCredentials + [
            'token' => Password::createToken($admin),
            'password_confirmation' => $newCredentials['password'],
        ])->assertOk()->assertJsonMissingPath('token');
        $this->postJson('/api/auth/login', $newCredentials)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->assertDatabaseEmpty('personal_access_tokens');
        Http::assertSentCount(1);
    }

    public function test_support_forms_and_email_resend_cannot_reach_mailers_without_captcha(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => 'fixture-resend-key']);
        Mail::shouldReceive('mailer')->never();
        Mail::shouldReceive('to')->never();
        $email = 'support@example.test';
        $this->account('klients', $email)->forceFill(['email_verified_at' => null])->save();
        foreach ([
            '/api/contact' => ['name' => 'Example User', 'email' => $email, 'comment' => 'Please help me.'],
            '/api/support/invoice' => ['booking_id' => 1, 'email' => $email],
            '/api/auth/verification/resend' => ['email' => $email],
            '/api/auth/forgot-password' => ['email' => $email],
            '/api/auth/register' => $this->registration(),
        ] as $path => $body) {
            $this->postJson($path, $body)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        }
        $this->assertDatabaseEmpty('email_verification_tokens');
        $this->assertDatabaseEmpty('password_reset_tokens');
        Http::assertNothingSent();
    }

    public function test_support_uses_separate_actions_and_preserves_its_rate_limit(): void
    {
        Mail::fake();
        $this->fakeSuccess('contact');
        $email = 'support@example.test';
        $this->postJson('/api/support/invoice', ['booking_id' => 1, 'email' => $email, 'turnstile_token' => 'wrong-action'])
            ->assertUnprocessable()->assertJsonPath('code', 'captcha_invalid');
        $body = ['name' => 'Example User', 'email' => $email, 'comment' => 'Please help me.'];
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/contact', $body + ['turnstile_token' => 'fixture-contact-'.$attempt])
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->postJson('/api/contact', $body + ['turnstile_token' => 'extra'])->assertTooManyRequests();
        Http::assertSentCount(5);
        Mail::assertSent(ContactMessageMail::class, 4);
    }

    public function test_mass_login_limit_spans_email_addresses_and_short_rate_windows(): void
    {
        for ($minute = 0; $minute < 5; $minute++) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $this->postJson('/api/auth/login', [
                    'email' => "attempt-$minute-$attempt@example.test", 'password' => 'incorrect',
                ])->assertUnprocessable()->assertJsonMissingPath('code');
            }
            $this->travel(61)->seconds();
        }
        $this->postJson('/api/auth/login', ['email' => 'different@example.test', 'password' => 'incorrect'])
            ->assertTooManyRequests();
        Http::assertNothingSent();
    }

    private function fakeSuccess(string $action, array $overrides = []): void
    {
        Http::fake([TurnstileService::SITEVERIFY => Http::response($overrides + [
            'success' => true,
            'action' => $action,
            'hostname' => 'easyrent.vip',
            'challenge_ts' => now()->toISOString(),
        ])]);
    }

    private function account(string $role = 'klients', string $email = 'existing@example.test'): Persona
    {
        return Persona::forceCreate([
            'epasts' => $email,
            'loma' => $role,
            'parole' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
    }

    private function registration(): array
    {
        return [
            'name' => 'Example User',
            'email' => 'renter@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'loma' => 'klients',
            'lietotajvards' => 'renter',
            'kontakttalrunis' => '+37120000000',
            'bankas_konts' => 'LV80BANK0000435195001',
        ];
    }
}
