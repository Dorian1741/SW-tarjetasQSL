<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Qso extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'callsign_id', 'contacted_callsign', 'normalized_contacted_callsign', 'frequency_khz', 'band', 'mode', 'started_at', 'ended_at', 'rst_sent', 'rst_received', 'notes', 'status'];

    protected function casts(): array
    {
        return [
            'frequency_khz' => 'decimal:3',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callsign(): BelongsTo
    {
        return $this->belongsTo(Callsign::class);
    }
}
