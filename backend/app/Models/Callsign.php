<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Callsign extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'callsign', 'normalized_callsign', 'reserved_callsign', 'country_code', 'verification_status', 'is_primary', 'verified_at', 'reviewed_by', 'reviewed_at', 'review_reason'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qsos(): HasMany
    {
        return $this->hasMany(Qso::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
