<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\Atsauksme;
use App\Models\Klients;
use App\Models\PakalpojumuSniedzejs;
use App\Models\Persona;
use App\Models\TransportliedzieklsVeids;
use App\Models\Transportlidzeklis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthContactAndReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Client',
            'email' => 'new-client@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'loma' => 'klients',
            'kontakttalrunis' => '+37120000000',
            'bankas_konts' => 'LV80BANK0000435195001',
            'lietotajvards' => 'newclient',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'token',
                'persona' => ['persona_id', 'epasts', 'loma', 'klients'],
            ])
            ->assertJsonPath('persona.epasts', 'new-client@example.com')
            ->assertJsonPath('persona.loma', 'klients')
            ->assertJsonPath('persona.klients.lietotajvards', 'newclient');

        $this->assertDatabaseHas('persona', [
            'epasts' => 'new-client@example.com',
            'loma' => 'klients',
        ]);

        $this->assertDatabaseHas('klients', [
            'lietotajvards' => 'newclient',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_provider_registration_uses_geocoding_when_coordinates_are_missing(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                ['lat' => '56.9496', 'lon' => '24.1052'],
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Provider Test',
            'email' => 'provider@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'loma' => 'pakalpojumu_sniedzejs',
            'kontakttalrunis' => '+37121111111',
            'bankas_konts' => 'LV80BANK0000435195002',
            'registracijas_numurs' => 'REG-123',
            'iela' => 'Brivibas iela',
            'majas_numurs' => '10',
            'pilseta' => 'Riga',
            'pasta_indekss' => 'LV-1010',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('persona.loma', 'pakalpojumu_sniedzejs')
            ->assertJsonPath('persona.pakalpojumu_sniedzejs.registracijas_numurs', 'REG-123');

        Http::assertSentCount(1);

        $personaId = $response->json('persona.persona_id');
        $provider = PakalpojumuSniedzejs::where('persona_id', $personaId)->first();

        $this->assertNotNull($provider);
        $this->assertEquals(56.9496, (float) $provider->latitude);
        $this->assertEquals(24.1052, (float) $provider->longitude);
    }

    public function test_registration_rejects_password_without_required_complexity(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Weak Password',
            'email' => 'weak@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'loma' => 'klients',
            'kontakttalrunis' => '+37120000000',
            'bankas_konts' => 'LV80BANK0000435195001',
            'lietotajvards' => 'weakclient',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_logout_and_current_token_is_deleted(): void
    {
        $persona = $this->createClientPersona();
        $token = $persona->createToken('frontend');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'OK']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_contact_endpoint_sends_email(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', [
            'name' => 'EasyRent User',
            'email' => 'sender@example.com',
            'comment' => 'This is a valid contact message for backend feature testing.',
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->data['name'] === 'EasyRent User'
                && $mail->data['email'] === 'sender@example.com';
        });
    }

    public function test_review_index_returns_filtered_statistics_for_requested_vehicle(): void
    {
        $firstClient = $this->createClientPersona(['epasts' => 'first@example.com'], ['lietotajvards' => 'first_client']);
        $secondClient = $this->createClientPersona(['epasts' => 'second@example.com'], ['lietotajvards' => 'second_client']);
        $provider = $this->createProviderPersona();
        $type = $this->createVehicleType();
        $firstVehicle = $this->createVehicle($provider, $type, ['registracijas_numurs' => 'AAA-111']);
        $secondVehicle = $this->createVehicle($provider, $type, ['registracijas_numurs' => 'BBB-222']);

        Atsauksme::create([
            'klients_id' => $firstClient->klients->klients_id,
            'transportlidzeklis_id' => $firstVehicle->transportlidzeklis_id,
            'vertejums' => 5,
            'komentars' => 'Excellent',
            'datums' => now()->toDateString(),
        ]);

        Atsauksme::create([
            'klients_id' => $secondClient->klients->klients_id,
            'transportlidzeklis_id' => $firstVehicle->transportlidzeklis_id,
            'vertejums' => 3,
            'komentars' => 'Average',
            'datums' => now()->subDay()->toDateString(),
        ]);

        Atsauksme::create([
            'klients_id' => $firstClient->klients->klients_id,
            'transportlidzeklis_id' => $secondVehicle->transportlidzeklis_id,
            'vertejums' => 1,
            'komentars' => 'Other vehicle review',
            'datums' => now()->subDays(2)->toDateString(),
        ]);

        $this->getJson('/api/atsauksmes?transportlidzeklis_id='.$firstVehicle->transportlidzeklis_id)
            ->assertOk()
            ->assertJsonCount(2, 'atsauksmes')
            ->assertJsonPath('statistika.kopejais_atsauksmju_skaits', 2)
            ->assertJsonPath('statistika.kopejais_vertejums', 4)
            ->assertJsonPath('statistika.transportlidzekli.0.transportlidzeklis_id', $firstVehicle->transportlidzeklis_id)
            ->assertJsonPath('statistika.transportlidzekli.0.atsauksmju_skaits', 2)
            ->assertJsonPath('statistika.transportlidzekli.0.videjais_vertejums', 4);
    }

    public function test_client_can_update_only_their_own_review(): void
    {
        $author = $this->createClientPersona(['epasts' => 'author@example.com'], ['lietotajvards' => 'author_client']);
        $otherClient = $this->createClientPersona(['epasts' => 'other-reviewer@example.com'], ['lietotajvards' => 'other_client']);
        $provider = $this->createProviderPersona();
        $type = $this->createVehicleType();
        $vehicle = $this->createVehicle($provider, $type);

        Sanctum::actingAs($author);

        $createdReview = $this->postJson('/api/atsauksmes', [
            'transportlidzeklis_id' => $vehicle->transportlidzeklis_id,
            'vertejums' => 4,
            'komentars' => 'Initial review comment',
        ]);

        $reviewId = $createdReview->json('atsauksme.atsauksme_id');

        $createdReview
            ->assertCreated()
            ->assertJsonPath('message', 'Atsauksme pievienota.');

        $this->putJson('/api/atsauksmes/'.$reviewId, [
            'vertejums' => 5,
            'komentars' => 'Updated review comment',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Atsauksme atjaunināta.')
            ->assertJsonPath('atsauksme.vertejums', 5)
            ->assertJsonPath('atsauksme.komentars', 'Updated review comment');

        Sanctum::actingAs($otherClient);

        $this->putJson('/api/atsauksmes/'.$reviewId, [
            'vertejums' => 2,
            'komentars' => 'Should not be allowed',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Nav atļauts rediģēt cita klienta atsauksmi.');
    }

    private function createClientPersona(array $personaOverrides = [], array $clientOverrides = []): Persona
    {
        $suffix = uniqid();

        $persona = Persona::create(array_merge([
            'vards' => 'Test',
            'uzvards' => 'Client',
            'epasts' => "client-{$suffix}@example.com",
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

    private function createProviderPersona(array $personaOverrides = [], array $providerOverrides = []): PakalpojumuSniedzejs
    {
        $suffix = uniqid();

        $persona = Persona::create(array_merge([
            'vards' => 'Test',
            'uzvards' => 'Provider',
            'epasts' => "provider-{$suffix}@example.com",
            'parole' => Hash::make('Secret123!'),
            'kontakttalrunis' => '+37121111111',
            'loma' => 'pakalpojumu_sniedzejs',
            'bankas_konts' => 'LV80BANK0000435195002',
        ], $personaOverrides));

        return PakalpojumuSniedzejs::create(array_merge([
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