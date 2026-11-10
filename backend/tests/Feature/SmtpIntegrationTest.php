<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SmtpIntegrationTest extends TestCase
{
    public function test_worker_sends_real_smtp_to_local_receiver_and_records_delivery(): void
    {
        if (getenv('QSL_INTEGRATION') !== '1' || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere integración PostgreSQL/SMTP local.');
        }
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        Artisan::call('migrate:fresh', ['--force' => true]);
        $path = tempnam(sys_get_temp_dir(), 'qsl-smtp-');
        $process = new Process(['python3', base_path('tests/Support/smtp_sink.py'), $path]);
        $process->start();
        try {
            $process->waitUntil(fn ($type, $output) => is_numeric(trim($output)));
            $port = (int) trim($process->getOutput());
            $this->assertGreaterThan(0, $port);
            config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => $port, 'mail.mailers.smtp.scheme' => null, 'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.timeout' => 5, 'queue.default' => 'database']);
            Mail::purge('smtp');
            $user = User::factory()->create(['email' => 'smtp-test@example.test']);
            app(NotificationService::class)->send($user->id, 'smtp-real', 'confirmed', ['message' => 'TEST0A / TEST0B', 'path' => '/app/matches']);
            $this->assertDatabaseCount('jobs', 1);
            $this->artisan('queue:work', ['--once' => true, '--sleep' => 0, '--tries' => 1, '--force' => true])->assertExitCode(0);
            $this->assertSame('sent', NotificationDelivery::firstOrFail()->status);
            $this->assertStringContainsString('smtp-test@example.test', file_get_contents($path));
            $this->assertStringContainsString('TEST0A / TEST0B', file_get_contents($path));
        } finally {
            $process->stop();
            unlink($path);
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }
}
