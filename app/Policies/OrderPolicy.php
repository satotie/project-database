<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Customers only see their own orders; staff see all. */
    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() || $order->user_id === $user->id;
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->canUploadSlip();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->isStaff()
            ? $order->status->canTransitionTo(\App\Enums\OrderStatus::Cancelled)
            : $order->user_id === $user->id && $order->customerCanCancel();
    }
}
