<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $user->hasRole('admin');
    }
}
