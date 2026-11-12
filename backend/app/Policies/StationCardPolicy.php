<?php

namespace App\Policies;

use App\Models\StationCard;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StationCardPolicy
{
    public function view(User $user, StationCard $stationCard): Response
    {
        return $user->id === $stationCard->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, StationCard $stationCard): Response
    {
        return $this->view($user, $stationCard);
    }

    public function delete(User $user, StationCard $stationCard): Response
    {
        return $this->view($user, $stationCard);
    }
}
