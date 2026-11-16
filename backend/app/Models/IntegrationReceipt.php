<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationReceipt extends Model
{
    protected $fillable = ['integration_credential_id', 'external_id', 'request_hash', 'qso_id'];

    public function qso(): BelongsTo
    {
        return $this->belongsTo(Qso::class);
    }
}
