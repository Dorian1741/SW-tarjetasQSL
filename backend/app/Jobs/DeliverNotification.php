<?php

namespace App\Jobs;

use App\Mail\DomainNotificationMail;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $delivery = NotificationDelivery::whereKey($this->deliveryId)->lockForUpdate()->firstOrFail();
            if ($delivery->status === 'sent') {
                return;
            }
            $delivery->increment('attempts');
            Mail::to($delivery->user->email)->send(new DomainNotificationMail($delivery->kind, $delivery->payload));
            $delivery->update(['status' => 'sent', 'delivered_at' => now(), 'last_error' => null]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->update(['status' => 'failed', 'attempts' => $this->tries, 'last_error' => 'No se pudo entregar el correo. Consultar failed_jobs.']);
    }
}
