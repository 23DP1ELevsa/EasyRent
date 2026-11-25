<?php

namespace Tests\Feature;

use App\Mail\ReservationInvoiceMail;
use App\Models\Klients;
use App\Models\Maksajums;
use App\Models\PakalpojumuSniedzejs;
use App\Models\Persona;
use App\Models\TransportliedzieklsVeids;
use App\Models\Transportlidzeklis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\VehicleImages;
use Tests\TestCase;

class ApiCriteriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_login_returns_token_and_me_returns_authenticated_persona(): void
    {
        $persona = $this->createClientPersona([
            'epasts' => 'client@example.com',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'client@example.com',
            'password' => 'Secret123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'persona' => ['persona_id', 'epasts', 'loma', 'klients'],
            ]);

        $token = $response->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('persona.persona_id', $persona->persona_id)
            ->assertJsonPath('persona.klients.klients_id', $persona->klients->klients_id);
    }

    public function test_profile_endpoint_forbids_access_to_other_persona(): void
    {
        $owner = $this->createClientPersona(['epasts' => 'owner@example.com']);
        $other = $this->createClientPersona(['epasts' => 'other@example.com']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/profile/{$other->persona_id}")
            ->assertForbidden();
    }

    public function test_login_allows_existing_account_with_legacy_weak_password(): void
    {
        Persona::forceCreate([
            'vards' => 'Legacy',
            'uzvards' => 'User',
            'epasts' => 'legacy@example.com',
            'email_verified_at' => now(),
            'parole' => Hash::make('weakpass'),
            'kontakttalrunis' => '+37120000000',
            'loma' => 'klients',
            'bankas_konts' => 'LV80BANK0000435195001',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'legacy@example.com',
            'password' => 'weakpass',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'persona']);
    }

    public function test_profile_update_rejects_password_without_required_complexity(): void
    {
        $persona = $this->createClientPersona(['epasts' => 'profile@example.com']);

        Sanctum::actingAs($persona);

        $this->putJson("/api/profile/{$persona->persona_id}", [
            'password' => 'password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_client_can_create_and_pay_reservation_when_authenticated(): void
    {
        $client = $this->createClientPersona();
        $provider = $this->createProviderPersona();
        $type = $this->createVehicleType();
        $vehicle = $this->createVehicle($provider->pakalpojumuSniedzejs, $type);

        Mail::fake();

        Sanctum::actingAs($client);

        $reservationResponse = $this->postJson('/api/rezervacijas', [
            'transportlidzeklis_id' => $vehicle->transportlidzeklis_id,
            'sakuma_laiks' => Carbon::now()->addDay()->toISOString(),
            'beigu_laiks' => Carbon::now()->addDays(3)->toISOString(),
        ]);

        $reservationResponse
            ->assertCreated()
            ->assertJsonPath('klients_id', $client->klients->klients_id)
            ->assertJsonPath('transportlidzeklis_id', $vehicle->transportlidzeklis_id);

        $reservationId = $reservationResponse->json('rezervacija_id');

        $this->postJson("/api/rezervacijas/{$reservationId}/pay")
            ->assertOk()
            ->assertJsonPath('rezervacija.apmaksas_statuss', 'apmaksata')
            ->assertJsonPath('maksajums.rezervacija_id', $reservationId);

        $this->assertDatabaseHas('maksajums', [
            'rezervacija_id' => $reservationId,
            'statuss' => 'apstiprinats',
        ]);

        $maksajums = Maksajums::where('rezervacija_id', $reservationId)->first();

        $this->assertNotNull($maksajums?->rekins);
        $this->assertStringStartsWith('ER-', $maksajums->rekins);

        Mail::assertSent(ReservationInvoiceMail::class, 2);
        Mail::assertSent(ReservationInvoiceMail::class, function (ReservationInvoiceMail $mail) use ($reservationId) {
            return $mail->rezervacija->rezervacija_id === $reservationId
                && in_array($mail->recipientType, ['client', 'provider'], true);
        });
    }

    public function test_pickup_location_excludes_apartments_and_requires_a_paid_booking_to_appear_in_the_client_profile(): void
    {
        $client = $this->createClientPersona();
        $provider = $this->createProviderPersona(providerOverrides: [
            'iela' => 'Brīvības iela 10-42',
            'dzivokla_numurs' => '42',
            'pilseta' => 'Rīga',
        ]);
        $vehicle = $this->createVehicle($provider->pakalpojumuSniedzejs, $this->createVehicleType(), [
            'adrese' => 'Brīvības iela 10-42, Rīga',
        ]);

        $catalog = $this->getJson('/api/transport')->assertOk()
            ->assertJsonPath('0.pickup_location.address', 'Brīvības iela 10')
            ->assertJsonPath('0.pickup_location.city', 'Rīga')
            ->assertJsonPath('0.pickup_location.postalCode', 'LV-1010')
            ->assertJsonPath('0.adrese', 'Brīvības iela 10, Rīga')
            ->assertJsonPath('0.sniedzejs.iela', 'Brīvības iela 10')
            ->assertJsonMissingPath('0.sniedzejs.dzivokla_numurs');
        $location = $catalog->json('0.pickup_location');
        $this->assertEquals(56.9496, $location['latitude']);
        $this->assertEquals(24.1052, $location['longitude']);
        $this->getJson('/api/transport/'.$vehicle->getKey())->assertOk()
            ->assertJsonPath('pickup_location', $location)->assertJsonMissingPath('sniedzejs.dzivokla_numurs');

        Mail::fake();
        Sanctum::actingAs($client);
        $booking = $this->postJson('/api/rezervacijas', [
            'transportlidzeklis_id' => $vehicle->getKey(),
            'sakuma_laiks' => now()->addDay()->toISOString(),
            'beigu_laiks' => now()->addDays(2)->toISOString(),
        ])->assertCreated()->assertJsonMissingPath('transportlidzeklis.adrese')
            ->assertJsonMissingPath('transportlidzeklis.sniedzejs.dzivokla_numurs');
        $this->getJson('/api/rezervacijas')->assertOk()->assertJsonMissingPath('0.pickup_location');

        $this->postJson('/api/rezervacijas/'.$booking->json('rezervacija_id').'/pay')->assertOk();
        $this->getJson('/api/rezervacijas')->assertOk()->assertJsonPath('0.pickup_location', $location)
            ->assertJsonMissingPath('0.transportlidzeklis.adrese');

        Sanctum::actingAs($provider);
        $this->getJson('/api/rezervacijas')->assertOk()->assertJsonMissingPath('0.pickup_location');

        $vehicle->forceFill(['moderation_hidden_at' => now()])->save();
        Sanctum::actingAs($client);
        $this->getJson('/api/rezervacijas')->assertOk()->assertJsonMissingPath('0.pickup_location');
    }

    public function test_provider_can_create_transport_but_client_cannot(): void
    {
        $client = $this->createClientPersona(['epasts' => 'blocked@example.com']);
        $provider = $this->createProviderPersona(['epasts' => 'provider@example.com']);
        $type = $this->createVehicleType();

        $payload = [
            'veids_id' => $type->veids_id,
            'marka' => 'Volkswagen',
            'modelis' => 'Golf',
            'atrumkarba' => 'Automāts',
            'degvielas_veids' => 'Benzīns',
            'dienas_nomas_cena' => 49.99,
            'statuss' => 'pieejams',
            'registracijas_numurs' => 'AB-1234',
            'vaditaja_apliecibas_kategorija' => 'B',
            'photos' => [VehicleImages::file()],
        ];

        Sanctum::actingAs($client);

        $this->post('/api/transport', $payload, ['Accept' => 'application/json'])
            ->assertForbidden();

        Sanctum::actingAs($provider);

        $this->post('/api/transport', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('sniedzejs_id', $provider->pakalpojumuSniedzejs->sniedzejs_id)
            ->assertJsonPath('marka', 'Volkswagen');
    }

    private function createClientPersona(array $personaOverrides = [], array $clientOverrides = []): Persona
    {
        $suffix = uniqid();

        $persona = Persona::forceCreate(array_merge([
            'vards' => 'Test',
            'uzvards' => 'Client',
            'epasts' => "client-{$suffix}@example.com",
            'email_verified_at' => now(),
            'parole' => Hash::make('Secret123!'),
            'kontakttalrunis' => '+37120000000',
            'loma' => 'klients',
            'bankas_konts' => 'LV80BANK0000435195001',
        ], $personaOverrides));

        Klients::create(array_merge([
            'persona_id' => $persona->persona_id,
            'lietotajvards' => "client_{$suffix}",
        ], $clientOverrides));

        return $persona->fresh()->load('klients');
    }

    private function createProviderPersona(array $personaOverrides = [], array $providerOverrides = []): Persona
    {
        $suffix = uniqid();

        $persona = Persona::forceCreate(array_merge([
            'vards' => 'Test',
            'uzvards' => 'Provider',
            'epasts' => "provider-{$suffix}@example.com",
            'email_verified_at' => now(),
            'parole' => Hash::make('Secret123!'),
            'kontakttalrunis' => '+37121111111',
            'loma' => 'pakalpojumu_sniedzejs',
            'bankas_konts' => 'LV80BANK0000435195002',
        ], $personaOverrides));

        PakalpojumuSniedzejs::create(array_merge([
            'persona_id' => $persona->persona_id,
            'registracijas_numurs' => "REG-{$suffix}",
            'iela' => 'Brivibas iela',
            'majas_numurs' => '10',
            'dzivokla_numurs' => null,
            'pilseta' => 'Riga',
            'pasta_indekss' => 'LV-1010',
            'latitude' => 56.9496,
            'longitude' => 24.1052,
        ], $providerOverrides));

        return $persona->fresh()->load('pakalpojumuSniedzejs');
    }

    private function createVehicleType(array $overrides = []): TransportliedzieklsVeids
    {
        $suffix = uniqid();

        return TransportliedzieklsVeids::create(array_merge([
            'nosaukums' => "Auto {$suffix}",
            'tips' => "auto_{$suffix}",
        ], $overrides));
    }

    private function createVehicle(PakalpojumuSniedzejs $provider, TransportliedzieklsVeids $type, array $overrides = []): Transportlidzeklis
    {
        $suffix = uniqid();

        return Transportlidzeklis::create(array_merge([
            'sniedzejs_id' => $provider->sniedzejs_id,
            'veids_id' => $type->veids_id,
            'marka' => 'Audi',
            'modelis' => 'A4',
            'atrumkarba' => 'Automāts',
            'degvielas_veids' => 'Dīzelis',
            'dienas_nomas_cena' => 65.00,
            'adrese' => 'Brivibas iela 10, Riga',
            'statuss' => 'pieejams',
            'registracijas_numurs' => "TEST-{$suffix}",
        ], $overrides));
    }
}
