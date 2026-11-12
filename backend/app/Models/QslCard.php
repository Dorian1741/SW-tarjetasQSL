<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QslCard extends Model
{
    protected $fillable = ['public_id', 'qso_match_id', 'sender_user_id', 'receiver_user_id', 'template', 'station_card_id', 'file_path', 'status', 'generated_at', 'sent_at'];

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(QsoMatch::class, 'qso_match_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function stationDesign(): BelongsTo
    {
        return $this->belongsTo(StationCard::class, 'station_card_id');
    }
}
