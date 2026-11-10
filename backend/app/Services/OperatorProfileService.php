<?php

namespace App\Services;

use App\Models\OperatorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OperatorProfileService
{
    public function update(User $user, array $data): OperatorProfile
    {
        return DB::transaction(function () use ($user, $data) {
            DomainLock::acquire();
            $callsign = $user->callsigns()->whereKey($data['callsign_id'])->whereNotNull('reserved_callsign')->firstOrFail();
            abort_if($data['is_public'] && $callsign->verification_status !== 'verified', 422, 'La titularidad debe estar verificada antes de publicar el perfil.');
            $existing = OperatorProfile::where('user_id', $user->id)->first();
            if ($existing) {
                Gate::forUser($user)->authorize('update', $existing);
            }
            $profile = OperatorProfile::updateOrCreate(['user_id' => $user->id], $data);
            DB::table('security_events')->insert(['user_id' => $user->id, 'event' => $data['is_public'] ? 'operator.profile.published' : 'operator.profile.private', 'created_at' => now()]);

            return $profile->load('callsign', 'user');
        }, 3);
    }

    public function photo(User $user, UploadedFile $photo): OperatorProfile
    {
        $profile = OperatorProfile::where('user_id', $user->id)->firstOrFail();
        Gate::forUser($user)->authorize('update', $profile);
        // Se convierte y elimina EXIF/metadatos. No se aceptan SVG ni URLs para descargar imágenes.
        $process = new Process([config('qsl.imagemagick'), $photo->getRealPath().'[0]', '-auto-orient', '-resize', '1024x1024>', '-strip', 'png:-']);
        $process->setTimeout(15);
        $process->mustRun();
        $path = 'operators/'.Str::uuid().'.png';
        abort_unless(Storage::disk('local')->put($path, $process->getOutput()), 500);
        $old = $profile->photo_path;
        $profile->update(['photo_path' => $path]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return $profile->load('callsign', 'user');
    }
}
