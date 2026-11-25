<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Support\AdminChanges;
use App\Models\TransportliedzieklsVeids;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Fixtures\VehicleImages;
use Tests\TestCase;

class AccountConcurrencyTest extends TestCase
{
    private const PASSWORD = 'Secret123!';

    #[Group('mysql-race')]
    public function test_booking_and_account_deletion_are_serialized_for_both_parties(): void
    {
        $this->migrateDisposableDatabase();

        foreach (['klients', 'pakalpojumu_sniedzejs'] as $role) {
            foreach ([true, false] as $deleteFirst) {
                $this->assertRace($role, $deleteFirst);
            }
        }
    }

    #[Group('mysql-race')]
    public function test_simultaneous_email_confirmations_issue_only_one_token(): void
    {
        $this->migrateDisposableDatabase();
        $frontend = 'https://easyrent.vip';
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.frontend_url' => $frontend,
            'mail.default' => 'array',
            'session.driver' => 'database',
            'sanctum.stateful' => ['easyrent.vip'],
        ]);
        $headers = ['Referer' => $frontend.'/verify-email'];
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Race Tester',
            'email' => 'activation@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'loma' => 'klients',
            'lietotajvards' => 'activationrace',
            'kontakttalrunis' => '+37120000000',
            'bankas_konts' => 'LV80BANK0000435195001',
        ], $headers)->assertCreated();
        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $response->getCookie($cookie)->getValue());
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        preg_match('/verify-email#([^\s]+)/', $mail->getTextBody(), $fragment);
        parse_str($fragment[1], $link);
        $path = '/api/auth/verification/confirm';

        $worker = $this->worker([
            'method' => 'POST',
            'path' => $path,
            'body' => $link,
            'referer' => $headers['Referer'],
            'cookies' => [$cookie => $response->getCookie($cookie, false)->getValue()],
        ], [
            'APP_KEY' => config('app.key'),
            'APP_ENV' => 'testing',
            'SESSION_DRIVER' => 'database',
            'SANCTUM_STATEFUL_DOMAINS' => 'easyrent.vip',
        ]);

        try {
            DB::transaction(function () use ($link, $worker, $path, $headers) {
                Persona::lockActiveAccounts((int) $link['id']);
                $worker->start();
                $reachedLock = $worker->waitUntil(fn ($type, $lines) => str_contains($lines, "WAITING\n"));
                $this->assertTrue($reachedLock, $worker->getErrorOutput().$worker->getOutput());
                $this->assertTrue($worker->isRunning());
                $this->postJson($path, $link, $headers)->assertOk()->assertJsonStructure(['token', 'persona']);
            });

            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('STATUS=422', $worker->getOutput());
            $this->assertStringContainsString('invalid_verification_link', $worker->getOutput());
            $this->assertDatabaseCount('personal_access_tokens', 1);
            $this->assertDatabaseEmpty('email_verification_tokens');
        } finally {
            $worker->stop(0);
        }
    }

    #[Group('mysql-race')]
    public function test_moderation_is_serialized_with_booking_payment_and_other_administrators(): void
    {
        $this->migrateDisposableDatabase();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $admin = $this->account('administrators');
        $otherAdmin = $this->account('administrators');
        $client = $this->account('klients');
        $provider = $this->account('pakalpojumu_sniedzejs');
        $client->klients()->create([]);
        $type = TransportliedzieklsVeids::create(['nosaukums' => 'Auto', 'tips' => 'auto']);
        $vehicle = $provider->pakalpojumuSniedzejs()->create([])->transportlidzekli()->create([
            'veids_id' => $type->getKey(), 'marka' => 'BMW', 'modelis' => '320d',
            'dienas_nomas_cena' => 40, 'adrese' => 'Rīga', 'statuss' => 'pieejams',
            'registracijas_numurs' => 'TEST-42',
        ]);
        $adminToken = $admin->createToken('moderation')->plainTextToken;
        $otherToken = $otherAdmin->createToken('moderation')->plainTextToken;
        $clientToken = $client->createToken('booking')->plainTextToken;
        $vehicleId = $vehicle->getKey();
        $book = [
            'transportlidzeklis_id' => $vehicleId,
            'sakuma_laiks' => now()->addDay()->toISOString(),
            'beigu_laiks' => now()->addDays(2)->toISOString(),
        ];

        $this->assertConcurrentRequest(
            ['method' => 'POST', 'path' => '/api/rezervacijas', 'body' => $book, 'token' => $clientToken, 'wait_for_vehicle' => true],
            function (Process $worker) use ($vehicle, $adminToken, $vehicleId) {
                $vehicle->newQuery()->whereKey($vehicleId)->lockForUpdate()->firstOrFail();
                $this->startBlockedRequest($worker);
                $this->patchJson("/api/admin/vehicles/$vehicleId/visibility", ['hidden' => true, 'iemesls' => 'Concurrent hide'],
                    ['Authorization' => 'Bearer '.$adminToken])->assertOk();
            },
            409
        );
        $this->assertDatabaseEmpty('rezervacija');
        Auth::forgetGuards();
        $this->patchJson("/api/admin/vehicles/$vehicleId/visibility", ['hidden' => false, 'iemesls' => 'Restored'],
            ['Authorization' => 'Bearer '.$adminToken])->assertOk();
        Auth::forgetGuards();
        $bookingId = $this->postJson('/api/rezervacijas', $book, ['Authorization' => 'Bearer '.$clientToken])
            ->assertCreated()->json('rezervacija_id');
        $reason = ['duration' => 'indefinite', 'iemesls' => 'Concurrent restriction'];

        $this->assertConcurrentRequest(
            ['method' => 'POST', 'path' => "/api/rezervacijas/$bookingId/pay", 'body' => [], 'token' => $clientToken],
            function (Process $worker) use ($client, $adminToken, $reason) {
                Persona::lockActiveAccounts($client->getKey());
                $this->startBlockedRequest($worker);
                $this->postJson('/api/admin/users/'.$client->getKey().'/restrictions', ['veids' => 'rezervaciju_aizliegums'] + $reason,
                    ['Authorization' => 'Bearer '.$adminToken])->assertCreated();
            },
            403
        );
        $this->assertDatabaseEmpty('maksajums');
        $this->assertDatabaseHas('rezervacija', ['rezervacija_id' => $bookingId, 'apmaksas_statuss' => 'neapmaksata']);

        $block = ['veids' => 'konta_blokesana'] + $reason;
        $this->assertConcurrentRequest(
            ['method' => 'POST', 'path' => '/api/admin/users/'.$otherAdmin->getKey().'/restrictions', 'body' => $block, 'token' => $otherToken],
            function (Process $worker) use ($admin, $block, $adminToken) {
                AdminChanges::lockAdministrators();
                $this->startBlockedRequest($worker);
                $this->postJson('/api/admin/users/'.$admin->getKey().'/restrictions', $block,
                    ['Authorization' => 'Bearer '.$adminToken])->assertCreated();
            },
            409
        );
        $this->assertSame(0, $otherAdmin->restrictions()->blocking()->count());
    }

    #[Group('mysql-race')]
    public function test_concurrent_photo_writes_keep_the_listing_between_one_and_four_photos(): void
    {
        $this->migrateDisposableDatabase();
        $owner = $this->account('pakalpojumu_sniedzejs');
        $type = TransportliedzieklsVeids::create(['nosaukums' => 'Auto', 'tips' => 'auto']);
        $vehicle = $owner->pakalpojumuSniedzejs()->create([])->transportlidzekli()->create([
            'veids_id' => $type->getKey(), 'marka' => 'BMW', 'modelis' => '320d', 'dienas_nomas_cena' => 40,
            'adrese' => 'Rīga', 'statuss' => 'pieejams', 'registracijas_numurs' => 'QA-42',
        ]);
        $path = '/api/transport/'.$vehicle->getKey().'/photos';
        $token = $owner->createToken('photo-race')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
        $this->post($path, ['photos' => array_fill(0, 3, VehicleImages::file())], $headers)->assertCreated();

        $this->assertConcurrentRequest(
            ['method' => 'POST', 'path' => $path, 'token' => $token, 'body' => [], 'photo_upload' => true],
            function (Process $worker) use ($owner, $path, $headers) {
                Persona::whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
                $this->startBlockedRequest($worker);
                $this->post($path, ['photos' => [VehicleImages::file()]], $headers)->assertCreated();
            },
            422
        );
        $ids = $vehicle->photos()->pluck('foto_id');
        $this->assertCount(4, $ids);
        foreach ($ids->take(2) as $photo) {
            $this->deleteJson("$path/$photo", [], $headers)->assertNoContent();
        }

        $this->assertConcurrentRequest(
            ['method' => 'DELETE', 'path' => "$path/{$ids[2]}", 'token' => $token, 'body' => []],
            function (Process $worker) use ($owner, $path, $headers, $ids) {
                Persona::whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
                $this->startBlockedRequest($worker);
                $this->deleteJson("$path/{$ids[3]}", [], $headers)->assertNoContent();
            },
            422
        );
        $this->assertSame([$ids[2]], $vehicle->photos()->pluck('foto_id')->all());
    }

    private function assertConcurrentRequest(array $request, \Closure $whileBlocked, int $status): void
    {
        $worker = $this->worker($request, ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key')]);
        try {
            DB::transaction(fn () => $whileBlocked($worker));
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('STATUS='.$status, $worker->getOutput());
        } finally {
            $worker->stop(0);
        }
    }

    private function startBlockedRequest(Process $worker): void
    {
        $worker->start();
        $this->assertTrue($worker->waitUntil(fn ($type, $lines) => str_contains($lines, "WAITING\n")),
            $worker->getErrorOutput().$worker->getOutput());
        $this->assertTrue($worker->isRunning());
        Auth::forgetGuards();
    }

    private function migrateDisposableDatabase(): void
    {
        if (getenv('EASYRENT_TEST_MYSQL_RACE') !== '1') {
            $this->markTestSkipped('Requires an empty, local and disposable MySQL/MariaDB database.');
        }

        $connection = DB::connection();
        $this->assertContains($connection->getDriverName(), ['mysql', 'mariadb']);
        $this->assertContains($connection->getConfig('host'), ['127.0.0.1', 'localhost']);
        $this->assertMatchesRegularExpression('/^easyrent_qa_[a-z0-9_]+$/D', $connection->getDatabaseName());
        $this->assertSame([], Schema::getTables($connection->getDatabaseName()), 'Use a new empty database for the race test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function assertRace(string $role, bool $deleteFirst): void
    {
        // The targeted account is created first, so it is the first lock for both requests.
        $target = $this->account($role);
        $other = $this->account($role === 'klients' ? 'pakalpojumu_sniedzejs' : 'klients');
        [$client, $provider] = $role === 'klients' ? [$target, $other] : [$other, $target];
        $client->klients()->create([]);
        $type = TransportliedzieklsVeids::create(['nosaukums' => uniqid('Auto-'), 'tips' => 'auto']);
        $vehicle = $provider->pakalpojumuSniedzejs()->create([])->transportlidzekli()->create([
            'veids_id' => $type->getKey(), 'marka' => 'BMW', 'modelis' => '320d',
            'dienas_nomas_cena' => 40, 'adrese' => 'Rīga', 'statuss' => 'pieejams',
            'registracijas_numurs' => uniqid('QA-'),
        ]);
        $delete = [
            'method' => 'DELETE',
            'path' => '/api/profile/me',
            'token' => $target->createToken('deletion')->plainTextToken,
            'body' => ['confirmation' => 'DZĒST', 'password' => self::PASSWORD],
        ];
        $book = [
            'method' => 'POST',
            'path' => '/api/rezervacijas',
            'token' => $client->createToken('booking')->plainTextToken,
            'body' => [
                'transportlidzeklis_id' => $vehicle->getKey(),
                'sakuma_laiks' => now()->addDay()->toISOString(),
                'beigu_laiks' => now()->addDays(2)->toISOString(),
            ],
        ];
        [$first, $second] = $deleteFirst ? [$delete, $book] : [$book, $delete];
        $worker = $this->worker($second);

        try {
            DB::transaction(function () use ($target, $worker, $first, $deleteFirst) {
                Persona::lockActiveAccounts($target->getKey());
                $worker->start();
                $reachedLock = $worker->waitUntil(fn ($type, $lines) => str_contains($lines, "WAITING\n"));
                $this->assertTrue($reachedLock, $worker->getErrorOutput().$worker->getOutput());
                $this->assertTrue($worker->isRunning(), 'The second request is expected to wait for the first transaction.');

                Auth::forgetGuards();
                $this->withToken($first['token'])->json($first['method'], $first['path'], $first['body'])
                    ->assertStatus($deleteFirst ? 204 : 201);
            });

            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('STATUS=409', $worker->getOutput());
            $this->assertSame($deleteFirst ? 0 : 1, $vehicle->rezervacijas()->count());
            $this->assertSame($deleteFirst, Persona::find($target->getKey()) === null);
        } finally {
            $worker->stop(0);
        }
    }

    private function account(string $role): Persona
    {
        return Persona::forceCreate([
            'epasts' => uniqid('qa-').'@example.test',
            'parole' => Hash::make(self::PASSWORD), 'loma' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function worker(array $request, array $env = []): Process
    {
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            $data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
            Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($sql) use ($data) {
                $accountLock = str_contains($sql, 'from `persona`') && str_ends_with($sql, 'for update');
                $vehicleWrite = ($data['wait_for_vehicle'] ?? false) && str_starts_with($sql, 'update `transportlidzeklis`');
                if ($accountLock || $vehicleWrite) {
                    echo "WAITING\n";
                    flush();
                }
            });
            $server = [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ];
            if (isset($data['token'])) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer '.$data['token'];
            }
            if (isset($data['referer'])) {
                $server['HTTP_REFERER'] = $data['referer'];
            }
            if ($data['photo_upload'] ?? false) {
                $server['CONTENT_TYPE'] = 'multipart/form-data';
                $request = Illuminate\Http\Request::create($data['path'], $data['method'],
                    files: ['photos' => [Tests\Fixtures\VehicleImages::file()]], server: $server);
            } else {
                $request = Illuminate\Http\Request::create($data['path'], $data['method'],
                    cookies: $data['cookies'] ?? [], server: $server, content: json_encode($data['body'], JSON_THROW_ON_ERROR));
            }
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            echo 'STATUS='.$response->getStatusCode()."\n".$response->getContent();
            PHP;

        return new Process([PHP_BINARY, '-r', $script, json_encode($request, JSON_THROW_ON_ERROR)], base_path(), $env, timeout: 15);
    }
}
