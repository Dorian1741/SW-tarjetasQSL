<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnershipRequest extends Model
{
    protected $fillable = ['user_id', 'callsign_id', 'evidence', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime'];
    }
}
