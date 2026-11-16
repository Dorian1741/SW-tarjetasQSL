<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->only(['id', 'name', 'abilities', 'expires_at', 'revoked_at', 'last_used_at', 'created_at']), 'callsign' => $this->callsign->callsign, 'callsign_id' => $this->callsign_id];
    }
}
