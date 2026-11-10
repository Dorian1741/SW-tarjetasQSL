<?php

namespace App\Services;

use App\Jobs\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Models\QsoMatch;

class NotificationService
{
    public function send(int $userId, string $key, string $kind, array $payload): void
    {
        $delivery = NotificationDelivery::firstOrCreate(['event_key' => $key.'-'.$userId], ['user_id' => $userId, 'kind' => $kind, 'payload' => $payload]);
        if ($delivery->wasRecentlyCreated) {
            DeliverNotification::dispatch($delivery->id)->afterCommit();
        }
    }

    public function match(QsoMatch $match, string $kind): void
    {
        $match->loadMissing('qsoA.callsign', 'qsoB.callsign');
        foreach ([$match->qsoA->user_id, $match->qsoB->user_id] as $userId) {
            $this->send($userId, $kind.'-'.$match->id, $kind, ['message' => $match->qsoA->callsign->callsign.' / '.$match->qsoB->callsign->callsign, 'path' => '/app/matches']);
        }
    }
}
