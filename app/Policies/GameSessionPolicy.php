<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\User;

class GameSessionPolicy
{
    public function view(User $user, GameSession $session): bool
    {
        return $user->isAdmin() || $session->cashier_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }

    public function update(User $user, GameSession $session): bool
    {
        return $user->isAdmin() || $session->cashier_id === $user->id;
    }

    public function delete(User $user, GameSession $session): bool
    {
        return $user->isAdmin();
    }
}
