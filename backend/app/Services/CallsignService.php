<?php

namespace App\Services;

use App\Models\Callsign;
use App\Models\OwnershipRequest;
use App\Models\Qso;
use App\Models\QsoMatch;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CallsignService
{
    public function review(User $reviewer, Callsign $callsign, string $status, string $reason): Callsign
    {
        Gate::forUser($reviewer)->authorize('review', $callsign);

        return DB::transaction(function () use ($reviewer, $callsign, $status, $reason) {
            DomainLock::acquire();
            $locked = Callsign::whereKey($callsign->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'verification_status' => $status,
                'verified_at' => $status === 'verified' ? now() : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ]);
            OwnershipRequest::where('callsign_id', $locked->id)->where('status', 'pending')->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            DB::table('callsign_reviews')->insert(['callsign_id' => $locked->id, 'reviewer_id' => $reviewer->id, 'status' => $status, 'reason' => $reason, 'created_at' => now()]);
            if ($status !== 'verified') {
                $matches = QsoMatch::whereIn('status', ['candidate', 'confirmed'])->where(fn ($q) => $q->whereHas('qsoA', fn ($q) => $q->where('callsign_id', $locked->id))->orWhereHas('qsoB', fn ($q) => $q->where('callsign_id', $locked->id)))->get();
                foreach ($matches as $match) {
                    $match->update(['status' => 'disputed']);
                    Qso::whereIn('id', [$match->qso_a_id, $match->qso_b_id])->update(['status' => 'disputed']);
                    $match->cards()->update(['status' => 'revoked']);
                    app(NotificationService::class)->match($match, 'disputed');
                }
            }
            DB::table('security_events')->insert([
                'user_id' => $reviewer->id,
                'event' => 'callsign.'.$locked->id.'.'.$status,
                'created_at' => now(),
            ]);

            return $locked;
        });
    }

    public function primary(User $user, Callsign $callsign): Callsign
    {
        abort_unless($callsign->user_id === $user->id, 404);

        return DB::transaction(function () use ($user, $callsign) {
            DomainLock::acquire();
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $callsign->refresh();
            abort_unless($callsign->reserved_callsign !== null, 409, 'El indicativo fue liberado.');
            $user->callsigns()->update(['is_primary' => false]);
            $callsign->update(['is_primary' => true]);

            return $callsign;
        }, 3);
    }

    public function release(User $admin, Callsign $callsign): void
    {
        Gate::forUser($admin)->authorize('review', $callsign);
        DB::transaction(function () use ($admin, $callsign) {
            DomainLock::acquire();
            $callsign->refresh();
            abort_unless($callsign->verification_status === 'rejected', 409, 'Rechaza primero la titularidad e indica el motivo.');
            abort_if($callsign->qsos()->whereIn('status', ['pending', 'candidate', 'confirmed'])->exists(), 409, 'Retira los contactos pendientes antes de liberar el indicativo.');
            $callsign->update(['reserved_callsign' => null, 'is_primary' => false]);
            DB::table('callsign_reviews')->insert(['callsign_id' => $callsign->id, 'reviewer_id' => $admin->id, 'status' => 'released', 'reason' => 'Reserva liberada tras rechazo administrativo.', 'created_at' => now()]);
        }, 3);
    }

    public function normalize(string $callsign): string
    {
        return strtoupper(trim($callsign));
    }

    public function validate(string $callsign): string
    {
        $normalized = $this->normalize($callsign);
        // Validación sintáctica amplia; nunca acredita titularidad ni determina el país.
        $segments = explode('/', $normalized);
        $valid = strlen($normalized) <= 32 && count($segments) <= 3;
        $hasStation = false;
        foreach ($segments as $segment) {
            $valid = $valid && preg_match('/^[A-Z0-9]{1,12}$/D', $segment) === 1;
            $hasStation = $hasStation || preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{3,12}$/D', $segment) === 1;
        }
        if (! $valid || ! $hasStation) {
            throw ValidationException::withMessages(['callsign' => 'El formato del indicativo no es válido.']);
        }

        return $normalized;
    }

    public function claim(User $user, string $callsign, ?string $countryCode = null): Callsign
    {
        $normalized = $this->validate($callsign);
        try {
            return DB::transaction(function () use ($user, $normalized, $countryCode) {
                DomainLock::acquire();
                // Serializa la elección del primer indicativo principal del usuario.
                $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                return $user->callsigns()->create([
                    'callsign' => $normalized,
                    'normalized_callsign' => $normalized,
                    'reserved_callsign' => $normalized,
                    'country_code' => $countryCode,
                    'verification_status' => 'pending',
                    'is_primary' => ! $user->callsigns()->exists(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['callsign' => 'Este indicativo ya está reclamado; requiere revisión administrativa.']);
        }
    }
}
