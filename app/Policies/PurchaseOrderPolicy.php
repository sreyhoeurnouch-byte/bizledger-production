<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->company_id === $order->company_id && in_array($user->role, ['owner', 'admin', 'accountant'], true);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->company_id === $order->company_id && in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }
}
