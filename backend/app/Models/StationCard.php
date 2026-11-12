<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationCard extends Model
{
    /** @use HasFactory<\Database\Factories\StationCardFactory> */
    use HasFactory;

    protected $fillable = ['public_id', 'user_id', 'callsign_id', 'title', 'description', 'source', 'file_path', 'design', 'is_public'];

    protected function casts(): array
    {
        return ['design' => 'array', 'is_public' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callsign(): BelongsTo
    {
        return $this->belongsTo(Callsign::class);
    }

    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('is_public', true)
            ->whereHas('user', fn (Builder $user) => $user->where('status', 'active')->whereNotNull('email_verified_at'))
            ->whereHas('callsign', fn (Builder $callsign) => $callsign->whereNotNull('reserved_callsign')->where('verification_status', 'verified'));
    }
}
