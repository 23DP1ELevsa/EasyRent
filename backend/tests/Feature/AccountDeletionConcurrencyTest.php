<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\TransportliedzieklsVeids;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountDeletionConcurrencyTest extends TestCase
{
    private const PASSWORD = 'Secret123!';

    #[Group('mysql-race')]
    public function test_booking_and_account_deletion_are_serialized_for_both_parties(): void
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

        foreach (['klients', 'pakalpojumu_sniedzejs'] as $role) {
            foreach ([true, false] as $deleteFirst) {
                $this->assertRace($role, $deleteFirst);
            }
        }
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

    private function worker(array $request): Process
    {
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($sql) {
                if (str_contains($sql, 'from `persona`') && str_ends_with($sql, 'for update')) {
                    echo "WAITING\n";
                    flush();
                }
            });
            $data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
            $request = Illuminate\Http\Request::create($data['path'], $data['method'], server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$data['token'],
                'CONTENT_TYPE' => 'application/json',
            ], content: json_encode($data['body'], JSON_THROW_ON_ERROR));
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            echo 'STATUS='.$response->getStatusCode()."\n".$response->getContent();
            PHP;

        return new Process([PHP_BINARY, '-r', $script, json_encode($request, JSON_THROW_ON_ERROR)], base_path(), timeout: 15);
    }
}
