<?php

namespace Database\Factories;

use App\Models\Callsign;
use App\Models\Qso;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Qso> */
class QsoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'callsign_id' => Callsign::factory(),
            'user_id' => fn (array $attributes) => Callsign::findOrFail($attributes['callsign_id'])->user_id,
            'contacted_callsign' => 'TEST0OTHER',
            'normalized_contacted_callsign' => 'TEST0OTHER',
            'frequency_khz' => '14074.125',
            'band' => '20m',
            'mode' => 'FT8',
            'started_at' => '2026-01-01 12:00:00',
            'status' => 'pending',
            'notes' => 'DATO DE PRUEBA: contacto ficticio.',
        ];
    }
}
