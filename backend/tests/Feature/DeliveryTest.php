<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotification;
use App\Mail\DomainNotificationMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_queued_idempotent_and_delivered_once(): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->create();
        $service = app(NotificationService::class);
        $service->send($user->id, 'test-event', 'qsl', ['message' => 'TEST0A / TEST0B', 'path' => '/app/cards']);
        $service->send($user->id, 'test-event', 'qsl', ['message' => 'TEST0A / TEST0B', 'path' => '/app/cards']);
        Queue::assertPushed(DeliverNotification::class, 1);
        $delivery = NotificationDelivery::firstOrFail();
        (new DeliverNotification($delivery->id))->handle();
        (new DeliverNotification($delivery->id))->handle();
        Mail::assertSent(DomainNotificationMail::class, 1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
    }

    public function test_mail_escapes_user_supplied_content(): void
    {
        $html = (new DomainNotificationMail('qsl', ['message' => '<script>alert(1)</script>', 'path' => '/app/cards']))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_terminal_failure_is_recorded(): void
    {
        $delivery = NotificationDelivery::create(['user_id' => User::factory()->create()->id, 'event_key' => 'failed', 'kind' => 'qsl', 'payload' => ['message' => 'test', 'path' => '/app']]);
        (new DeliverNotification($delivery->id))->failed(new \RuntimeException('SMTP unavailable'));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->last_error);
    }
}
