<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const CONTACTS = [
        'kontakttalrunis' => '+371 20000000',
        'bankas_konts' => 'LV80BANK0000435195001',
    ];

    private array $browsers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => 'https://easyrent.vip',
            'session.driver' => 'database',
            'session.domain' => null,
            'session.secure' => false,
            'sanctum.stateful' => ['localhost:3000'],
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost:3000/oauth/google/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_new_client_is_authenticated_and_can_only_use_profile_until_it_is_complete(): void
    {
        $this->fakeGoogle();
        $start = $this->start('client', 'klients');
        $this->assertSame('openid email profile', $start['scope']);
        $this->assertSame('S256', $start['code_challenge_method']);
        $this->assertSame('select_account', $start['prompt']);
        $this->assertArrayNotHasKey('client_secret', $start);

        $this->finishGoogle('client', $start)->assertRedirect('/profile?google=ok')->assertSessionMissing('google_oauth.flow');
        Http::assertSent(fn (Request $request) => $request->url() === self::TOKEN_URL
            && $request['client_secret'] === 'test-secret'
            && $start['code_challenge'] === rtrim(strtr(base64_encode(hash('sha256', $request['code_verifier'], true)), '+/', '-_'), '='));
        Http::assertSent(fn (Request $request) => $request->url() === self::USERINFO_URL
            && $request->hasHeader('Authorization', 'Bearer test-access-token'));

        $account = $this->browser('client', 'GET', '/api/auth/me')->assertOk()
            ->assertJsonPath('persona.vards', 'Anna')
            ->assertJsonPath('persona.uzvards', 'Ozola')
            ->assertJsonPath('persona.epasts', 'anna@example.com')
            ->assertJsonPath('persona.profile_completed', false)
            ->assertJsonMissingPath('persona.google_id')
            ->assertJsonMissingPath('persona.parole')
            ->assertJsonMissingPath('token');
        $id = $account->json('persona.persona_id');
        $this->assertEqualsCanonicalizing(['kontakttalrunis', 'bankas_konts', 'lietotajvards'], $account->json('persona.profile_missing_fields'));
        $this->assertDatabaseHas('persona', ['persona_id' => $id, 'google_id' => '123456', 'parole' => null]);
        $this->assertTrue(Persona::findOrFail($id)->hasVerifiedEmail());
        $this->assertDatabaseEmpty('email_verification_tokens');
        $this->assertDatabaseHas('klients', ['persona_id' => $id, 'lietotajvards' => null]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        foreach ([
            ['GET', '/api/transport'], ['GET', '/api/transport/veidi'], ['GET', '/api/atsauksmes'],
            ['GET', '/api/rezervacijas'], ['POST', '/api/rezervacijas'], ['POST', '/api/transport'],
        ] as [$method, $uri]) {
            $this->browser('client', $method, $uri)->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
        }
        $this->browser('client', 'GET', '/api/transport', referer: false)->assertForbidden();
        $this->browser('client', 'GET', '/api/profile/'.$id)->assertOk();
        $this->browser('client', 'GET', '/api/profile/'.($id + 1))->assertForbidden();

        $this->app->instance('env', 'local');
        $this->browser('client', 'PUT', '/api/profile/'.$id, self::CONTACTS, csrf: false)->assertStatus(419);
        $this->app->instance('env', 'testing');

        $this->browser('client', 'PUT', '/api/profile/'.$id, ['profile_completed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['kontakttalrunis', 'bankas_konts', 'lietotajvards']);
        $this->browser('client', 'PUT', '/api/profile/'.$id, [
            'kontakttalrunis' => 'foobar',
            'bankas_konts' => 'LV00BANK0000435195001',
            'lietotajvards' => 'a',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kontakttalrunis', 'bankas_konts', 'lietotajvards']);

        $this->browser('client', 'PUT', '/api/profile/'.$id, self::CONTACTS + ['lietotajvards' => 'annaozola'])
            ->assertOk()->assertJsonPath('persona.profile_completed', true)
            ->assertJsonPath('persona.klients.lietotajvards', 'annaozola');
        $this->browser('client', 'GET', '/api/transport')->assertOk();
        $this->browser('client', 'GET', '/api/rezervacijas')->assertOk();
        $this->browser('client', 'PUT', '/api/profile/'.$id, ['bankas_konts' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('bankas_konts');

        $this->browser('client', 'POST', '/api/auth/logout')->assertOk();
        $this->browser('client', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->finishGoogle('client', $this->start('client', 'pakalpojumu_sniedzejs'))->assertRedirect('/profile?google=ok');
        $this->browser('client', 'GET', '/api/auth/me')->assertJsonPath('persona.persona_id', $id)
            ->assertJsonPath('persona.loma', 'klients')->assertJsonPath('persona.profile_completed', true);
        $this->assertDatabaseCount('persona', 1);
    }

    public function test_provider_keeps_the_same_incomplete_profile_after_logout_and_can_finish_without_coordinates(): void
    {
        $this->fakeGoogle();
        $this->finishGoogle('first', $this->start('first', 'pakalpojumu_sniedzejs'))->assertRedirect('/profile?google=ok');
        $id = $this->browser('first', 'GET', '/api/auth/me')
            ->assertJsonPath('persona.loma', 'pakalpojumu_sniedzejs')
            ->assertJsonPath('persona.profile_completed', false)->json('persona.persona_id');
        $this->assertDatabaseHas('pakalpojumu_sniedzejs', ['persona_id' => $id, 'iela' => null]);
        $this->browser('first', 'POST', '/api/auth/logout')->assertOk();
        $this->browser('first', 'GET', '/api/auth/me')->assertUnauthorized();

        $this->finishGoogle('second', $this->start('second'))->assertRedirect('/profile?google=ok');
        $this->browser('second', 'GET', '/api/auth/me')->assertJsonPath('persona.persona_id', $id)
            ->assertJsonPath('persona.profile_completed', false);
        $this->browser('second', 'PUT', '/api/profile/'.$id, self::CONTACTS)
            ->assertUnprocessable()->assertJsonValidationErrors(['registracijas_numurs', 'iela', 'majas_numurs', 'pilseta', 'pasta_indekss']);
        $this->browser('second', 'PUT', '/api/profile/'.$id, self::CONTACTS + [
            'registracijas_numurs' => 'REG-123',
            'iela' => 'Brīvības iela',
            'majas_numurs' => '10',
            'pilseta' => 'Rīga',
            'pasta_indekss' => 'LV-1010',
        ])->assertOk()->assertJsonPath('persona.profile_completed', true)
            ->assertJsonPath('persona.pakalpojumu_sniedzejs.iela', 'Brīvības iela');
        $this->assertDatabaseCount('persona', 1);
        $this->assertDatabaseCount('pakalpojumu_sniedzejs', 1);
    }

    public function test_role_is_selected_in_profile_and_missing_google_data_can_be_entered_manually(): void
    {
        $this->fakeGoogle(['sub' => '67890', 'email' => 'not-verified@example.com', 'email_verified' => false]);
        $this->finishGoogle('new', $this->start('new'))->assertRedirect('/profile?google=role');
        $this->assertDatabaseCount('persona', 0);
        $this->browser('new', 'GET', '/api/auth/me')->assertOk()
            ->assertJsonPath('persona', null)->assertJsonPath('google_pending.purpose', 'role')
            ->assertJsonPath('google_pending.epasts', null);
        $this->browser('new', 'GET', '/api/transport')->assertForbidden();
        $this->browser('new', 'POST', '/oauth/google/role', ['loma' => 'administrators'])->assertUnprocessable();

        $this->app->instance('env', 'local');
        $this->browser('new', 'POST', '/oauth/google/role', ['loma' => 'klients'], csrf: false)->assertStatus(419);
        $this->browser('new', 'POST', '/oauth/google/role', ['loma' => 'klients'])
            ->assertOk()->assertJsonPath('next', '/profile?google=ok');
        $this->app->instance('env', 'testing');

        $id = $this->browser('new', 'GET', '/api/auth/me')->assertJsonPath('persona.vards', null)
            ->assertJsonPath('persona.epasts', null)->json('persona.persona_id');
        $this->assertFalse(Persona::findOrFail($id)->hasVerifiedEmail());
        $this->browser('new', 'GET', '/api/profile/deletion-check')->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->browser('new', 'GET', '/api/rezervacijas')->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->browser('new', 'PUT', '/api/profile/'.$id, self::CONTACTS + [
            'vards' => 'Jānis',
            'uzvards' => 'Kalniņš',
            'epasts' => 'janis@example.com',
            'lietotajvards' => 'janisk',
        ])->assertOk()->assertJsonPath('code', 'verification_required')->assertJsonPath('mail_status', 'sent');
        $this->assertDatabaseHas('persona', ['persona_id' => $id, 'email_verified_at' => null, 'profile_completed' => true]);
        $this->browser('new', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->finishGoogle('new', $this->start('new'))->assertRedirect('/verify-email');
        $this->browser('new', 'GET', '/api/auth/me')->assertUnauthorized();

        $message = Mail::mailer('array')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        preg_match('/verify-email#([^\s]+)/', $message->getTextBody(), $fragment);
        parse_str($fragment[1], $link);
        $this->postJson('/api/auth/verification/confirm', $link)->assertOk()->assertJsonPath('code', 'email_verified');
        $this->browser('new', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->finishGoogle('new', $this->start('new'))->assertRedirect('/profile?google=ok');
        $this->browser('new', 'GET', '/api/auth/me')->assertJsonPath('persona.profile_completed', true);
        $this->browser('new', 'PUT', '/api/profile/'.$id, ['epasts' => 'new@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('epasts');
        $this->browser('new', 'POST', '/oauth/google/role', ['loma' => 'klients'])->assertStatus(410);
    }

    public function test_email_collision_requires_the_existing_accounts_password_and_preserves_its_role(): void
    {
        $password = 'Existing123!';
        $existing = Persona::create([
            'vards' => 'Esošs',
            'uzvards' => 'Klients',
            'epasts' => 'Anna@Example.com',
            'loma' => 'klients',
            'parole' => Hash::make($password),
        ]);
        $existing->klients()->create(['lietotajvards' => 'existing']);
        $this->fakeGoogle();
        $this->finishGoogle('link', $this->start('link', 'pakalpojumu_sniedzejs'))->assertRedirect('/auth?google=link');
        $this->browser('link', 'GET', '/api/auth/me')->assertJsonPath('persona', null)
            ->assertJsonPath('google_pending.purpose', 'link');
        $this->browser('link', 'POST', '/oauth/google/role', ['loma' => 'klients'])->assertStatus(410);
        $this->browser('link', 'POST', '/oauth/google/link', ['password' => 'wrong'])->assertUnprocessable();
        $this->assertNull($existing->fresh()->google_id);
        $this->assertFalse($existing->fresh()->hasVerifiedEmail());
        $this->browser('visitor', 'POST', '/oauth/google/link', ['password' => $password])->assertStatus(410);

        $this->browser('link', 'POST', '/oauth/google/link', ['password' => $password])->assertOk()
            ->assertJsonPath('persona.persona_id', $existing->getKey())->assertJsonPath('persona.loma', 'klients')
            ->assertJsonPath('persona.profile_completed', true);
        $this->assertSame('123456', $existing->fresh()->google_id);
        $this->assertTrue($existing->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('persona', 1);
        $this->assertDatabaseCount('pakalpojumu_sniedzejs', 0);
        $this->browser('link', 'GET', '/api/transport')->assertOk();

        $this->browser('password', 'POST', '/api/auth/login', [
            'email' => 'anna@example.com', 'password' => $password,
        ])->assertOk()->assertJsonPath('persona.persona_id', $existing->getKey());
    }

    public function test_returning_google_account_can_use_a_newly_verified_unclaimed_email(): void
    {
        $owner = Persona::create(['epasts' => 'claimed@example.com', 'loma' => 'klients']);
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-access-token']),
            self::USERINFO_URL => Http::sequence()
                ->push(['sub' => '123456', 'email' => 'anna@example.com', 'email_verified' => false])
                ->push(['sub' => '123456', 'email' => $owner->epasts, 'email_verified' => true])
                ->push(['sub' => '123456', 'email' => 'anna@example.com', 'email_verified' => true]),
        ]);
        $this->finishGoogle('browser', $this->start('browser', 'klients'))->assertRedirect('/profile?google=ok');
        $account = Persona::where('google_id', '123456')->firstOrFail();
        $this->assertFalse($account->hasVerifiedEmail());

        $this->finishGoogle('browser', $this->start('browser'))->assertRedirect('/profile?google=ok');
        $this->assertNull($account->fresh()->epasts);
        $this->assertNull($owner->fresh()->google_id);

        $this->finishGoogle('browser', $this->start('browser'))->assertRedirect('/profile?google=ok');
        $this->assertSame('anna@example.com', $account->fresh()->epasts);
        $this->assertTrue($account->fresh()->hasVerifiedEmail());
        $this->assertDatabaseEmpty('email_verification_tokens');
    }

    public function test_overlapping_authorizations_reuse_the_unique_google_account(): void
    {
        $this->fakeGoogle();
        $first = $this->start('first', 'klients');
        $second = $this->start('second', 'pakalpojumu_sniedzejs');
        $this->assertNotSame($first['state'], $second['state']);

        $this->finishGoogle('first', $first)->assertRedirect('/profile?google=ok');
        $this->finishGoogle('second', $second)->assertRedirect('/profile?google=ok');
        $this->finishGoogle('second', $second)->assertRedirect('/auth?google=invalid');
        $this->assertDatabaseCount('persona', 1);
        $this->assertDatabaseCount('klients', 1);
        $this->assertDatabaseCount('pakalpojumu_sniedzejs', 0);
        $this->browser('second', 'GET', '/api/auth/me')->assertJsonPath('persona.loma', 'klients');

        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate = new Persona(['loma' => 'klients', 'epasts' => 'different@example.com']);
        $duplicate->forceFill(['google_id' => '123456'])->save();
    }

    public function test_switching_google_accounts_rotates_the_existing_web_session(): void
    {
        $existing = Persona::create([
            'vards' => 'Pirmais',
            'epasts' => 'first@example.com',
            'loma' => 'klients',
            'parole' => Hash::make('First123!'),
        ]);
        $existing->forceFill(['google_id' => 'first-subject', 'email_verified_at' => now()])->save();
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-access-token']),
            self::USERINFO_URL => Http::sequence()
                ->push(['sub' => 'first-subject'])
                ->push(['sub' => 'second-subject', 'given_name' => 'Otrais']),
        ]);

        $this->finishGoogle('browser', $this->start('browser'))->assertRedirect('/profile?google=ok');
        $this->browser('browser', 'GET', '/api/auth/me')->assertOk()
            ->assertJsonPath('persona.persona_id', $existing->getKey());
        $this->finishGoogle('browser', $this->start('browser', 'klients'))->assertRedirect('/profile?google=ok');
        $this->browser('browser', 'GET', '/api/auth/me')->assertOk()
            ->assertJsonPath('persona.vards', 'Otrais')->assertJsonPath('persona.profile_completed', false);
        $this->assertDatabaseCount('persona', 2);
    }

    public function test_invalid_expired_or_cancelled_callbacks_do_not_contact_google(): void
    {
        Http::fake();
        $start = $this->start('browser');
        $this->finishGoogle('another-browser', $start)->assertRedirect('/auth?google=invalid');
        $this->finishGoogle('browser', $start, ['code' => null, 'error' => 'access_denied'])->assertRedirect('/auth?google=cancelled');
        $this->finishGoogle('browser', $start)->assertRedirect('/auth?google=invalid');
        $this->finishGoogle('browser', $this->start('browser'), ['state' => ['invalid']])->assertRedirect('/auth?google=invalid');
        $expired = $this->start('browser');
        $this->travel(11)->minutes();
        $this->finishGoogle('browser', $expired)->assertRedirect('/auth?google=invalid');
        Http::assertNothingSent();
        $this->assertDatabaseCount('persona', 0);
    }

    public function test_provider_failure_is_reported_without_creating_an_account(): void
    {
        Http::fake([self::TOKEN_URL => Http::failedConnection()]);
        $this->finishGoogle('browser', $this->start('browser', 'klients'))->assertRedirect('/auth?google=failed');
        $this->browser('browser', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->assertDatabaseCount('persona', 0);
    }

    public function test_google_consent_screen_uses_the_selected_language(): void
    {
        foreach (['lv', 'ru', 'en'] as $language) {
            $response = $this->browser('language', 'GET', '/oauth/google/start?lang='.$language)->assertRedirect();
            parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
            $this->assertSame($language, $parameters['hl']);
        }
    }

    public function test_incomplete_google_account_requires_fresh_oauth_and_can_register_again_after_deletion(): void
    {
        $this->fakeGoogle();
        $this->finishGoogle('first', $this->start('first', 'klients'))->assertRedirect('/profile?google=ok');
        $accountId = $this->browser('first', 'GET', '/api/auth/me')->json('persona.persona_id');
        $this->finishGoogle('second', $this->start('second'))->assertRedirect('/profile?google=ok');

        $check = '/api/profile/deletion-check';
        $this->browser('first', 'GET', $check)->assertOk()->assertJsonPath('confirmation_method', 'google')
            ->assertJsonPath('google_confirmed', false);
        $this->browser('first', 'DELETE', '/api/profile/me', ['confirmation' => 'DZĒST'])
            ->assertUnprocessable()->assertJsonValidationErrors('google');
        $parameters = $this->startGoogleDeletion('first');
        $this->assertSame('select_account consent', $parameters['prompt']);
        $this->finishGoogle('first', $parameters)->assertRedirect('/profile?delete_google=ok');
        $this->browser('first', 'GET', $check)->assertJsonPath('google_confirmed', true);
        $this->browser('second', 'GET', $check)->assertJsonPath('google_confirmed', false);
        $this->browser('first', 'DELETE', '/api/profile/me', ['confirmation' => 'DZĒST'])->assertNoContent();
        $this->browser('first', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->browser('second', 'GET', '/api/auth/me')->assertUnauthorized();
        $this->assertDatabaseMissing('sessions', ['user_id' => $accountId]);
        $this->assertDatabaseHas('persona', ['persona_id' => $accountId, 'google_id' => null, 'epasts' => null]);

        $this->finishGoogle('first', $this->start('first'))->assertRedirect('/profile?google=role');
        $this->browser('first', 'GET', '/api/auth/me')->assertJsonPath('persona', null);
        $this->assertDatabaseCount('persona', 1);
        $this->browser('first', 'POST', '/oauth/google/role', ['loma' => 'klients'])->assertOk();
        $newId = $this->browser('first', 'GET', '/api/auth/me')->assertOk()->json('persona.persona_id');
        $this->assertNotEquals($accountId, $newId);
        $this->assertDatabaseCount('persona', 2);
    }

    public function test_google_deletion_rejects_another_subject_and_expired_proof(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-access-token']),
            self::USERINFO_URL => Http::sequence()
                ->push(['sub' => 'first-subject', 'email' => 'first@example.com', 'email_verified' => true])
                ->push(['sub' => 'other-subject'])
                ->push(['sub' => 'first-subject']),
        ]);
        $this->finishGoogle('browser', $this->start('browser', 'klients'))->assertRedirect('/profile?google=ok');
        $this->finishGoogle('browser', $this->startGoogleDeletion('browser'))->assertRedirect('/profile?delete_google=mismatch');
        $this->browser('browser', 'GET', '/api/profile/deletion-check')->assertJsonPath('google_confirmed', false);
        $this->assertDatabaseCount('persona', 1);

        $this->finishGoogle('browser', $this->startGoogleDeletion('browser'))->assertRedirect('/profile?delete_google=ok');
        $this->travel(5)->minutes();
        $this->browser('browser', 'DELETE', '/api/profile/me', ['confirmation' => 'DZĒST'])
            ->assertUnprocessable()->assertJsonValidationErrors('google');
        $this->browser('browser', 'GET', '/api/auth/me')->assertOk();
    }

    private function startGoogleDeletion(string $browser): array
    {
        $url = $this->browser($browser, 'POST', '/oauth/google/deletion')->assertOk()->json('authorization_url');
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);

        return $parameters;
    }

    private function fakeGoogle(?array $identity = null): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'test-access-token']),
            self::USERINFO_URL => Http::response($identity ?? [
                'sub' => '123456',
                'given_name' => 'Anna',
                'family_name' => 'Ozola',
                'email' => 'anna@example.com',
                'email_verified' => true,
            ]),
        ]);
    }

    private function start(string $browser, ?string $role = null): array
    {
        $redirect = $this->browser($browser, 'GET', '/oauth/google/start'.($role ? '?role='.$role : ''))->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect->headers->get('Location'));
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $parameters);

        return $parameters;
    }

    private function finishGoogle(string $browser, array $parameters, array $overrides = []): TestResponse
    {
        return $this->browser($browser, 'GET', '/oauth/google/callback?'.http_build_query($overrides + [
            'code' => 'test-code',
            'state' => $parameters['state'],
        ]), referer: false);
    }

    private function browser(string $browser, string $method, string $path, array $data = [], bool $referer = true, bool $csrf = true): TestResponse
    {
        // Refresh request-scoped guards and the session store, as a real browser request would.
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $cookies = $this->browsers[$browser] ?? [];
        $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if ($referer) {
            $headers['HTTP_REFERER'] = 'http://localhost:3000/profile';
        }
        if ($csrf && isset($cookies['XSRF-TOKEN'])) {
            $headers['HTTP_X_XSRF_TOKEN'] = $cookies['XSRF-TOKEN'];
        }

        $response = $this->call($method, $path, [], $cookies, [], $headers, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $this->browsers[$browser][$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }
}
