<?php

namespace App\Services;

use App\Models\Qso;
use App\Models\QsoMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QsoMatchingService
{
    public function __construct(private NotificationService $notifications) {}

    public function candidates(Qso $qso): Collection
    {
        return Qso::with('callsign')->whereIn('status', ['pending', 'candidate'])
            ->where('user_id', '!=', $qso->user_id)->where('band', $qso->band)->where('mode', $qso->mode)
            ->where('normalized_contacted_callsign', $qso->callsign->normalized_callsign)
            ->whereHas('callsign', fn ($query) => $query->where('normalized_callsign', $qso->normalized_contacted_callsign)->where('verification_status', 'verified'))
            ->whereBetween('started_at', [$qso->started_at->subMinutes(30), $qso->started_at->addMinutes(30)])->orderBy('id')->get();
    }

    public function reconcile(): void
    {
        // Se llama dentro de una transacción que ya posee DomainLock. Grado uno en ambos extremos;
        // la llegada de un tercer QSO invalida candidatos anteriores, incluso con una sola firma.
        $qsos = Qso::with('callsign')->whereIn('status', ['pending', 'candidate'])->orderBy('id')->get();
        $edges = [];
        $selected = [];
        foreach ($qsos as $qso) {
            $edges[$qso->id] = $qso->callsign->verification_status === 'verified' ? $this->candidates($qso)->modelKeys() : [];
        }
        foreach ($qsos as $qso) {
            $peers = $edges[$qso->id];
            if (count($peers) !== 1 || count($edges[$peers[0]] ?? []) !== 1) {
                continue;
            }
            [$a,$b] = [min($qso->id, $peers[0]), max($qso->id, $peers[0])];
            $selected[$a.'-'.$b] = [$a, $b];
        }
        foreach (QsoMatch::where('status', 'candidate')->get() as $match) {
            if (! isset($selected[$match->qso_a_id.'-'.$match->qso_b_id])) {
                $match->update(['status' => 'rejected', 'a_confirmed_at' => null, 'b_confirmed_at' => null]);
            }
        }
        Qso::whereIn('status', ['pending', 'candidate'])->update(['status' => 'pending']);
        foreach ($selected as [$a,$b]) {
            $left = $qsos->firstWhere('id', $a);
            $right = $qsos->firstWhere('id', $b);
            $match = QsoMatch::firstOrCreate(['qso_a_id' => $a, 'qso_b_id' => $b], ['status' => 'candidate', 'matched_at' => now(), 'time_difference_seconds' => abs($left->started_at->getTimestamp() - $right->started_at->getTimestamp())]);
            if ($match->status === 'rejected') {
                $match->update(['status' => 'candidate', 'matched_at' => now()]);
            }
            Qso::whereIn('id', [$a, $b])->update(['status' => 'candidate']);
            $this->notifications->match($match, 'candidate');
        }
    }

    public function decide(User $user, QsoMatch $match, string $decision): QsoMatch
    {
        return DB::transaction(function () use ($user, $match, $decision) {
            DomainLock::acquire();
            $match->refresh()->load('qsoA.callsign', 'qsoB.callsign');
            abort_unless(in_array($user->id, [$match->qsoA->user_id, $match->qsoB->user_id]), 404);
            if ($decision === 'confirm') {
                if ($match->status === 'confirmed') {
                    return $match;
                }
                abort_unless($match->status === 'candidate', 409, 'Esta coincidencia ya no admite confirmación.');
                $this->reconcile();
                $match->refresh();
                abort_unless($match->status === 'candidate', 409, 'La coincidencia es ambigua o fue invalidada.');
                $column = $match->qsoA->user_id === $user->id ? 'a_confirmed_at' : 'b_confirmed_at';
                $match->update([$column => $match->$column ?? now()]);
                if ($match->a_confirmed_at && $match->b_confirmed_at) {
                    DB::table('qso_confirmations')->insert([
                        ['qso_id' => $match->qso_a_id, 'qso_match_id' => $match->id],
                        ['qso_id' => $match->qso_b_id, 'qso_match_id' => $match->id],
                    ]);
                    $match->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                    Qso::whereIn('id', [$match->qso_a_id, $match->qso_b_id])->update(['status' => 'confirmed']);
                    $this->notifications->match($match, 'confirmed');
                }
            } else {
                abort_unless(in_array($match->status, ['candidate', 'confirmed']), 409);
                $status = $decision === 'dispute' ? 'disputed' : 'rejected';
                abort_if($match->status === 'confirmed' && $status !== 'disputed', 409, 'Utiliza disputar para revocar una confirmación.');
                $match->update(['status' => $status]);
                Qso::whereIn('id', [$match->qso_a_id, $match->qso_b_id])->update(['status' => $status]);
                $match->cards()->update(['status' => 'revoked']);
                $this->notifications->match($match,'disputed');
            }

            return $match->fresh()->load('qsoA.callsign','qsoB.callsign');
        }, 3);
    }
}
