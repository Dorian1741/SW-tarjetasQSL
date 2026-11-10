<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mine = $this->qsoA->user_id === $request->user()->id;

        return [...$this->only(['id', 'status', 'time_difference_seconds', 'matched_at', 'confirmed_at']),
            'my_qso_id' => $mine ? $this->qso_a_id : $this->qso_b_id,
            'my_callsign' => $mine ? $this->qsoA->callsign->callsign : $this->qsoB->callsign->callsign,
            'other_callsign' => $mine ? $this->qsoB->callsign->callsign : $this->qsoA->callsign->callsign,
            'band' => $this->qsoA->band, 'mode' => $this->qsoA->mode, 'started_at' => $this->qsoA->started_at,
            'my_confirmation' => (bool) ($mine ? $this->a_confirmed_at : $this->b_confirmed_at),
            'other_confirmation' => (bool) ($mine ? $this->b_confirmed_at : $this->a_confirmed_at),
            'explanation' => 'Indicativos recíprocos, propietarios distintos, banda y modo iguales, diferencia de inicio <= 30 minutos y candidato único en ambos sentidos.'];
    }
}
