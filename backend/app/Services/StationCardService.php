<?php

namespace App\Services;

use App\Models\Callsign;
use App\Models\StationCard;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class StationCardService
{
    public function assertPublishable(User $user, Callsign $callsign, bool $public): void
    {
        if ($public && ($user->status !== 'active' || ! $user->hasVerifiedEmail() || $callsign->reserved_callsign === null || $callsign->verification_status !== 'verified')) {
            throw ValidationException::withMessages(['is_public' => 'Para publicar la tarjeta necesitas un indicativo con titularidad verificada. Puedes guardarla privada.']);
        }
    }

    public function save(User $user, array $data, UploadedFile $image, ?StationCard $card = null): StationCard
    {
        $callsign = Callsign::where('user_id', $user->id)->whereNotNull('reserved_callsign')->findOrFail($data['callsign_id']);
        $this->assertPublishable($user, $callsign, (bool) $data['is_public']);
        $process = new Process([config('qsl.imagemagick'), '-limit', 'memory', '128MiB', '-limit', 'map', '256MiB', $image->getRealPath().'[0]', '-auto-orient', '-resize', '2000x2000>', '-strip', 'png:-']);
        $process->setTimeout(20);
        $process->mustRun();
        $path = 'station-cards/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $process->getOutput())) {
            throw new \RuntimeException('No se pudo guardar la imagen de la tarjeta.');
        }
        $oldPath = $card?->file_path;
        try {
            $card = DB::transaction(function () use ($user, $data, $path, $card) {
                $attributes = [
                    'callsign_id' => $data['callsign_id'], 'title' => $data['title'], 'description' => $data['description'] ?? null,
                    'source' => $data['source'], 'design' => $data['source'] === 'editor' ? $data['design'] : null,
                    'is_public' => (bool) $data['is_public'], 'file_path' => $path,
                ];
                if ($card) {
                    $card->update($attributes);
                } else {
                    $card = StationCard::create($attributes + ['user_id' => $user->id, 'public_id' => (string) Str::uuid()]);
                }

                return $card;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return $card->load('callsign');
    }
}
