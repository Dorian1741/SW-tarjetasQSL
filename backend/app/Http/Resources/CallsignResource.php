<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallsignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['released' => $this->reserved_callsign === null, ...$this->only(['id', 'callsign', 'country_code', 'verification_status', 'is_primary', 'verified_at', 'reviewed_at', 'review_reason'])];
    }
}
