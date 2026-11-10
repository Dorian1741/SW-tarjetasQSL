<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class TwoFactorSessionController extends TwoFactorAuthenticatedSessionController
{
    public function store(TwoFactorLoginRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // Bloqueo del usuario antes de validar/consumir recuperación: evita dos sesiones con el mismo código.
            $user = $request->challengedUser();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->refresh();
            abort_unless($user->status === 'active' && $user->hasEnabledTwoFactorAuthentication(), 403);

            return parent::store($request);
        }, 3);
    }
}
