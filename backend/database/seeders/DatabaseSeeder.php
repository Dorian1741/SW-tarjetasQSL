<?php

namespace Database\Seeders;

use App\Models\Qso;
use App\Models\User;
use App\Services\CallsignService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos de demostración solo se permiten en local/testing.');
        }

        $stations = [];
        foreach (['A', 'B'] as $suffix) {
            $user = User::firstOrCreate(['email' => 'demo-'.strtolower($suffix).'@example.test'], [
                'name' => 'Estación ficticia '.$suffix,
                'password' => Hash::make('DemoPassword123!'),
            ]);
            $callsign = $user->callsigns()->first() ?? app(CallsignService::class)->claim($user, 'TEST0DEMO'.$suffix);
            $stations[] = [$user, $callsign];
        }
        foreach ([0, 1] as $index) {
            [$user, $callsign] = $stations[$index];
            $otherCallsign = $stations[1 - $index][1]->callsign;
            Qso::firstOrCreate([
                'user_id' => $user->id,
                'callsign_id' => $callsign->id,
                'normalized_contacted_callsign' => $otherCallsign,
                'started_at' => '2026-01-01 12:00:00',
            ], [
                'contacted_callsign' => $otherCallsign,
                'frequency_khz' => '14074.000',
                'band' => '20m',
                'mode' => 'FT8',
                'notes' => 'DATO DE PRUEBA. Contacto ficticio; no representa una estación real.',
            ]);
        }
    }
}
