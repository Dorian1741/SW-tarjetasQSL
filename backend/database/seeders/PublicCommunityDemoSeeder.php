<?php

namespace Database\Seeders;

use App\Models\OperatorPost;
use App\Models\OperatorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class PublicCommunityDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('La comunidad de demostración solo está disponible en local/testing.');
        }
        foreach (['a', 'b'] as $suffix) {
            $user = User::where('email', 'scenario-'.$suffix.'@example.test')->firstOrFail();
            $callsign = $user->callsigns()->where('reserved_callsign', 'TEST0FULL'.strtoupper($suffix))->where('verification_status', 'verified')->firstOrFail();
            OperatorProfile::firstOrCreate(['user_id' => $user->id], [
                'callsign_id' => $callsign->id,
                'biography' => 'PERFIL FICTICIO DE DEMOSTRACIÓN. Una estación de prueba para conocer la comunidad de QSL Nexus.',
                'country_code' => $suffix === 'a' ? 'EC' : null,
                'is_public' => true,
                'show_qsl_history' => false,
                'links' => [],
            ]);
            $content = $suffix === 'a'
                ? 'DEMOSTRACIÓN · Bienvenidos al muro de QSL Nexus. Aquí podemos compartir experiencias, conocer otras estaciones y descubrir nuevas conexiones. Esta es una publicación ficticia para probar el sistema.'
                : 'DEMOSTRACIÓN · Una tarjeta QSL conserva la historia de un encuentro en el aire. Explora nuestro perfil de prueba y descubre cómo compartir la actividad de tu estación con la comunidad.';
            OperatorPost::firstOrCreate(['user_id' => $user->id, 'content' => $content]);
        }
    }
}
