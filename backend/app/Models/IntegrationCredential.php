<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCredential extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'callsign_id', 'name', 'token_hash', 'security_fingerprint', 'abilities', 'expires_at', 'revoked_at', 'last_used_at'];

    protected $hidden = ['token_hash', 'security_fingerprint'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callsign(): BelongsTo
    {
        return $this->belongsTo(Callsign::class);
    }

    public static function fingerprint(User $user): string
    {
        return hash('sha256', $user->password.'|'.$user->two_factor_secret);
    }

    public function usable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture() && $this->user->status === 'active' && $this->user->hasVerifiedEmail() && $this->user->hasEnabledTwoFactorAuthentication() && $this->callsign->reserved_callsign !== null && $this->callsign->verification_status === 'verified' && hash_equals($this->security_fingerprint, self::fingerprint($this->user));
    }
}
