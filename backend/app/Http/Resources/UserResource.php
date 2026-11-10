<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->only(['id', 'name', 'email', 'email_verified_at', 'preferred_language', 'timezone', 'country_code']), 'is_admin' => (bool) $this->is_admin, 'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication()];
    }
}
