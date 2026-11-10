<?php

namespace App\Services;

use App\Models\Qso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QsoService
{
    public function __construct(private CallsignService $callsigns, private QsoMatchingService $matching) {}

    public function create(User $user, array $data): Qso
    {
        return DB::transaction(function () use ($user, $data) {
            DomainLock::acquire();
            $callsign = $user->callsigns()->whereKey($data['callsign_id'])->whereNotNull('reserved_callsign')->firstOrFail();
            if ($callsign->verification_status !== 'verified') {
                throw ValidationException::withMessages(['callsign_id' => 'La titularidad del indicativo debe estar verificada.']);
            }
            $normalized = $this->callsigns->validate($data['contacted_callsign']);
            if ($normalized === $callsign->normalized_callsign) {
                throw ValidationException::withMessages(['contacted_callsign' => 'No puedes registrar un contacto contigo mismo.']);
            }
            $data['contacted_callsign'] = $normalized;
            $data['normalized_contacted_callsign'] = $normalized;
            $data['user_id'] = $user->id;
            $data['status'] = 'pending';
            $qso = Qso::create($data);
            $this->matching->reconcile();

            return $qso->fresh()->load('callsign');
        }, 3);
    }

    public function withdraw(Qso $qso): void
    {
        DB::transaction(function () use ($qso) {
            DomainLock::acquire();
            $qso->refresh();
            abort_if(in_array($qso->status, ['confirmed', 'disputed']), 409, 'Un contacto confirmado debe disputarse desde su coincidencia.');
            $qso->update(['status' => 'rejected']);
            $this->matching->reconcile();
        }, 3);
    }
}
