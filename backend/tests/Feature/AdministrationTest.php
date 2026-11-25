<?php

namespace Tests\Feature;

use App\Models\Atsauksme;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\Persona;
use App\Models\TransportliedzieklsVeids;
use App\Models\Transportlidzeklis;
use App\Support\AccountMail;
use App\Support\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'SampleAdmin42!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => 'https://easyrent.example',
            'app.support_email' => 'support@easyrent.example',
        ]);
    }

    public function test_admin_apis_require_verified_admin_and_public_role_elevation_is_rejected(): void
    {
        $this->get('/api/admin/dashboard')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->post('/api/transport/veidi')->assertUnauthorized()->assertJsonStructure(['message']);
        foreach (['klients', 'pakalpojumu_sniedzejs'] as $role) {
            $user = $this->account($role);
            foreach (['dashboard', 'users', 'vehicles', 'categories', 'bookings', 'payments', 'reviews', 'reports', 'audit'] as $path) {
                $this->api($user, 'GET', '/api/admin/'.$path)->assertForbidden();
            }
            $this->api($user, 'POST', '/api/transport/veidi', ['nosaukums' => 'Auto', 'iemesls' => 'Test'])->assertForbidden();
            $this->api($user, 'PUT', '/api/profile/'.$user->getKey(), ['loma' => 'administrators'])
                ->assertUnprocessable()->assertJsonValidationErrors('loma');
            $this->assertSame($role, $user->fresh()->loma);
        }

        $this->api($this->account('administrators', false), 'GET', '/api/admin/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $admin = $this->account('administrators');
        $this->api($admin, 'GET', '/api/admin/dashboard?days=7')->assertOk()->assertJsonCount(7, 'series');
        $this->api($admin, 'GET', '/api/admin/dashboard?days=90')->assertOk()->assertJsonCount(90, 'series');
        $this->api($admin, 'GET', '/api/admin/dashboard?days=300')->assertUnprocessable();
        $this->api(null, 'POST', '/api/auth/register', [
            'email' => 'public@example.test', 'name' => 'Public Admin', 'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD, 'loma' => 'administrators',
            'kontakttalrunis' => '+371 20000000', 'bankas_konts' => 'LV80BANK0000435195001',
        ])->assertUnprocessable()->assertJsonValidationErrors('loma');
        $this->assertDatabaseMissing('persona', ['epasts' => 'public@example.test']);
    }

    public function test_safe_read_models_filter_sort_and_paginate_real_data(): void
    {
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $bookingId = $this->book($client, $vehicle)->assertCreated()->json('rezervacija_id');
        $payment = $this->api($client, 'POST', "/api/rezervacijas/$bookingId/pay")->assertOk()->json('maksajums');

        $users = $this->api($admin, 'GET', '/api/admin/users?verified=1&status=active&role=klients&sort=epasts&direction=asc&per_page=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.persona_id', $client->getKey())
            ->assertJsonMissingPath('data.0.bankas_konts')->assertJsonMissingPath('data.0.parole')
            ->assertJsonMissingPath('data.0.google_id')->assertJsonMissingPath('data.0.session_generation');
        $this->assertNotEmpty($users->json('data.0.verified_at'));
        $this->api($admin, 'GET', '/api/admin/users?q='.urlencode($client->epasts))
            ->assertOk()->assertJsonPath('total', 1);
        $this->api($admin, 'GET', '/api/admin/users?q=Test+Klients')->assertOk()->assertJsonPath('total', 1);
        $this->api($admin, 'GET', '/api/admin/users/'.$owner->getKey())
            ->assertOk()->assertJsonPath('vehicles', 1)->assertJsonPath('bookings', 1)->assertJsonMissingPath('user.bankas_konts');
        $this->api($admin, 'GET', '/api/admin/bookings?status=apmaksata&persona_id='.$owner->getKey())
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.klients.persona.persona_id', $client->getKey())
            ->assertJsonPath('data.0.maksajumi.0.rekins', $payment['rekins'])
            ->assertJsonMissingPath('data.0.klients.persona.epasts');
        $this->api($admin, 'GET', '/api/admin/payments?status=apstiprinats&booking_id='.$bookingId)
            ->assertOk()->assertJsonPath('data.0.tranzakcijas_numurs', $payment['tranzakcijas_numurs']);
        $this->api($admin, 'PATCH', '/api/admin/payments/'.$payment['maksajums_id'], ['summa' => 0])->assertNotFound();
        $dashboard = $this->api($admin, 'GET', '/api/admin/dashboard?days=30')->assertOk();
        $this->assertEquals($payment['summa'], $dashboard->json('confirmed_payment_volume'));
        $this->assertEquals(1, array_sum(array_column($dashboard->json('series'), 'paid_bookings')));
    }

    public function test_timed_indefinite_cancelled_and_expired_restrictions_apply_at_real_write_endpoints(): void
    {
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $bookingId = $this->book($client, $vehicle)->assertCreated()->json('rezervacija_id');
        $bookingBan = $this->restrict($admin, $client, Restriction::BOOKING, '24h')->assertCreated();
        $this->book($client, $vehicle, 5)->assertForbidden()->assertJsonPath('code', 'action_restricted');
        $this->api($client, 'POST', "/api/rezervacijas/$bookingId/pay")->assertForbidden();
        $this->api($admin, 'GET', '/api/admin/users?restriction='.Restriction::BOOKING)->assertOk()->assertJsonPath('total', 1);

        $this->travel(25)->hours();
        $this->book($client, $vehicle, 5)->assertCreated();
        $this->api($admin, 'GET', '/api/admin/users/'.$client->getKey())->assertOk()
            ->assertJsonPath('restrictions.data.0.active', false);
        $this->assertDatabaseHas('lietotaja_ierobezojums', [
            'ierobezojums_id' => $bookingBan->json('ierobezojums_id'), 'atcelts_at' => null,
        ]);

        $reviewBody = ['transportlidzeklis_id' => $vehicle->getKey(), 'vertejums' => 5];
        $review = $this->api($client, 'POST', '/api/atsauksmes', $reviewBody)->assertCreated()->json('atsauksme.atsauksme_id');
        $ban = $this->restrict($admin, $client, Restriction::REVIEW)->assertCreated()->assertJsonPath('beigu_laiks', null)->json('ierobezojums_id');
        $this->api($client, 'POST', '/api/atsauksmes', $reviewBody)->assertForbidden();
        $this->api($client, 'PUT', '/api/atsauksmes/'.$review, ['vertejums' => 4])->assertForbidden();
        $revokePath = '/api/admin/users/'.$client->getKey().'/restrictions/'.$ban;
        $this->api($admin, 'PATCH', $revokePath)->assertUnprocessable();
        $this->api($admin, 'PATCH', $revokePath, ['iemesls' => 'Corrective action verified'])->assertOk();
        $this->api($client, 'PUT', '/api/atsauksmes/'.$review, ['vertejums' => 4])->assertOk();

        $vehicle->update(['statuss' => 'neaktivs']);
        $this->restrict($admin, $owner, Restriction::LISTING)->assertCreated();
        $this->api($owner, 'POST', '/api/transport', $this->listing($vehicle))->assertForbidden();
        $this->api($owner, 'PUT', '/api/transport/'.$vehicle->getKey(), ['statuss' => 'pieejams'])->assertForbidden();
        $this->api($owner, 'PUT', '/api/transport/'.$vehicle->getKey(), ['marka' => 'Updated'])->assertOk();
        $this->api($admin, 'GET', '/api/admin/audit')->assertOk()->assertJsonPath('total', 4);
    }

    public function test_custom_expiry_accepts_offsets_and_the_last_verified_active_admin_is_protected(): void
    {
        $admin = $this->account('administrators');
        $second = $this->account('administrators', false);
        $end = now()->addHour()->setTimezone('Europe/Riga');
        $this->restrict($admin, $admin, Restriction::BLOCK)->assertConflict();
        $this->api($admin, 'DELETE', '/api/profile/me', ['confirmation' => 'DZĒST', 'password' => self::PASSWORD])->assertConflict();
        $this->api($admin, 'PUT', '/api/profile/'.$admin->getKey(), ['loma' => 'klients'])->assertUnprocessable();
        $second->markEmailAsVerified();

        $custom = $this->api($admin, 'POST', '/api/admin/users/'.$second->getKey().'/restrictions', [
            'veids' => Restriction::BLOCK, 'duration' => 'custom', 'beigu_laiks' => $end->format(DATE_ATOM), 'iemesls' => 'Review required',
        ])->assertCreated();
        $this->assertEquals($end->timestamp, \Carbon\Carbon::parse($custom->json('beigu_laiks'))->timestamp);
        $this->restrict($admin, $admin, Restriction::BLOCK)->assertConflict();
        $this->travel(61)->minutes();
        $this->restrict($admin, $admin, Restriction::BLOCK)->assertCreated();
        $this->restrict($second, $second, Restriction::BLOCK)->assertConflict();
    }

    public function test_block_revokes_tokens_sessions_and_password_reset_but_paid_documents_remain_available(): void
    {
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $bookingId = $this->book($client, $vehicle)->assertCreated()->json('rezervacija_id');
        $payment = $this->api($client, 'POST', "/api/rezervacijas/$bookingId/pay")->assertOk()->json('maksajums');
        $this->api($client, 'GET', "/api/rezervacijas/$bookingId/invoice")->assertOk();
        $token = $owner->createToken('legacy')->plainTextToken;
        $reset = Password::createToken($owner);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $owner->getKey(), 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $block = $this->restrict($admin, $owner, Restriction::BLOCK)->assertCreated()->json('ierobezojums_id');
        $this->assertDatabaseMissing('sessions', ['user_id' => $owner->getKey()]);
        $this->assertSame(0, $owner->tokens()->count());
        $this->api(null, 'GET', '/api/auth/me', token: $token)->assertUnauthorized();
        $this->api(null, 'POST', '/api/auth/login', ['email' => $owner->epasts, 'password' => self::PASSWORD])
            ->assertForbidden()->assertJsonPath('code', 'account_blocked')->assertJsonPath('reason', 'Moderation reason')
            ->assertJsonPath('support_email', 'support@easyrent.example')->assertJsonMissingPath('token');
        $this->api(null, 'POST', '/api/auth/reset-password', [
            'email' => $owner->epasts, 'token' => $reset, 'password' => 'ChangedAdmin42!', 'password_confirmation' => 'ChangedAdmin42!',
        ])->assertUnprocessable();
        $this->api(null, 'GET', '/api/transport/'.$vehicle->getKey())->assertNotFound();
        $this->book($client, $vehicle, 6)->assertConflict();
        $this->assertDatabaseHas('rezervacija', ['rezervacija_id' => $bookingId, 'apmaksas_statuss' => 'apmaksata']);
        $this->assertDatabaseHas('rezervaciju_rekinu_arhivs', ['maksajums_id' => $payment['maksajums_id']]);
        $this->api($client, 'GET', "/api/rezervacijas/$bookingId/invoice")->assertOk();

        $count = count($this->messages());
        $this->enableTurnstile('invoice_recovery');
        $invoice = ['booking_id' => $bookingId, 'email' => $owner->epasts];
        $this->api(null, 'POST', '/api/support/invoice', $invoice)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->assertCount($count, $this->messages());
        $real = $this->api(null, 'POST', '/api/support/invoice', $invoice + ['turnstile_token' => 'fixture-invoice'])->assertAccepted();
        $fake = $this->api(null, 'POST', '/api/support/invoice', [
            'booking_id' => $bookingId, 'email' => 'outsider@example.test', 'turnstile_token' => 'fixture-invoice-other',
        ])->assertAccepted();
        $this->assertSame($real->json(), $fake->json());
        $this->assertCount($count + 1, $this->messages());
        $messages = $this->messages();
        $this->assertSame($owner->epasts, end($messages)->getOriginalMessage()->getTo()[0]->getAddress());

        $this->api($admin, 'PATCH', '/api/admin/users/'.$owner->getKey().'/restrictions/'.$block, ['iemesls' => 'Cleared'])
            ->assertOk();
        $this->api(null, 'GET', '/api/auth/me', token: $token)->assertUnauthorized();
        $this->api(null, 'POST', '/api/auth/login', ['email' => $owner->epasts, 'password' => self::PASSWORD])->assertOk();
    }

    public function test_admin_categories_listing_and_review_moderation_preserve_availability_and_rating(): void
    {
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $type = $this->api($admin, 'POST', '/api/admin/categories', ['nosaukums' => 'Minibusi', 'iemesls' => 'Catalog update'])
            ->assertCreated()->json('veids_id');
        $this->api($admin, 'PUT', '/api/transport/veidi/'.$type, ['nosaukums' => 'Elektriskie minibusi', 'iemesls' => 'Clarify name'])->assertOk();
        $this->api($owner, 'PUT', '/api/transport/veidi/'.$type, ['nosaukums' => 'Forbidden', 'iemesls' => 'Test'])->assertForbidden();
        $path = '/api/admin/categories/'.$vehicle->veids_id.'/availability';
        $this->api($admin, 'PATCH', $path, ['active' => false, 'iemesls' => 'Retired category'])->assertOk();
        $this->api($admin, 'GET', '/api/admin/categories?active=0')->assertOk()->assertJsonPath('total', 1);
        $this->api(null, 'GET', '/api/transport/veidi')->assertOk()->assertJsonCount(1);
        $this->api($owner, 'POST', '/api/transport', $this->listing($vehicle))->assertUnprocessable()->assertJsonValidationErrors('veids_id');
        $this->api(null, 'GET', '/api/transport/'.$vehicle->getKey())->assertOk();
        $this->api($admin, 'PATCH', $path, ['active' => true, 'iemesls' => 'Restored category'])->assertOk();

        $other = $this->account('klients');
        $review = $this->api($client, 'POST', '/api/atsauksmes', [
            'transportlidzeklis_id' => $vehicle->getKey(), 'vertejums' => 5, 'komentars' => 'Original',
        ])->assertCreated()->json('atsauksme.atsauksme_id');
        $this->api($other, 'POST', '/api/atsauksmes', ['transportlidzeklis_id' => $vehicle->getKey(), 'vertejums' => 1])->assertCreated();
        $this->assertRating($vehicle, 3, 2);
        $reviewPath = "/api/admin/reviews/$review/visibility";
        $this->api($admin, 'PATCH', $reviewPath, ['hidden' => true])->assertUnprocessable();
        $this->api($admin, 'PATCH', $reviewPath, ['hidden' => true, 'iemesls' => 'Moderation', 'komentars' => 'Forged'])
            ->assertOk();
        $this->assertSame('Original', Atsauksme::findOrFail($review)->komentars);
        $this->assertRating($vehicle, 1, 1);
        $this->api($admin, 'GET', '/api/admin/reviews?hidden=1&rating=5')->assertOk()->assertJsonPath('total', 1);
        $this->api($admin, 'PATCH', $reviewPath, ['hidden' => false, 'iemesls' => 'Restored'])->assertOk();
        $this->assertRating($vehicle, 3, 2);

        $visibility = '/api/admin/vehicles/'.$vehicle->getKey().'/visibility';
        $this->api($admin, 'PATCH', $visibility, ['hidden' => true, 'iemesls' => 'Verify listing'])->assertOk();
        $this->assertSame('pieejams', $vehicle->fresh()->statuss);
        $this->api(null, 'GET', '/api/transport')->assertOk()->assertJsonCount(0);
        $this->book($client, $vehicle)->assertConflict();
        $this->api($owner, 'PUT', '/api/transport/'.$vehicle->getKey(), ['marka' => 'Updated', 'moderation_hidden_at' => null])->assertOk();
        $this->api(null, 'GET', '/api/transport/'.$vehicle->getKey())->assertNotFound();
        $this->api($admin, 'GET', '/api/admin/vehicles?visibility=hidden')->assertOk()->assertJsonPath('total', 1);
        $this->api($admin, 'PATCH', $visibility, ['hidden' => false, 'iemesls' => 'Verified listing'])->assertOk();
        $this->book($client, $vehicle)->assertCreated();
    }

    public function test_reports_validate_targets_deduplicate_and_require_an_audited_decision(): void
    {
        $this->enableTurnstile('report');
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $description = ['iemesls' => 'misinformation', 'apraksts' => 'Listing information does not match'];
        $vehicleReport = ['objekta_veids' => 'vehicle', 'objekta_id' => $vehicle->getKey()] + $description;
        $this->api(null, 'POST', '/api/reports', $vehicleReport)->assertUnauthorized();
        $this->api($client, 'POST', '/api/reports', $vehicleReport)->assertUnprocessable()->assertJsonPath('code', 'captcha_required');
        $this->assertDatabaseEmpty('parkapuma_zinojums');
        $this->api($client, 'POST', '/api/reports', [
            'objekta_veids' => 'user', 'objekta_id' => $admin->getKey(), 'turnstile_token' => 'fixture-report-hidden',
        ] + $description)->assertNotFound();
        $id = $this->api($client, 'POST', '/api/reports', $vehicleReport + ['turnstile_token' => 'fixture-report-vehicle'])
            ->assertCreated()->assertJsonPath('statuss', 'jauns')->json('id');
        $this->api($client, 'POST', '/api/reports', $vehicleReport + ['turnstile_token' => 'fixture-report-conflict'])->assertConflict();
        $this->api($client, 'POST', '/api/reports', [
            'objekta_veids' => 'user', 'objekta_id' => $owner->getKey(), 'turnstile_token' => 'fixture-report-user',
        ] + $description)->assertCreated();
        $this->api($admin, 'GET', '/api/admin/reports?status=open&target=vehicle')->assertOk()->assertJsonPath('total', 1);
        $path = "/api/admin/reports/$id";
        $this->api($admin, 'GET', $path)->assertOk()->assertJsonPath('target.transportlidzeklis_id', $vehicle->getKey())
            ->assertJsonMissingPath('target.sniedzejs.persona.bankas_konts');
        $this->api($client, 'PATCH', $path, ['statuss' => 'atrisinats', 'iemesls' => 'Forbidden'])->assertForbidden();
        $this->api($admin, 'PATCH', $path, ['statuss' => 'atrisinats'])->assertUnprocessable();
        $this->api($admin, 'PATCH', $path, ['statuss' => 'izskatisana', 'iemesls' => 'Assigned for review'])->assertOk();
        $this->api($admin, 'PATCH', $path, [
            'statuss' => 'atrisinats', 'iemesls' => 'Listing verified https://example.test/action?token=private-value LV80BANK0000435195001',
        ])->assertOk();
        $this->api($admin, 'PATCH', $path, ['statuss' => 'noraidits', 'iemesls' => 'Double submission'])->assertConflict();
        $this->api($admin, 'GET', '/api/admin/audit')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.iepriekseja_vertiba.statuss', 'izskatisana')->assertJsonPath('data.0.jauna_vertiba.statuss', 'atrisinats')
            ->assertJsonPath('data.0.iemesls', 'Listing verified […] […]');
        $this->api($admin, 'DELETE', '/api/admin/audit/1')->assertNotFound();
        $this->assertDatabaseEmpty('lietotaja_ierobezojums');
        Http::assertSentCount(4);
    }

    private function enableTurnstile(string $action): void
    {
        config(['turnstile.enabled' => true, 'turnstile.secret_key' => 'fixture-secret']);
        Http::fake([TurnstileService::SITEVERIFY => Http::response([
            'success' => true, 'action' => $action, 'hostname' => 'easyrent.vip', 'challenge_ts' => now()->toISOString(),
        ])])->preventStrayRequests();
    }

    #[DataProvider('locales')]
    public function test_moderation_email_has_both_localized_parts(string $locale): void
    {
        $admin = $this->account('administrators');
        $client = $this->account('klients');
        $client->forceFill(['preferred_locale' => $locale])->save();
        $this->api($admin, 'POST', '/api/admin/users/'.$client->getKey().'/restrictions', [
            'veids' => Restriction::WARNING, 'duration' => '7d', 'iemesla_kods' => 'rental_terms', 'iemesls' => 'Moderation reason',
        ])->assertCreated()->assertJsonPath('iemesla_kods', 'rental_terms');
        $mail = $this->messages()[0]->getOriginalMessage();
        $this->assertSame(__('admin.mail.bridinajums', [], $locale), $mail->getSubject());
        $this->assertStringContainsString('lang="'.$locale.'"', $mail->getHtmlBody());
        $this->assertStringContainsString(__('admin.reasons.rental_terms', [], $locale), $mail->getTextBody());
        $this->assertStringContainsString('Moderation reason', $mail->getTextBody());
        $this->assertStringContainsString('https://easyrent.example/support', $mail->getTextBody());
    }

    public static function locales(): array
    {
        return [['lv'], ['ru'], ['en']];
    }

    public function test_email_failure_cannot_rollback_a_moderation_decision_or_its_audit(): void
    {
        $admin = $this->account('administrators');
        $client = $this->account('klients');
        $this->mock(AccountMail::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('Test transport failure'));
        $this->restrict($admin, $client, Restriction::WARNING)->assertCreated();
        $this->assertDatabaseCount('lietotaja_ierobezojums', 1);
        $this->assertDatabaseCount('administratora_darbiba', 1);
    }

    public function test_only_vehicle_owner_and_admin_can_load_vehicle_photos(): void
    {
        $admin = $this->account('administrators');
        [$client, $owner, $vehicle] = $this->rental();
        $id = $vehicle->getKey();
        $path = "/api/transport/$id/photos";
        $photoFile = UploadedFile::fake()->createWithContent('car.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aKHsAAAAASUVORK5CYII='
        ));
        $photoId = $this->api($owner, 'POST', $path, ['photos' => [$photoFile]])->assertCreated()->json('0.foto_id');
        $this->api($client, 'GET', "$path/$photoId")->assertForbidden();
        $this->api(null, 'GET', "$path/$photoId")->assertUnauthorized();
        $this->api($admin, 'GET', "/api/admin/vehicles/$id/photos/$photoId")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->api($owner, 'DELETE', "$path/$photoId")->assertNoContent();
        $this->api($admin, 'GET', "/api/admin/vehicles/$id/photos")->assertOk()->assertJsonCount(0);
    }

    private function assertRating(Transportlidzeklis $vehicle, int $expected, int $count): void
    {
        $id = $vehicle->getKey();
        $public = $this->api(null, 'GET', '/api/transport/'.$id)->assertOk()->assertJsonCount($count, 'atsauksmes');
        $this->assertEquals($expected, $public->json('videjais_vertejums'));
        $reviews = $this->api(null, 'GET', '/api/atsauksmes?transportlidzeklis_id='.$id)
            ->assertOk()->assertJsonCount($count, 'atsauksmes');
        $this->assertEquals($expected, $reviews->json('statistika.kopejais_vertejums'));
    }

    private function account(string $role, bool $verified = true): Persona
    {
        $person = Persona::forceCreate([
            'vards' => 'Test', 'uzvards' => ucfirst($role), 'epasts' => Str::uuid().'@example.test', 'loma' => $role,
            'parole' => Hash::make(self::PASSWORD), 'email_verified_at' => $verified ? now() : null,
            'bankas_konts' => 'LV80BANK0000435195001',
        ]);
        if ($role === 'klients') {
            $person->klients()->create([]);
        }
        if ($role === 'pakalpojumu_sniedzejs') {
            $person->pakalpojumuSniedzejs()->create(['iela' => 'Testa', 'majas_numurs' => '1', 'pilseta' => 'Rīga']);
        }

        return $person;
    }

    private function rental(): array
    {
        $client = $this->account('klients');
        $owner = $this->account('pakalpojumu_sniedzejs');
        $category = TransportliedzieklsVeids::create(['nosaukums' => 'Auto', 'tips' => 'auto']);
        $vehicle = $owner->pakalpojumuSniedzejs->transportlidzekli()->create([
            'veids_id' => $category->getKey(), 'marka' => 'Toyota', 'modelis' => 'Yaris', 'dienas_nomas_cena' => 35,
            'adrese' => 'Testa 1, Rīga', 'statuss' => 'pieejams', 'registracijas_numurs' => 'QA-42',
        ]);

        return [$client, $owner, $vehicle];
    }

    private function listing(Transportlidzeklis $vehicle): array
    {
        return [
            'veids_id' => $vehicle->veids_id, 'marka' => 'Toyota', 'modelis' => 'Yaris', 'dienas_nomas_cena' => 35,
            'statuss' => 'pieejams', 'registracijas_numurs' => 'QA-NEW', 'atrumkarba' => '-', 'degvielas_veids' => '-',
        ];
    }

    private function restrict(Persona $admin, Persona $subject, string $kind, string $duration = 'indefinite'): TestResponse
    {
        return $this->api($admin, 'POST', '/api/admin/users/'.$subject->getKey().'/restrictions', [
            'veids' => $kind, 'duration' => $duration, 'iemesls' => 'Moderation reason',
        ]);
    }

    private function book(Persona $client, Transportlidzeklis $vehicle, int $days = 3): TestResponse
    {
        return $this->api($client, 'POST', '/api/rezervacijas', [
            'transportlidzeklis_id' => $vehicle->getKey(),
            'sakuma_laiks' => now()->addDays($days)->toISOString(), 'beigu_laiks' => now()->addDays($days + 1)->toISOString(),
        ]);
    }

    private function api(?Persona $user, string $method, string $url, array $body = [], ?string $token = null): TestResponse
    {
        Auth::forgetGuards();
        $user?->refresh();
        $token ??= $user?->createToken('test')->plainTextToken;

        return $this->json($method, $url, $body, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function messages(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->all();
    }
}
