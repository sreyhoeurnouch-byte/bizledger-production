<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }

    public function approve(User $user, SalesOrder $order): bool
    {
        return $user->company_id === $order->company_id && in_array($user->role, ['owner', 'admin', 'accountant'], true);
    }

    public function deliver(User $user, SalesOrder $order): bool
    {
        return $user->company_id === $order->company_id && in_array($user->role, ['owner', 'admin', 'accountant', 'operator'], true);
    }

    public function invoice(User $user, SalesOrder $order): bool
    {
        return $this->deliver($user, $order);
    }
}
