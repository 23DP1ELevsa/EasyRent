<?php

namespace Tests\Feature;

use App\Models\LietotajaIerobezojums;
use App\Models\Persona;
use App\Models\TransportliedzieklsVeids;
use App\Models\TransportlidzeklaFoto;
use App\Models\Transportlidzeklis;
use App\Support\DrivingLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\VehicleImages;
use Tests\TestCase;

class VehicleListingTest extends TestCase
{
    use RefreshDatabase;

    private Persona $owner;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->account('pakalpojumu_sniedzejs');
        $this->categoryId = TransportliedzieklsVeids::create(['nosaukums' => 'Auto', 'tips' => 'auto'])->getKey();
    }

    public function test_multipart_create_saves_the_selected_cover_and_returns_only_catalog_metadata(): void
    {
        $payload = $this->payload([
            'photos' => [VehicleImages::file(), VehicleImages::file('jpg'), VehicleImages::file('webp')],
            'primary_photo_index' => '1',
        ]);
        $id = $this->api($this->owner, 'POST', '/api/transport', $payload)
            ->assertCreated()->assertJsonPath(DrivingLicence::FIELD, 'B')->json('transportlidzeklis_id');

        $jpegs = TransportlidzeklaFoto::where('transportlidzeklis_id', $id)->where('mime_type', 'image/jpeg')->sole();
        $catalog = $this->api(null, 'GET', '/api/transport')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.photos_count', 3)->assertJsonMissingPath('0.photos')->assertJsonMissingPath('0.primary_photo');
        $url = $catalog->json('0.primary_photo_url');
        $this->assertStringStartsWith("/api/transport/$id/public-photos/{$jpegs->getKey()}?v=", $url);
        $this->assertStringNotContainsString($jpegs->attels, $catalog->getContent());
        $this->api(null, 'GET', "/api/transport/$id")->assertOk()->assertJsonPath('primary_photo_url', $url);
        $this->api(null, 'GET', "/api/transport/$id/public-photos")
            ->assertOk()->assertJsonCount(3)->assertJsonPath('0.url', $url);
        $image = $this->api(null, 'GET', $url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($payload['photos'][1]->getContent(), $image->getContent());
        $this->assertStringContainsString('no-cache', $image->headers->get('Cache-Control'));
        $this->get($url, ['If-None-Match' => $image->headers->get('ETag')])->assertStatus(304);
    }

    public function test_create_requires_photos_and_a_supported_licence_category(): void
    {
        $payload = $this->payload();
        unset($payload['photos']);
        $this->api($this->owner, 'POST', '/api/transport', $payload)->assertUnprocessable()->assertJsonValidationErrors('photos');
        $payload = $this->payload();
        unset($payload[DrivingLicence::FIELD]);
        $this->api($this->owner, 'POST', '/api/transport', $payload)->assertUnprocessable()->assertJsonValidationErrors(DrivingLicence::FIELD);
        $this->api($this->owner, 'POST', '/api/transport', $this->payload([DrivingLicence::FIELD => 'XYZ']))
            ->assertUnprocessable()->assertJsonValidationErrors(DrivingLicence::FIELD);
        $this->api($this->owner, 'POST', '/api/transport', $this->payload(['primary_photo_index' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors('primary_photo_index');
        $this->assertDatabaseEmpty('transportlidzeklis');
        $this->assertDatabaseEmpty('transportlidzekla_foto');
    }

    public function test_create_rolls_back_the_vehicle_and_first_photo_if_the_second_save_fails(): void
    {
        TransportlidzeklaFoto::creating(function (TransportlidzeklaFoto $photo) {
            return $photo->mime_type !== 'image/webp';
        });

        $this->api($this->owner, 'POST', '/api/transport', $this->payload([
            'photos' => [VehicleImages::file(), VehicleImages::file('webp')],
        ]))->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->assertDatabaseEmpty('transportlidzeklis');
        $this->assertDatabaseEmpty('transportlidzekla_foto');
    }

    public function test_create_and_replacement_validate_actual_file_type_size_and_count(): void
    {
        $invalid = [
            UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            VehicleImages::file()->size(1537),
        ];
        foreach ($invalid as $photo) {
            $this->api($this->owner, 'POST', '/api/transport', $this->payload(['photos' => [$photo]]))
                ->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        }
        $this->api($this->owner, 'POST', '/api/transport', $this->payload(['photos' => array_fill(0, 5, VehicleImages::file())]))
            ->assertUnprocessable()->assertJsonValidationErrors('photos');

        $id = $this->api($this->owner, 'POST', '/api/transport', $this->payload(['photos' => [VehicleImages::file()->size(1536)]]))
            ->assertCreated()->json('transportlidzeklis_id');
        $photoId = TransportlidzeklaFoto::sole()->getKey();
        $this->api($this->owner, 'POST', "/api/transport/$id/photos/$photoId", ['photo' => $invalid[0]])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->api(null, 'GET', "/api/transport/$id/public-photos/$photoId")->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_owner_can_change_and_replace_the_main_photo_at_capacity_and_cannot_delete_the_last(): void
    {
        $id = $this->legacy()->getKey();
        $photos = "/api/transport/$id/photos";
        $uploaded = $this->api($this->owner, 'POST', $photos, ['photos' => array_fill(0, 4, VehicleImages::file())])
            ->assertCreated()->assertJsonCount(4)->json();
        $this->api($this->owner, 'POST', $photos, ['photos' => [VehicleImages::file()]])
            ->assertUnprocessable()->assertJsonValidationErrors('photos');

        $main = $uploaded[2]['foto_id'];
        $this->api($this->owner, 'PATCH', "$photos/$main/primary")->assertNoContent();
        $this->api($this->owner, 'GET', $photos)->assertOk()->assertJsonPath('0.foto_id', $main);
        $oldUrl = $this->api(null, 'GET', "/api/transport/$id")->assertOk()->json('primary_photo_url');
        $oldEtag = $this->api(null, 'GET', $oldUrl)->assertOk()->headers->get('ETag');
        $this->api($this->owner, 'POST', "$photos/$main", ['photo' => VehicleImages::file('webp')])->assertNoContent();
        $newUrl = $this->api(null, 'GET', "/api/transport/$id")->assertOk()->assertJsonPath('photos_count', 4)->json('primary_photo_url');
        $this->assertNotSame($oldUrl, $newUrl);
        $this->get($oldUrl, ['If-None-Match' => $oldEtag])->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->api($this->owner, 'DELETE', "$photos/$main")->assertNoContent();
        $this->api(null, 'GET', $newUrl)->assertNotFound();
        $this->api(null, 'GET', "/api/transport/$id/public-photos")->assertOk()->assertJsonPath('0.foto_id', $uploaded[0]['foto_id']);
        foreach ([0, 1] as $index) {
            $this->api($this->owner, 'DELETE', $photos.'/'.$uploaded[$index]['foto_id'])->assertNoContent();
        }
        $this->api($this->owner, 'DELETE', $photos.'/'.$uploaded[3]['foto_id'])->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->assertDatabaseCount('transportlidzekla_foto', 1);
    }

    public function test_public_gallery_enforces_photo_ownership_and_live_visibility_even_with_an_etag(): void
    {
        $vehicle = $this->legacy();
        $id = $vehicle->getKey();
        $photo = $this->api($this->owner, 'POST', "/api/transport/$id/photos", ['photos' => [VehicleImages::file()]])
            ->assertCreated()->json('0.foto_id');
        $base = "/api/transport/$id/public-photos";
        $etag = $this->api(null, 'GET', "$base/$photo")->assertOk()->headers->get('ETag');
        $otherId = $this->legacy()->getKey();
        $this->api(null, 'GET', "/api/transport/$otherId/public-photos/$photo")->assertNotFound();
        $this->api(null, 'GET', '/api/transport/999999/public-photos')->assertNotFound();

        $admin = $this->account('administrators');
        $this->api($admin, 'PATCH', "/api/admin/vehicles/$id/visibility", ['hidden' => true, 'iemesls' => 'Inappropriate photo'])->assertOk();
        $this->get("$base/$photo", ['If-None-Match' => $etag])->assertNotFound();
        $this->api(null, 'GET', $base)->assertNotFound();
        $this->api($admin, 'GET', "/api/admin/vehicles/$id/photos/$photo")->assertOk();
        $this->api($admin, 'PATCH', "/api/admin/vehicles/$id/visibility", ['hidden' => false, 'iemesls' => 'Reviewed'])->assertOk();
        $this->api(null, 'GET', "$base/$photo")->assertOk();

        $vehicle->forceFill(['archived_at' => now()])->save();
        $this->api(null, 'GET', "$base/$photo")->assertNotFound();
        $this->api(null, 'GET', $base)->assertNotFound();
        $this->api($this->owner, 'GET', "/api/transport/$id/photos/$photo")->assertNotFound();
        $this->api($admin, 'GET', "/api/admin/vehicles/$id/photos/$photo")->assertOk();
    }

    public function test_photo_writes_require_ownership_and_respect_account_restrictions(): void
    {
        $id = $this->legacy()->getKey();
        $path = "/api/transport/$id/photos";
        $photo = $this->api($this->owner, 'POST', $path, ['photos' => [VehicleImages::file()]])->assertCreated()->json('0.foto_id');
        $other = $this->account('pakalpojumu_sniedzejs');
        $this->api($other, 'POST', $path, ['photos' => [VehicleImages::file()]])->assertNotFound();
        $this->api($other, 'POST', "$path/$photo", ['photo' => VehicleImages::file()])->assertNotFound();
        $this->api($other, 'DELETE', "$path/$photo")->assertNotFound();
        $this->api($other, 'PATCH', "$path/$photo/primary")->assertNotFound();
        $admin = $this->account('administrators');
        $this->api($admin, 'POST', '/api/admin/users/'.$this->owner->getKey().'/restrictions', [
            'veids' => LietotajaIerobezojums::LISTING, 'duration' => 'indefinite', 'iemesls' => 'Restriction',
        ])->assertCreated();
        $this->api($this->owner, 'POST', "$path/$photo", ['photo' => VehicleImages::file()])->assertForbidden();
        $this->api($this->owner, 'PUT', "/api/transport/$id", ['statuss' => 'neaktivs'])->assertOk();
        $this->api(null, 'GET', "/api/transport/$id/public-photos/$photo")->assertNotFound();
    }

    public function test_catalog_filters_exact_categories_with_or_and_combines_existing_filters(): void
    {
        $b = $this->legacy([DrivingLicence::FIELD => 'B']);
        $a2 = $this->legacy([DrivingLicence::FIELD => 'A2', 'dienas_nomas_cena' => 42]);
        $none = $this->legacy([DrivingLicence::FIELD => 'NONE']);
        $unknown = $this->legacy();
        $this->legacy([DrivingLicence::FIELD => 'A']);

        $this->api(null, 'GET', '/api/transport')->assertOk()->assertJsonCount(5);
        $this->api(null, 'GET', '/api/transport?licence_categories[]=A2')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.transportlidzeklis_id', $a2->getKey());
        $both = $this->api(null, 'GET', '/api/transport?licence_categories[]=B&licence_categories[]=A2')->assertOk();
        $this->assertEqualsCanonicalizing([$b->getKey(), $a2->getKey()], array_column($both->json(), 'transportlidzeklis_id'));
        $this->api(null, 'GET', "/api/transport?licence_categories[]=B&licence_categories[]=A2&max_cena=40&veids_id=$this->categoryId&statuss=pieejams")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.transportlidzeklis_id', $b->getKey());
        $this->api(null, 'GET', '/api/transport?licence_categories[]=NONE')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.transportlidzeklis_id', $none->getKey());
        $this->api(null, 'GET', '/api/transport?licence_categories[]=UNKNOWN')->assertUnprocessable()
            ->assertJsonValidationErrors('licence_categories.0');
        $this->api(null, 'GET', '/api/transport/'.$unknown->getKey())->assertOk()
            ->assertJsonPath(DrivingLicence::FIELD, null)->assertJsonPath('photos_count', 0)->assertJsonPath('primary_photo_url', null);
    }

    public function test_admin_can_find_incomplete_listings_and_old_listings_remain_bookable_until_unpublished(): void
    {
        $legacy = $this->legacy();
        $id = $legacy->getKey();
        $path = "/api/transport/$id";
        $ready = $this->legacy([DrivingLicence::FIELD => 'NONE']);
        $this->api($this->owner, 'POST', '/api/transport/'.$ready->getKey().'/photos', ['photos' => [VehicleImages::file()]])->assertCreated();
        $admin = $this->account('administrators');
        foreach (['missing_photos', 'missing_licence'] as $filter) {
            $this->api($admin, 'GET', "/api/admin/vehicles?$filter=1")->assertOk()->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.transportlidzeklis_id', $id);
        }
        $client = $this->account('klients');
        $reservationId = $this->api($client, 'POST', '/api/rezervacijas', [
            'transportlidzeklis_id' => $id, 'sakuma_laiks' => now()->addDays(3)->toISOString(),
            'beigu_laiks' => now()->addDays(4)->toISOString(),
        ])->assertCreated()->json('rezervacija_id');
        $this->api($this->owner, 'PUT', $path, ['statuss' => 'neaktivs'])->assertOk();
        $this->api($this->owner, 'PUT', $path, ['statuss' => 'pieejams'])->assertUnprocessable()
            ->assertJsonValidationErrors([DrivingLicence::FIELD, 'photos']);
        $this->api($client, 'POST', "/api/rezervacijas/$reservationId/pay")
            ->assertOk()->assertJsonPath('rezervacija.apmaksas_statuss', 'apmaksata');
        $this->api($this->owner, 'PUT', $path, [DrivingLicence::FIELD => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors(DrivingLicence::FIELD);
        $photo = $this->api($this->owner, 'POST', "$path/photos", ['photos' => [VehicleImages::file()]])->assertCreated()->json('0.foto_id');
        $this->api(null, 'GET', "$path/public-photos/$photo")->assertNotFound();
        $this->api($this->owner, 'PUT', $path, ['statuss' => 'pieejams', DrivingLicence::FIELD => 'NONE'])->assertOk();
        $this->api(null, 'GET', "$path/public-photos/$photo")->assertOk();
        $this->assertDatabaseHas('rezervacija', ['rezervacija_id' => $reservationId, 'transportlidzeklis_id' => $id]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'veids_id' => $this->categoryId,
            'marka' => 'Toyota',
            'modelis' => 'Yaris',
            'atrumkarba' => 'Automāts',
            'degvielas_veids' => 'Benzīns',
            'dienas_nomas_cena' => 35,
            'statuss' => 'pieejams',
            'registracijas_numurs' => uniqid('QA-'),
            DrivingLicence::FIELD => 'B',
            'photos' => [VehicleImages::file()],
        ];
    }

    private function legacy(array $overrides = []): Transportlidzeklis
    {
        $data = $this->payload();
        unset($data['photos'], $data[DrivingLicence::FIELD]);

        return $this->owner->pakalpojumuSniedzejs->transportlidzekli()->create($overrides + $data + ['adrese' => 'Testa 1, Rīga']);
    }

    private function account(string $role): Persona
    {
        $person = Persona::forceCreate([
            'vards' => 'Test', 'uzvards' => 'Account', 'epasts' => uniqid().'@example.test',
            'loma' => $role, 'parole' => Hash::make('Secret123!'), 'email_verified_at' => now(),
        ]);
        if ($role === 'pakalpojumu_sniedzejs') {
            $person->pakalpojumuSniedzejs()->create(['iela' => 'Testa', 'majas_numurs' => '1', 'pilseta' => 'Rīga']);
        }
        if ($role === 'klients') {
            $person->klients()->create([]);
        }

        return $person;
    }

    private function api(?Persona $user, string $method, string $url, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        $headers = $user ? ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken] : [];

        return $method === 'POST'
            ? $this->post($url, $data, $headers + ['Accept' => 'application/json'])
            : $this->json($method, $url, $data, $headers);
    }
}
