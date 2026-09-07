<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.transfer_stock');
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.transfer_stock') && $transfer->isDraft();
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.transfer_stock') && $transfer->isDraft();
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.transfer_stock') && $transfer->isInTransit();
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.transfer_stock')
            && ($transfer->isDraft() || $transfer->isInTransit());
    }

    public function delete(User $user, StockTransfer $transfer): bool
    {
        return $user->hasPermission('products.transfer_stock') && $transfer->isDraft();
    }
}
