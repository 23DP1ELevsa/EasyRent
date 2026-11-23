<?php

namespace Tests\Feature;

use App\Models\Maksajums;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use App\Models\TransportliedzieklsVeids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret123!';
    private const CONFIRM = ['confirmation' => 'DZĒST', 'password' => self::PASSWORD];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->freezeTime();
    }

    #[DataProvider('roles')]
    public function test_account_without_reservations_can_be_deleted_and_all_tokens_are_revoked(string $role): void
    {
        $account = $this->account($role);
        $oldToken = $account->createToken('another-device')->plainTextToken;
        $this->authenticate($account);
        $this->getJson('/api/profile/deletion-check')->assertOk()->assertJson([
            'can_delete' => true, 'blocking_reservations_count' => 0, 'confirmation_method' => 'password',
        ]);
        $this->deleteJson('/api/profile/me', ['confirmation' => 'DZĒST'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson('/api/profile/me', ['confirmation' => 'DZĒST', 'password' => 'invalid'])->assertUnprocessable();
        $this->deleteJson('/api/profile/me', ['confirmation' => 'invalid', 'password' => self::PASSWORD])->assertUnprocessable();
        $this->deleteJson('/api/profile/me', self::CONFIRM)->assertNoContent();

        $this->assertSoftDeleted($account);
        $this->assertDatabaseHas('persona', [
            'persona_id' => $account->getKey(), 'epasts' => null, 'vards' => null, 'uzvards' => null,
            'kontakttalrunis' => null, 'bankas_konts' => null, 'parole' => null,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/api/profile/'.$account->getKey())->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $account->epasts, 'password' => self::PASSWORD])->assertUnprocessable();
    }

    public static function roles(): array
    {
        return [['klients'], ['pakalpojumu_sniedzejs']];
    }

    #[DataProvider('blockingReservations')]
    public function test_active_and_future_reservations_block_deletion(string $role, string $status, bool $active): void
    {
        $client = $this->account();
        $provider = $this->account('pakalpojumu_sniedzejs');
        $this->vehicle($provider); // A reservation on any one of several vehicles blocks deletion.
        $vehicle = $this->vehicle($provider);
        $reservation = $this->reservation($client, $vehicle, $active ? now()->subHour() : now()->addHour(), now()->addDay(), $status);
        $this->authenticate($role === 'klients' ? $client : $provider);

        $expected = ['can_delete' => false, 'blocking_reservations_count' => 1, 'reason' => 'active_or_upcoming_reservations'];
        $this->getJson('/api/profile/deletion-check')->assertOk()->assertJson($expected);
        $this->deleteJson('/api/profile/me', self::CONFIRM)->assertConflict()->assertJson($expected);
        $this->assertDatabaseHas('rezervacija', ['rezervacija_id' => $reservation->getKey(), 'apmaksas_statuss' => $status]);
        $this->assertDatabaseCount('persona', 2);
    }

    public static function blockingReservations(): array
    {
        return [
            'client future unpaid' => ['klients', 'neapmaksata', false],
            'client future paid' => ['klients', 'apmaksata', false],
            'client active' => ['klients', 'apmaksata', true],
            'provider future unpaid' => ['pakalpojumu_sniedzejs', 'neapmaksata', false],
            'provider future paid' => ['pakalpojumu_sniedzejs', 'apmaksata', false],
            'provider active' => ['pakalpojumu_sniedzejs', 'apmaksata', true],
        ];
    }

    public function test_expired_unpaid_hold_is_ignored_but_pending_payment_blocks_both_parties(): void
    {
        $client = $this->account();
        $provider = $this->account('pakalpojumu_sniedzejs');
        $vehicle = $this->vehicle($provider);
        $this->reservation($client, $vehicle, now()->subHour(), now()->addHour(), 'neapmaksata');
        $this->authenticate($client);
        $this->getJson('/api/profile/deletion-check')->assertOk()->assertJsonPath('can_delete', true);

        $finished = $this->reservation($client, $vehicle, now()->subDays(3), now()->subDay(), 'neapmaksata');
        $payment = $this->payment($finished, ['statuss' => 'gaida', 'rekins' => null]);
        foreach ([$client, $provider] as $account) {
            $this->authenticate($account);
            $this->deleteJson('/api/profile/me', self::CONFIRM)->assertConflict()->assertJson([
                'blocking_reservations_count' => 0, 'blocking_payments_count' => 1, 'reason' => 'pending_payments',
            ]);
        }
        $this->getJson('/api/transport')->assertOk();
        $this->assertDatabaseCount('rezervacija', 1);
        $this->assertModelExists($payment);
    }

    #[DataProvider('roles')]
    public function test_completed_reservation_and_invoice_survive_deleting_either_and_then_both_accounts(string $firstRole): void
    {
        Mail::fake();
        $client = $this->account();
        $provider = $this->account('pakalpojumu_sniedzejs');
        $vehicle = $this->vehicle($provider);
        $this->authenticate($client);
        $created = $this->postJson('/api/rezervacijas', $this->bookingPayload($vehicle))->assertCreated();
        $id = $created->json('rezervacija_id');
        $this->postJson("/api/rezervacijas/$id/pay")->assertOk();
        $this->travel(4)->days();
        $vehicle->update(['marka' => 'Jauna marka', 'modelis' => 'Jauns modelis']);
        $reservation = Rezervacija::findOrFail($id);
        $financialData = $reservation->maksajumi()->firstOrFail()->getRawOriginal();
        [$first, $survivor] = $firstRole === 'klients' ? [$client, $provider] : [$provider, $client];

        $this->authenticate($first);
        $this->deleteJson('/api/profile/me', self::CONFIRM)->assertNoContent();
        $this->authenticate($survivor);
        $history = $this->getJson('/api/rezervacijas')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.rezervacija_id', $id)
            ->assertJsonPath('0.transporta_snapshot.marka', 'Volkswagen')
            ->assertJsonPath('0.transporta_snapshot.modelis', 'Golf')
            ->assertJsonPath('0.sakuma_laiks', $created->json('sakuma_laiks'))
            ->assertJsonPath('0.beigu_laiks', $created->json('beigu_laiks'))
            ->assertJsonPath('0.kopa_summa', $created->json('kopa_summa'))
            ->assertJsonPath('0.apmaksas_statuss', 'apmaksata')
            ->assertJsonPath('0.invoice_available', true)
            ->assertDontSee($first->epasts);
        $partyPath = $firstRole === 'klients' ? '0.klients' : '0.transportlidzeklis.sniedzejs';
        $history->assertJsonPath($partyPath.'.persona_deleted', true)->assertJsonPath($partyPath.'.persona', null);
        $this->getJson("/api/rezervacijas/$id/invoice")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="EasyRent-'.$id.'.html"')
            ->assertSee($financialData['rekins'])->assertSee('Volkswagen Golf')->assertSee($first->epasts);
        foreach (['lv' => 'Rēķina numurs', 'en' => 'Invoice number', 'ru' => 'Номер счёта'] as $locale => $label) {
            $this->getJson("/api/rezervacijas/$id/invoice", ['Accept-Language' => $locale])
                ->assertOk()->assertHeader('Content-Language', $locale)->assertSee($label);
        }
        $this->getJson('/api/profile/'.$first->getKey())->assertForbidden();
        if ($firstRole === 'pakalpojumu_sniedzejs') {
            $this->getJson('/api/transport?sniedzejs_id='.$provider->pakalpojumuSniedzejs->getKey())->assertOk()->assertExactJson([]);
            $this->getJson('/api/transport/'.$vehicle->getKey())->assertNotFound();
            $this->postJson('/api/rezervacijas', $this->bookingPayload($vehicle))->assertConflict();
        }
        $this->deleteJson('/api/profile/me', self::CONFIRM)->assertNoContent();

        $this->assertSoftDeleted($client);
        $this->assertSoftDeleted($provider);
        $this->assertModelExists($reservation);
        $this->assertDatabaseHas('maksajums', $financialData);
        $this->assertDatabaseCount('rezervaciju_rekinu_arhivs', 1);
        $this->assertStringNotContainsString($first->epasts, DB::table('rezervaciju_rekinu_arhivs')->value('saturs'));
        $this->assertNotNull($vehicle->refresh()->archived_at);
        $this->assertNull($vehicle->registracijas_numurs);
    }

    public function test_legacy_invoice_is_archived_and_new_account_with_same_email_has_no_history(): void
    {
        $client = $this->account();
        $provider = $this->account('pakalpojumu_sniedzejs');
        $vehicle = $this->vehicle($provider);
        $reservation = $this->reservation($client, $vehicle, now()->subDays(3), now()->subDay(), 'apmaksata');
        $payment = $this->payment($reservation);
        $backfill = require database_path('migrations/2026_11_23_000004_backfill_booking_vehicle_snapshots.php');
        $backfill->up();
        $vehicle->update(['modelis' => 'Jauns modelis']);
        $backfill->up();
        $this->assertSame('Golf', $reservation->refresh()->transporta_snapshot['modelis']);

        $this->authenticate($client);
        $this->deleteJson('/api/profile/'.$provider->getKey(), self::CONFIRM)->assertMethodNotAllowed();
        $this->deleteJson('/api/profile/me', self::CONFIRM + ['persona_id' => $provider->getKey()])->assertNoContent();
        $this->assertNotNull($provider->fresh());

        $this->authenticate($provider);
        $invoice = '/api/rezervacijas/'.$reservation->getKey().'/invoice';
        $this->getJson($invoice)->assertOk()->assertSee($client->epasts)->assertSee($payment->rekins)
            ->assertSee('Volkswagen Golf');

        Auth::forgetGuards();
        $newAccount = $this->postJson('/api/auth/register', [
            'name' => 'Jauns Klients', 'email' => $client->epasts, 'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD, 'loma' => 'klients', 'lietotajvards' => 'jauns',
            'kontakttalrunis' => '20000000', 'bankas_konts' => 'LV80BANK0000435195001',
        ])->assertCreated();
        $this->assertNotEquals($client->getKey(), $newAccount->json('persona.persona_id'));
        Auth::forgetGuards();
        $this->withToken($newAccount->json('token'))->getJson('/api/rezervacijas')->assertOk()->assertExactJson([]);
        $this->getJson($invoice)->assertNotFound();
    }

    public function test_deletion_rolls_back_anonymization_archiving_and_revocation_on_failure(): void
    {
        $client = $this->account();
        $provider = $this->account('pakalpojumu_sniedzejs');
        $vehicle = $this->vehicle($provider);
        $reservation = $this->reservation($client, $vehicle, now()->subDays(3), now()->subDay(), 'apmaksata');
        $payment = $this->payment($reservation);
        Persona::deleting(fn () => false);

        $this->authenticate($provider);
        $this->deleteJson('/api/profile/me', self::CONFIRM)->assertServerError();
        $this->assertSame($provider->epasts, $provider->fresh()->epasts);
        $this->assertSame('Brīvības iela', $provider->pakalpojumuSniedzejs->fresh()->iela);
        $this->assertNull($vehicle->refresh()->archived_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('rezervaciju_rekinu_arhivs', 0);
        $this->assertModelExists($reservation);
        $this->assertModelExists($payment);
    }

    private function authenticate(Persona $account): void
    {
        Auth::forgetGuards();
        $this->withToken($account->createToken('test')->plainTextToken);
    }

    private function account(string $role = 'klients'): Persona
    {
        $account = Persona::create([
            'vards' => 'Anna', 'uzvards' => 'Ozola', 'epasts' => uniqid('account-').'@example.test',
            'kontakttalrunis' => '20000000', 'bankas_konts' => 'LV80BANK0000435195001',
            'parole' => Hash::make(self::PASSWORD), 'loma' => $role,
        ]);
        if ($role === 'klients') {
            $account->klients()->create(['lietotajvards' => uniqid('user-')]);
        } else {
            $account->pakalpojumuSniedzejs()->create([
                'registracijas_numurs' => '123456', 'iela' => 'Brīvības iela', 'majas_numurs' => '42',
                'pilseta' => 'Rīga', 'pasta_indekss' => 'LV-1001', 'latitude' => 56.95, 'longitude' => 24.10,
            ]);
        }

        return $account;
    }

    private function vehicle(Persona $provider): Transportlidzeklis
    {
        $type = TransportliedzieklsVeids::create(['nosaukums' => uniqid('Auto-'), 'tips' => 'auto']);

        return $provider->pakalpojumuSniedzejs->transportlidzekli()->create([
            'veids_id' => $type->getKey(), 'marka' => 'Volkswagen', 'modelis' => 'Golf',
            'dienas_nomas_cena' => '49.99', 'adrese' => 'Brīvības iela 42, Rīga',
            'statuss' => 'pieejams', 'registracijas_numurs' => uniqid('LV-'),
        ]);
    }

    private function bookingPayload(Transportlidzeklis $vehicle): array
    {
        return [
            'transportlidzeklis_id' => $vehicle->getKey(),
            'sakuma_laiks' => now()->addDay()->toISOString(),
            'beigu_laiks' => now()->addDays(3)->toISOString(),
        ];
    }

    private function reservation(Persona $client, Transportlidzeklis $vehicle, $start, $end, string $status): Rezervacija
    {
        return $vehicle->rezervacijas()->create([
            'klients_id' => $client->klients->getKey(), 'sakuma_laiks' => $start, 'beigu_laiks' => $end,
            'rezervacijas_datums' => $start->toDateString(), 'kopa_summa' => '99.98', 'apmaksas_statuss' => $status,
        ]);
    }

    private function payment(Rezervacija $reservation, array $overrides = []): Maksajums
    {
        return $reservation->maksajumi()->create($overrides + [
            'summa' => '99.98', 'statuss' => 'apstiprinats',
            'tranzakcijas_numurs' => uniqid('TX-'), 'rekins' => uniqid('ER-'),
        ]);
    }
}
