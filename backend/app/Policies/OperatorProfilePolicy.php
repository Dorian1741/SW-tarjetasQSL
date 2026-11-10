<?php

namespace App\Policies;

use App\Models\OperatorProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OperatorProfilePolicy
{
    public function update(User $user, OperatorProfile $operatorProfile): Response
    {
        return $user->id === $operatorProfile->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
