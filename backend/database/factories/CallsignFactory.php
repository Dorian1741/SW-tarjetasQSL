<?php

namespace Database\Factories;

use App\Models\Callsign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Callsign> */
class CallsignFactory extends Factory
{
    public function definition(): array
    {
        $callsign = 'TEST0'.fake()->unique()->regexify('[A-Z]{5}');

        return [
            'user_id' => User::factory(),
            'callsign' => $callsign,
            'normalized_callsign' => $callsign,
            'reserved_callsign' => $callsign,
            'verification_status' => 'pending',
            'is_primary' => false,
        ];
    }
}
