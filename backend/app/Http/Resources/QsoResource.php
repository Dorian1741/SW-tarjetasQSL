<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QsoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->only(['id', 'callsign_id', 'contacted_callsign', 'frequency_khz', 'band', 'mode', 'started_at', 'ended_at', 'rst_sent', 'rst_received', 'notes', 'status']), 'my_callsign' => $this->callsign->callsign];
    }
}
