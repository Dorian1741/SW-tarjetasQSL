<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = ['user_id', 'event_key', 'kind', 'payload', 'status', 'attempts', 'last_error', 'delivered_at', 'read_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'immutable_datetime', 'read_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
