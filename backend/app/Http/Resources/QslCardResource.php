<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QslCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->only(['public_id', 'status', 'generated_at', 'sent_at']),
            'callsign_a' => $this->match->qsoA->callsign->callsign, 'callsign_b' => $this->match->qsoB->callsign->callsign,
            'started_at' => $this->match->qsoA->started_at, 'band' => $this->match->qsoA->band, 'frequency_khz' => $this->match->qsoA->frequency_khz, 'mode' => $this->match->qsoA->mode,
            'confirmation_status' => $this->match->status, 'verification_url' => rtrim(config('qsl.frontend_url'), '/').'/verify/'.$this->public_id];
    }
}
