<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $user->hasRole('admin');
    }
}
