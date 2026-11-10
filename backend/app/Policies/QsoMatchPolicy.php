<?php

namespace App\Policies;

use App\Models\QsoMatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QsoMatchPolicy
{
    public function view(User $user, QsoMatch $qsoMatch): Response
    {
        return in_array($user->id, [$qsoMatch->qsoA->user_id, $qsoMatch->qsoB->user_id]) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, QsoMatch $qsoMatch): Response
    {
        return $this->view($user, $qsoMatch);
    }
}
