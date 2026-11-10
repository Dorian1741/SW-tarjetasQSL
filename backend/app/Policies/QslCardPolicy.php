<?php

namespace App\Policies;

use App\Models\QslCard;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QslCardPolicy
{
    public function view(User $user, QslCard $qslCard): Response
    {
        return in_array($user->id, [$qslCard->sender_user_id, $qslCard->receiver_user_id]) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, QslCard $qslCard): Response
    {
        return $this->view($user, $qslCard);
    }
}
