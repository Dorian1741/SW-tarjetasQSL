<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StationCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $private = $request->routeIs('station-cards.*');

        return [
            'public_id' => $this->public_id, 'title' => $this->title, 'description' => $this->description,
            'callsign' => $this->callsign->normalized_callsign, 'source' => $this->source,
            'is_public' => $this->is_public, 'created_at' => $this->created_at,
            'image_url' => '/api/v1/'.($private ? '' : 'public/').'station-cards/'.$this->public_id.'/image?v='.substr(hash('sha256', $this->file_path), 0, 12),
            ...($private ? ['callsign_id' => $this->callsign_id, 'design' => $this->design] : []),
        ];
    }
}
