<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Support\TurnstileService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Resend\Client as ResendClient;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'renter+qa@example.com';
    private const PASSWORD = 'Initial123!';
    private const FRONTEND = 'https://easyrent.vip';
    private const CONFIRM = '/api/auth/verification/confirm';
    private const RESEND = '/api/auth/verification/resend';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => self::FRONTEND,
            'mail.default' => 'array',
        ]);
        $this->withHeader('Accept-Language', 'en');
    }

    #[DataProvider('locales')]
    public function test_registration_creates_pending_account_and_sends_localized_html_and_text(string $locale, string $subject): void
    {
        $this->postJson('/api/auth/register', $this->registration(), [
            'Accept-Language' => $locale,
            'Host' => 'untrusted.example.test',
        ])->assertCreated()->assertHeader('Content-Language', $locale)
            ->assertJsonPath('code', 'verification_required')->assertJsonPath('mail_status', 'sent')
            ->assertJsonMissingPath('token')->assertJsonMissingPath('persona');
        $persona = Persona::forEmail(self::EMAIL)->firstOrFail();
        $this->assertNull($persona->email_verified_at);
        $this->assertSame('qatest', $persona->klients->lietotajvards);
        $this->assertDatabaseEmpty('personal_access_tokens');

        $mail = $this->messages()[0]->getOriginalMessage();
        $this->assertSame($subject, $mail->getSubject());
        $this->assertSame(self::EMAIL, $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('lang="'.$locale.'"', $mail->getHtmlBody());
        $this->assertStringContainsString(__('email-verification.mail.expires', ['hours' => 24], $locale), $mail->getTextBody());
        $this->assertStringNotContainsString('untrusted.example.test', $mail->getHtmlBody());
        $link = $this->link();
        $this->assertSame($locale, $link['lang']);
        $this->assertSame((string) $persona->getKey(), $link['id']);
        $this->assertNotSame($link['token'], DB::table('email_verification_tokens')->value('token_hash'));
    }

    public static function locales(): array
    {
        return [
            ['lv', 'Apstipriniet savu EasyRent e-pasta adresi'],
            ['ru', 'Подтвердите адрес электронной почты для EasyRent'],
            ['en', 'Verify your EasyRent email address'],
        ];
    }

    public function test_login_requires_verification_and_activation_revokes_earlier_credentials_without_signing_in(): void
    {
        Event::fake([Verified::class]);
        config(['session.driver' => 'database', 'sanctum.stateful' => ['easyrent.vip']]);
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $persona = Persona::forEmail(self::EMAIL)->firstOrFail();
        $credentials = ['email' => self::EMAIL, 'password' => self::PASSWORD];
        $this->postJson('/api/auth/login', ['password' => 'Wrong123!'] + $credentials)->assertUnprocessable()->assertJsonMissingPath('code');
        $this->postJson('/api/auth/login', $credentials)->assertForbidden()->assertJsonPath('code', 'email_not_verified')
            ->assertJsonMissingPath('token');

        $stale = $persona->createToken('stale')->plainTextToken;
        $session = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $session,
            'user_id' => $persona->getKey(),
            'payload' => base64_encode(serialize([Auth::guard('web')->getName() => $persona->getKey()])),
            'last_activity' => now()->timestamp,
        ]);
        $this->withCredentials()->withCookie(config('session.cookie'), $session);
        $headers = ['Referer' => self::FRONTEND.'/'];
        $this->getJson('/api/auth/me', $headers)->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->resetRequestState();
        $link = $this->link();
        $this->postJson(self::CONFIRM, $link + ['interactive' => true], $headers)->assertOk()->assertJsonPath('code', 'email_verified')
            ->assertJsonMissingPath('token')->assertJsonMissingPath('persona')->assertJsonPath('password_reset_required', true);
        $this->assertNotNull($persona->fresh()->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $persona->getKey()]);
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->assertDatabaseEmpty('email_verification_tokens');
        $this->resetRequestState();
        $this->getJson('/api/auth/me', $headers + ['Authorization' => 'Bearer '.$stale])->assertUnauthorized();
        $this->postJson(self::CONFIRM, $link)->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_link');
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable();
    }

    #[DataProvider('emailLinkRoles')]
    public function test_the_signup_browser_can_sign_in_once_via_the_email_link_for_non_administrators(string $role): void
    {
        config(['turnstile.enabled' => true, 'turnstile.secret_key' => 'fixture-secret']);
        Http::fake([TurnstileService::SITEVERIFY => Http::response([
            'success' => true, 'action' => 'register', 'hostname' => 'easyrent.vip', 'challenge_ts' => now()->toISOString(),
        ])])->preventStrayRequests();
        $headers = $this->registerWithSession(['turnstile_token' => 'fixture-signup']);
        $link = $this->link();
        Persona::forEmail(self::EMAIL)->firstOrFail()->forceFill(['loma' => $role])->save();
        $this->getJson(self::CONFIRM, $headers)->assertMethodNotAllowed();
        $this->assertDatabaseEmpty('personal_access_tokens');
        $response = $this->postJson(self::CONFIRM, $link, $headers)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('code', 'email_verified');
        Http::assertSentCount(1);
        $this->assertDatabaseEmpty('email_verification_tokens');
        if ($role === 'administrators') {
            $response->assertJsonMissingPath('token')->assertJsonMissingPath('persona');
            $this->assertDatabaseEmpty('personal_access_tokens');

            return;
        }
        $response->assertJsonPath('persona.epasts', self::EMAIL);
        $token = $response->json('token');
        $this->assertIsString($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->resetRequestState();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$token])->assertOk()
            ->assertJsonPath('persona.epasts', self::EMAIL);
        $this->resetRequestState();
        $this->postJson(self::CONFIRM, $link, $headers)->assertUnprocessable()->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public static function emailLinkRoles(): array
    {
        return [['klients'], ['administrators']];
    }

    public function test_the_signup_browser_can_still_verify_at_the_auto_login_deadline(): void
    {
        $headers = $this->registerWithSession();
        $this->travel(15)->minutes();
        $this->postJson(self::CONFIRM, $this->link(), $headers)->assertOk()
            ->assertJsonPath('code', 'email_verified')->assertJsonPath('password_reset_required', false)
            ->assertJsonMissingPath('token')->assertJsonMissingPath('persona');
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->postJson('/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertOk()->assertJsonStructure(['token', 'persona']);
    }

    public function test_stateful_confirmation_requires_the_sanctum_csrf_cookie(): void
    {
        $headers = $this->registerWithSession();
        $this->app->instance('env', 'local');
        $link = $this->link();
        $this->postJson(self::CONFIRM, $link, $headers)->assertStatus(419)->assertJsonMissingPath('token');
        $this->assertDatabaseHas('persona', ['epasts' => self::EMAIL, 'email_verified_at' => null]);
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->resetRequestState();

        $csrf = $this->get('/sanctum/csrf-cookie', $headers)->assertNoContent();
        $this->resetRequestState();
        $this->postJson(self::CONFIRM, $link, $headers + ['X-XSRF-TOKEN' => $csrf->getCookie('XSRF-TOKEN', false)->getValue()])
            ->assertOk()->assertJsonStructure(['token', 'persona']);
    }

    public function test_a_link_cannot_verify_a_different_current_email(): void
    {
        $headers = $this->registerWithSession();
        $account = Persona::forEmail(self::EMAIL)->firstOrFail();
        $account->update(['epasts' => 'changed@example.com']);
        $this->postJson(self::CONFIRM, $this->link() + ['interactive' => true], $headers)
            ->assertUnprocessable()->assertJsonMissingPath('token');
        $this->assertNull($account->fresh()->email_verified_at);
        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    public function test_another_browser_must_confirm_explicitly_and_reset_the_unproven_password(): void
    {
        $this->registerWithSession();
        $this->resetRequestState();
        $this->defaultCookies = [];
        $link = $this->link();
        $this->postJson(self::CONFIRM, $link)->assertConflict()->assertJsonPath('code', 'verification_interaction_required')
            ->assertJsonMissingPath('token');
        $account = Persona::forEmail(self::EMAIL)->firstOrFail();
        $this->assertNull($account->email_verified_at);
        $this->assertDatabaseCount('email_verification_tokens', 1);
        $this->assertDatabaseEmpty('personal_access_tokens');

        $this->postJson(self::CONFIRM, $link + ['interactive' => true])->assertOk()
            ->assertJsonPath('password_reset_required', true)->assertJsonMissingPath('token');
        $this->assertNotNull($account->fresh()->email_verified_at);
        $this->postJson('/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])->assertUnprocessable();

        $password = 'MailboxOwner123!';
        $this->postJson('/api/auth/reset-password', [
            'email' => self::EMAIL, 'password' => $password, 'password_confirmation' => $password,
            'token' => Password::createToken($account->fresh()),
        ])->assertOk();
        $this->postJson('/api/auth/login', ['email' => self::EMAIL, 'password' => $password])->assertOk();
    }

    public function test_confirmation_leaves_other_bearer_and_google_web_sessions_signed_in(): void
    {
        $headers = $this->registerWithSession();
        $other = $this->account('other@example.com');
        $other->forceFill(['google_id' => 'other-google-subject', 'email_verified_at' => now()])->save();
        $bearer = $other->createToken('other-device')->plainTextToken;
        $session = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $session,
            'user_id' => $other->getKey(),
            'payload' => base64_encode(serialize([Auth::guard('web')->getName() => $other->getKey()])),
            'last_activity' => now()->timestamp,
        ]);
        // Preserve the signup proof in a browser that also has a different Sanctum identity.
        $link = $this->link();
        $this->postJson(self::CONFIRM, $link, $headers + ['Authorization' => 'Bearer '.$bearer])
            ->assertOk()->assertJsonPath('active_session', true)->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->resetRequestState();
        $this->getJson('/api/auth/me', $headers + ['Authorization' => 'Bearer '.$bearer])
            ->assertOk()->assertJsonPath('persona.epasts', $other->epasts);

        $this->resetRequestState();
        $this->withCredentials()->withCookie(config('session.cookie'), $session);
        $target = $this->account('target@example.com');
        $target->sendEmailVerificationNotification();
        $this->postJson(self::CONFIRM, $this->link(1) + ['interactive' => true], $headers)
            ->assertOk()->assertJsonPath('active_session', true)->assertJsonMissingPath('token');
        $this->resetRequestState();
        $this->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('persona.epasts', $other->epasts);
        $this->assertDatabaseHas('sessions', ['id' => $session, 'user_id' => $other->getKey()]);
    }

    public function test_a_bearer_token_cannot_bypass_verification_on_private_routes(): void
    {
        $persona = $this->account(self::EMAIL);
        $this->withToken($persona->createToken('stale')->plainTextToken);
        foreach ([
            ['GET', '/api/auth/me'],
            ['GET', '/api/profile/'.$persona->getKey()],
            ['PUT', '/api/profile/'.$persona->getKey()],
            ['GET', '/api/profile/deletion-check'],
            ['DELETE', '/api/profile/me'],
            ['POST', '/api/rezervacijas'],
            ['POST', '/api/rezervacijas/1/pay'],
            ['GET', '/api/rezervacijas/1/invoice'],
            ['POST', '/api/transport'],
            ['POST', '/api/atsauksmes'],
        ] as [$method, $uri]) {
            Auth::forgetGuards();
            $this->json($method, $uri)->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        }
        $this->getJson('/api/transport')->assertOk();
    }

    public function test_wrong_expired_deleted_and_cross_account_links_are_rejected(): void
    {
        $account = $this->account(self::EMAIL);
        $other = $this->account('other@example.com');
        $this->postJson(self::RESEND, ['email' => self::EMAIL])->assertAccepted();
        $link = $this->link();
        $wrong = ['token' => str_repeat('0', 64)] + $link;
        $this->postJson(self::CONFIRM, $wrong)->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_link');
        $this->postJson(self::CONFIRM, ['id' => $other->getKey()] + $link)->assertUnprocessable();
        $this->travel(24)->hours();
        $this->postJson(self::CONFIRM, $link)->assertGone()->assertJsonPath('code', 'verification_link_expired');
        $this->postJson(self::CONFIRM, $wrong)->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_link');
        $account->delete();
        $this->postJson(self::CONFIRM, $link)->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_link');
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_resend_is_private_rate_limited_and_invalidates_the_previous_link(): void
    {
        $this->postJson('/api/auth/register', $this->registration())->assertCreated()->assertJsonPath('mail_status', 'sent');
        $first = $this->link();
        $this->account('verified@example.com')->markEmailAsVerified();
        $this->account('deleted@example.com')->delete();
        $request = $this->postJson(self::RESEND, ['email' => strtoupper(self::EMAIL), 'locale' => 'ru'])->assertAccepted()->json();
        foreach (['missing@example.com', 'verified@example.com', 'deleted@example.com', self::EMAIL] as $email) {
            $this->postJson(self::RESEND, ['email' => $email, 'locale' => 'ru'])->assertAccepted()->assertExactJson($request);
        }
        $this->assertCount(1, $this->messages());
        $this->postJson(self::RESEND, ['email' => self::EMAIL])->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
        $this->travel(60)->seconds();
        $this->postJson(self::RESEND, ['email' => self::EMAIL, 'locale' => 'ru'])->assertAccepted()->assertExactJson($request);
        $this->assertCount(2, $this->messages());
        $second = $this->link(1);
        $this->assertSame('ru', $second['lang']);
        $this->assertNotSame($first['token'], $second['token']);
        $this->postJson(self::CONFIRM, $first)->assertUnprocessable();
        $this->postJson(self::CONFIRM, $second + ['interactive' => true])->assertOk();
    }

    public function test_transport_failure_preserves_registration_and_resend_is_available(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
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
        $response = $this->postJson('/api/auth/register', $this->registration())->assertCreated()->assertJsonPath('mail_status', 'unavailable');
        $this->assertDatabaseHas('persona', ['epasts' => self::EMAIL, 'email_verified_at' => null]);
        Log::shouldHaveReceived('log')->with('warning', 'Account email send result.', [
            'mail_type' => 'email_verification', 'recipient_domain' => 'example.com', 'resend_id' => null,
            'outcome' => 'error', 'error' => 'send_failed', 'request_id' => $response->headers->get('X-Request-ID'),
        ])->once();

        $this->travel(60)->seconds();
        config(['mail.default' => 'array']);
        $this->postJson(self::RESEND, ['email' => self::EMAIL])->assertAccepted();
        $this->postJson(self::CONFIRM, $this->link() + ['interactive' => true])->assertOk();
    }

    public function test_resend_in_the_signup_session_reports_acceptance_or_transport_failure_and_preserves_the_previous_link(): void
    {
        config([
            'session.driver' => 'database',
            'sanctum.stateful' => ['easyrent.vip'],
        ]);
        $headers = ['Referer' => self::FRONTEND.'/auth'];
        $register = $this->postJson('/api/auth/register', $this->registration(), $headers)
            ->assertCreated()->assertJsonPath('mail_status', 'sent');
        $first = $this->link();
        config(['turnstile.enabled' => true, 'turnstile.secret_key' => 'fixture-secret']);
        Http::fake([TurnstileService::SITEVERIFY => fn () => Http::response([
            'success' => true, 'action' => 'verification_resend', 'hostname' => 'easyrent.vip', 'challenge_ts' => now()->toISOString(),
        ])])->preventStrayRequests();
        $this->resetRequestState();
        $this->withCredentials()->withCookie(config('session.cookie'), $register->getCookie(config('session.cookie'))->getValue());
        $this->postJson(self::RESEND, ['email' => self::EMAIL], $headers)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->postJson(self::RESEND, ['email' => 'missing@example.com', 'turnstile_token' => 'fixture-missing'], $headers)->assertAccepted();
        $this->postJson(self::RESEND, ['email' => self::EMAIL, 'turnstile_token' => 'fixture-fast'], $headers)
            ->assertTooManyRequests()->assertJsonPath('code', 'verification_mail_rate_limited');

        $this->travel(60)->seconds();
        $this->postJson(self::RESEND, ['email' => self::EMAIL, 'turnstile_token' => 'fixture-resend'], $headers)
            ->assertOk()->assertJsonPath('code', 'verification_mail_sent');
        $recent = $this->link(1);
        $this->postJson(self::CONFIRM, $first, $headers)->assertUnprocessable();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();

        $this->travel(60)->seconds();
        Event::listen(\Illuminate\Mail\Events\MessageSending::class, fn () => false);
        $this->postJson(self::RESEND, ['email' => self::EMAIL, 'turnstile_token' => 'fixture-resend-error'], $headers)
            ->assertStatus(503)->assertHeader('Retry-After', '60')->assertJsonPath('code', 'mail_unavailable');
        $this->assertCount(2, $this->messages());
        $this->postJson(self::CONFIRM, $recent, $headers)->assertOk();
    }

    public function test_pending_login_grants_mail_feedback_to_the_current_session_only(): void
    {
        config(['session.driver' => 'database', 'sanctum.stateful' => ['easyrent.vip']]);
        $this->account(self::EMAIL);
        $headers = ['Referer' => self::FRONTEND.'/auth'];
        $login = $this->postJson('/api/auth/login', ['email' => self::EMAIL, 'password' => self::PASSWORD], $headers)
            ->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->resetRequestState();
        $this->withCredentials()->withCookie(config('session.cookie'), $login->getCookie(config('session.cookie'))->getValue());
        $this->postJson(self::RESEND, ['email' => self::EMAIL], $headers)->assertOk();
        $this->postJson(self::CONFIRM, $this->link(), $headers)->assertConflict()->assertJsonMissingPath('token');
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->resetRequestState();
        $this->postJson(self::RESEND, ['email' => self::EMAIL])->assertAccepted()->assertJsonPath('code', 'verification_requested');
    }

    public static function recipientDomains(): array
    {
        return [['gmail.com'], ['inbox.lv'], ['outlook.com'], ['rvt.lv']];
    }

    #[DataProvider('recipientDomains')]
    public function test_resend_transport_uses_the_normalized_address_and_safely_logs_the_provider_id(string $domain): void
    {
        $providerId = '49a862af-016a-45a8-8f72-c18eefb7f503';
        $requests = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => $providerId])),
        ]));
        $handler->push(Middleware::history($requests));
        $resend = new ResendClient(new HttpTransporter(
            new Client(['handler' => $handler]),
            BaseUri::from('api.resend.com'),
            Headers::withAuthorization(ApiKey::from('re_test_key')),
        ));
        config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
        Mail::extend('resend', fn () => new ResendTransport($resend));
        Log::spy();

        $email = 'renter+qa@'.$domain;
        $response = $this->postJson('/api/auth/register', ['email' => ' '.strtoupper($email).' '] + $this->registration())
            ->assertCreated()->assertJsonPath('mail_status', 'sent')->assertJsonMissingPath('resend_id');
        $this->assertCount(1, $requests);
        $request = $requests[0]['request'];
        $this->assertSame('https://api.resend.com/emails', (string) $request->getUri());
        $message = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$email], $message['to']);
        $this->assertStringContainsString(self::FRONTEND.'/verify-email#id=', $message['text']);
        $this->assertStringContainsString(self::FRONTEND.'/verify-email#id=', $message['html']);
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        Log::shouldHaveReceived('log')->with('info', 'Account email send result.', [
            'mail_type' => 'email_verification', 'recipient_domain' => $domain, 'resend_id' => $providerId,
            'outcome' => 'accepted', 'error' => null, 'request_id' => $response->headers->get('X-Request-ID'),
        ])->once();
    }

    public function test_password_reset_does_not_verify_email_or_bypass_login_gate(): void
    {
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $persona = Persona::forEmail(self::EMAIL)->firstOrFail();
        $credentials = ['email' => self::EMAIL, 'password' => 'NewPassword123!'];
        $this->postJson('/api/auth/reset-password', $credentials + [
            'password_confirmation' => $credentials['password'],
            'token' => Password::createToken($persona),
        ])->assertOk();
        $this->assertNull($persona->fresh()->email_verified_at);
        $this->postJson('/api/auth/login', $credentials)->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->postJson(self::CONFIRM, $this->link() + ['interactive' => true])->assertOk()
            ->assertJsonPath('password_reset_required', false)->assertJsonMissingPath('token');
        $this->postJson('/api/auth/login', $credentials)->assertOk();
    }

    public function test_migration_grandfathers_all_existing_rows_and_leaves_new_rows_pending(): void
    {
        $default = DB::getDefaultConnection();
        config(['database.connections.verification_migration' => ['database' => ':memory:', 'url' => null] + config('database.connections.sqlite')]);
        DB::setDefaultConnection('verification_migration');
        Schema::clearResolvedInstance('db.schema');
        try {
            Schema::create('persona', function (Blueprint $table) {
                $table->id('persona_id');
                $table->string('epasts')->nullable();
                $table->softDeletes();
            });
            $legacy = [
                ['epasts' => self::EMAIL, 'deleted_at' => null],
                ['epasts' => null, 'deleted_at' => null],
                ['epasts' => 'deleted@example.com', 'deleted_at' => now()],
            ];
            DB::table('persona')->insert($legacy);
            $migration = require database_path('migrations/2026_11_23_000006_add_persona_email_verification.php');
            $migration->up();
            $this->assertSame(3, DB::table('persona')->whereNotNull('email_verified_at')->count());
            $this->assertSame(1, DB::table('persona')->whereNotNull('deleted_at')->count());
            $id = DB::table('persona')->insertGetId(['epasts' => 'fresh@example.com'], 'persona_id');
            $this->assertNull(DB::table('persona')->where('persona_id', $id)->value('email_verified_at'));
        } finally {
            DB::purge('verification_migration');
            DB::setDefaultConnection($default);
            Schema::clearResolvedInstance('db.schema');
        }
    }

    public function test_block_invalidates_verification_link_and_prevents_automatic_login(): void
    {
        $headers = $this->registerWithSession();
        $link = $this->link();
        $persona = Persona::forEmail(self::EMAIL)->firstOrFail();
        $admin = Persona::forceCreate(['loma' => 'administrators', 'epasts' => 'admin@example.test', 'email_verified_at' => now()]);
        $token = $admin->createToken('admin-test')->plainTextToken;
        $this->postJson('/api/admin/users/'.$persona->getKey().'/restrictions', [
            'veids' => 'konta_blokesana', 'duration' => 'indefinite', 'iemesls' => 'Account review',
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();

        $this->resetRequestState();
        $this->postJson(self::CONFIRM, $link + ['interactive' => true, 'auto_login' => true], $headers)
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_link')->assertJsonMissingPath('token');
        $this->assertFalse($persona->fresh()->hasVerifiedEmail());
        $this->assertDatabaseEmpty('email_verification_tokens');
        $this->assertSame(0, $persona->tokens()->count());
    }

    private function registration(): array
    {
        return [
            'name' => 'QA Renter', 'email' => self::EMAIL, 'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD, 'loma' => 'klients', 'lietotajvards' => 'qatest',
            'kontakttalrunis' => '+37120000000', 'bankas_konts' => 'LV80BANK0000435195001',
        ];
    }

    private function registerWithSession(array $extra = []): array
    {
        config(['session.driver' => 'database', 'sanctum.stateful' => ['easyrent.vip']]);
        $headers = ['Referer' => self::FRONTEND.'/auth'];
        $response = $this->postJson('/api/auth/register', $this->registration() + $extra, $headers)->assertCreated();
        $this->resetRequestState();
        $this->withCredentials()->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());

        return $headers;
    }

    private function account(string $email): Persona
    {
        return Persona::create(['epasts' => $email, 'loma' => 'klients', 'parole' => Hash::make(self::PASSWORD)]);
    }

    private function messages(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->all();
    }

    private function link(int $index = 0): array
    {
        $mail = $this->messages()[$index]->getOriginalMessage();
        preg_match('~https://easyrent\.vip/verify-email#([^\s]+)~', $mail->getTextBody(), $parts);
        $this->assertNotEmpty($parts);
        $this->assertStringContainsString(e($parts[0]), $mail->getHtmlBody());
        parse_str($parts[1], $fragment);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fragment['token']);

        return $fragment;
    }

    private function resetRequestState(): void
    {
        Auth::forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
