<?php

namespace Database\Seeders;

use App\Models\QsoMatch;
use App\Models\User;
use App\Services\CallsignService;
use App\Services\QslService;
use App\Services\QsoMatchingService;
use App\Services\QsoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Escenario de demostración solo en local/testing.');
        }
        $admin = User::firstOrCreate(['email' => 'scenario-admin@example.test'], ['name' => 'Administrador ficticio', 'password' => Hash::make('DemoPassword123!')]);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        $stations = [];
        foreach (['A', 'B'] as $suffix) {
            $user = User::firstOrCreate(['email' => 'scenario-'.strtolower($suffix).'@example.test'], ['name' => 'Estación ficticia '.$suffix, 'password' => Hash::make('DemoPassword123!')]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $service = app(CallsignService::class);
            $callsign = $user->callsigns()->whereNotNull('reserved_callsign')->first() ?? $service->claim($user, 'TEST0FULL'.$suffix);
            if ($callsign->verification_status !== 'verified') {
                $service->review($admin, $callsign, 'verified', 'DATO DE PRUEBA: aprobación ficticia exclusiva para demostración local.');
            }
            $stations[] = [$user, $callsign];
        }
        foreach ([0, 1] as $i) {
            [$user,$callsign] = $stations[$i];
            if (! $user->qsos()->where('notes', 'DATO DE PRUEBA: escenario completo.')->exists()) {
                app(QsoService::class)->create($user, ['callsign_id' => $callsign->id, 'contacted_callsign' => $stations[1 - $i][1]->callsign, 'frequency_khz' => '14074.125', 'band' => '20m', 'mode' => 'FT8', 'started_at' => '2026-01-02T12:00:00Z', 'notes' => 'DATO DE PRUEBA: escenario completo.']);
            }
        }
        $match = QsoMatch::whereHas('qsoA', fn ($q) => $q->where('notes', 'DATO DE PRUEBA: escenario completo.'))->firstOrFail();
        if ($match->status === 'candidate') {
            foreach ($stations as [$user]) {
                app(QsoMatchingService::class)->decide($user, $match, 'confirm');
            }
        }
        app(QslService::class)->issue($stations[0][0], $match);
        [$user,$callsign] = $stations[0];
        if (! $user->qsos()->where('contacted_callsign', 'TEST0NOMATCH')->exists()) {
            app(QsoService::class)->create($user, ['callsign_id' => $callsign->id, 'contacted_callsign' => 'TEST0NOMATCH', 'frequency_khz' => '7074.000', 'band' => '40m', 'mode' => 'FT8', 'started_at' => '2026-01-03T12:00:00Z', 'notes' => 'DATO DE PRUEBA: sin coincidencia.']);
        }
    }
}
