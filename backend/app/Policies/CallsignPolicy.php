<?php

namespace App\Policies;

use App\Models\Callsign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CallsignPolicy
{
    public function review(User $user, Callsign $callsign): Response
    {
        return $user->is_admin && $user->id !== $callsign->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
