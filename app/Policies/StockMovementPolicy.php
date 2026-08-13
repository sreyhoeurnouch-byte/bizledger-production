<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function post(User $user, StockMovement $movement): bool
    {
        return $user->company_id === $movement->company_id && in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }

    public function reverse(User $user, StockMovement $movement): bool
    {
        return $user->company_id === $movement->company_id && in_array($user->role, ['owner', 'admin', 'accountant'], true);
    }
}
