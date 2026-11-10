<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmailQueued)->afterCommit());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordQueued($token))->afterCommit());
    }

    public function callsigns(): HasMany
    {
        return $this->hasMany(Callsign::class);
    }

    public function qsos(): HasMany
    {
        return $this->hasMany(Qso::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'is_admin' => 'boolean',
        ];
    }
}
