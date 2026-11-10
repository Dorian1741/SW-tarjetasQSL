<?php

namespace Tests\Feature;

use App\Models\Callsign;
use App\Models\QsoMatch;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || getenv('QSL_INTEGRATION') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL aislado y QSL_INTEGRATION=1.');
        }
        if (! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            $this->fail('La base de integración debe terminar en _test.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->app && DB::getDriverName() === 'pgsql' && getenv('QSL_INTEGRATION') === '1' && str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        parent::tearDown();
    }

    private function concurrent(string $action, array $payloads): array
    {
        $processes = [];
        foreach ($payloads as $data) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/concurrent.php'), $action, json_encode($data)], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password'), 'QUEUE_CONNECTION' => 'database', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'log']);
            $p->setTimeout(30);
            $p->start();
            $processes[] = $p;
        }
        $results = [];
        foreach ($processes as $p) {
            $p->wait();
            $this->assertTrue($p->isSuccessful(), $p->getErrorOutput().$p->getOutput());
            $results[] = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR)['result'];
        }

return $results;
    }

    public function test_concurrent_qsos_confirmations_and_cards_remain_unique(): void
    {
        $a = Callsign::factory()->create(['verification_status' => 'verified']);
        $b = Callsign::factory()->create(['verification_status' => 'verified']);
        $payloads = [];
        foreach ([[$a, $b], [$b, $a]] as [$mine,$other]) {
            $payloads[] = ['user' => $mine->user_id, 'payload' => ['callsign_id' => $mine->id, 'contacted_callsign' => $other->callsign, 'frequency_khz' => '14074.125', 'band' => '20m', 'mode' => 'FT8', 'started_at' => '2026-01-01T12:00:00Z']];
        }
        $this->concurrent('qso', $payloads);
        $this->assertDatabaseCount('qso_matches', 1);
        $match = QsoMatch::firstOrFail();
        $this->concurrent('confirm', [['user' => $a->user_id, 'match' => $match->id], ['user' => $b->user_id, 'match' => $match->id], ['user' => $a->user_id, 'match' => $match->id]]);
        $this->assertDatabaseCount('qso_confirmations', 2);
        $this->assertSame('confirmed', $match->fresh()->status);
        $ids = $this->concurrent('issue', [['user' => $a->user_id, 'match' => $match->id], ['user' => $a->user_id, 'match' => $match->id]]);
        $this->assertSame($ids[0], $ids[1]);
        $this->assertDatabaseCount('qsl_cards', 1);
    }

    public function test_two_sessions_cannot_consume_the_same_recovery_code(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $data = ['user' => $user->id, 'code' => $user->recoveryCodes()[0]];
        $results = $this->concurrent('recover', [$data, $data]);
        sort($results);
        $this->assertSame([false, true],$results);
    }
}
