<?php

namespace App\Services;

use App\Models\QslCard;
use App\Models\QsoMatch;
use App\Models\User;
use App\Models\StationCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QslService
{
    public function __construct(private QslRenderer $renderer, private NotificationService $notifications) {}

    public function issue(User $user, QsoMatch $match, ?string $stationCardId = null): QslCard
    {
        return DB::transaction(function () use ($user, $match, $stationCardId) {
            DomainLock::acquire();
            $match->refresh()->load('qsoA.callsign', 'qsoB.callsign');
            abort_unless(in_array($user->id, [$match->qsoA->user_id, $match->qsoB->user_id]), 404);
            abort_unless($match->status === 'confirmed' && $match->a_confirmed_at && $match->b_confirmed_at, 409, 'Ambas estaciones deben confirmar antes de emitir una tarjeta.');
            $receiver = $user->id === $match->qsoA->user_id ? $match->qsoB->user_id : $match->qsoA->user_id;
            $stationDesign = null;
            if ($stationCardId !== null) {
                $callsignId = $user->id === $match->qsoA->user_id ? $match->qsoA->callsign_id : $match->qsoB->callsign_id;
                $stationDesign = StationCard::where('user_id', $user->id)->where('callsign_id', $callsignId)->where('public_id', $stationCardId)->first();
                if (! $stationDesign) {
                    throw ValidationException::withMessages(['station_card_id' => 'Selecciona una tarjeta propia del indicativo usado en este contacto.']);
                }
            }
            $card = QslCard::firstOrCreate(['qso_match_id' => $match->id, 'sender_user_id' => $user->id], ['public_id' => (string) Str::uuid(), 'receiver_user_id' => $receiver, 'status' => 'pending', 'generated_at' => now(), 'station_card_id' => $stationDesign?->id]);
            abort_if($card->status === 'revoked', 409, 'La tarjeta está revocada.');
            if ($card->status !== 'ready' || ! Storage::disk('local')->exists($card->file_path ?? 'missing')) {
                $path = 'qsl/'.$card->public_id.'.png';
                if (! Storage::disk('local')->put($path, $this->renderer->render($card))) {
                    throw new \RuntimeException('No se pudo guardar la tarjeta.');
                }
                $card->update(['status' => 'ready', 'file_path' => $path]);
            }
            foreach ([$user->id, $receiver] as $id) {
                $this->notifications->send($id, 'qsl-'.$card->id, 'qsl', ['message' => 'Tarjeta QSL '.$card->public_id.' emitida.', 'path' => '/app/cards/'.$card->public_id]);
            }

            return $card;
        }, 3);
    }
}
