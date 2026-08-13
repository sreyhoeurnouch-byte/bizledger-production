<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function update(User $user, Item $item): bool
    {
        return $user->company_id === $item->company_id && in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }
}
