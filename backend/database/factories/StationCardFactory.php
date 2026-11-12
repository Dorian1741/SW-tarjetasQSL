<?php

namespace Database\Factories;

use App\Models\StationCard;
use App\Models\Callsign;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StationCard>
 */
class StationCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'callsign_id' => Callsign::factory(),
            'user_id' => fn (array $attributes) => Callsign::findOrFail($attributes['callsign_id'])->user_id,
            'public_id' => (string) Str::uuid(),
            'title' => 'Tarjeta de prueba',
            'description' => null,
            'source' => 'upload',
            'file_path' => 'station-cards/'.Str::uuid().'.png',
            'design' => null,
            'is_public' => false,
        ];
    }
}
