<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class OperatorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['callsign' => $this->callsign->normalized_callsign, 'format_valid' => true, 'ownership_verified' => $this->callsign->verification_status === 'verified',
            'biography' => $this->biography, 'country_code' => $this->country_code, 'links' => $this->links ?? [],
            'is_public' => $this->is_public, 'show_qsl_history' => $this->show_qsl_history,
            'photo_url' => $this->photo_path ? '/api/v1/public/operator-photos/'.$this->id : null,
            'followers' => DB::table('operator_follows')->where('followed_id', $this->user_id)->count(),
            'following' => DB::table('operator_follows')->where('follower_id', $this->user_id)->count(),
            'public_url' => '/operators/'.rawurlencode($this->callsign->normalized_callsign)];
    }
}
