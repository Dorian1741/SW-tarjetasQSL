<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorProfile extends Model
{
    protected $fillable = ['user_id', 'callsign_id', 'biography', 'photo_path', 'country_code', 'links', 'is_public', 'show_qsl_history'];

    protected function casts(): array
    {
        return ['links' => 'array', 'is_public' => 'boolean', 'show_qsl_history' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callsign(): BelongsTo
    {
        return $this->belongsTo(Callsign::class);
    }

    public function isVisible(): bool
    {
        return $this->is_public && $this->user->status === 'active' && $this->user->hasVerifiedEmail() && $this->callsign->reserved_callsign !== null && $this->callsign->verification_status === 'verified';
    }

    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('is_public', true)
            ->whereHas('user', fn (Builder $user) => $user->where('status', 'active')->whereNotNull('email_verified_at'))
            ->whereHas('callsign', fn (Builder $callsign) => $callsign->whereNotNull('reserved_callsign')->where('verification_status', 'verified'));
    }
}
