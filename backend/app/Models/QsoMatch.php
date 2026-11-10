<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QsoMatch extends Model
{
    protected $fillable = ['qso_a_id', 'qso_b_id', 'time_difference_seconds', 'status', 'matched_at', 'a_confirmed_at', 'b_confirmed_at', 'confirmed_at'];

    protected function casts(): array
    {
        return [
            'matched_at' => 'immutable_datetime',
            'a_confirmed_at' => 'immutable_datetime',
            'b_confirmed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function qsoA(): BelongsTo
    {
        return $this->belongsTo(Qso::class, 'qso_a_id');
    }

    public function qsoB(): BelongsTo
    {
        return $this->belongsTo(Qso::class, 'qso_b_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(QslCard::class);
    }
}
