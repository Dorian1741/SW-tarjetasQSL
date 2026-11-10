<?php

namespace App\Policies;

use App\Models\Qso;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QsoPolicy
{
    public function view(User $user, Qso $qso): Response
    {
        return $user->id === $qso->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Qso $qso): Response
    {
        return $this->view($user, $qso);
    }
}
