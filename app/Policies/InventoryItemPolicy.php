<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }

    public function adjustStock(User $user, InventoryItem $inventoryItem): bool
    {
        return $inventoryItem->status === config('inventory.defaults.status')
            && $user->hasRole(['admin', 'secretary']);
    }

    public function audit(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }

    public function archive(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasRole('admin');
    }
}
