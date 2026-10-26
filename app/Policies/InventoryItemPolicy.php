<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->viewAny($user) && $this->sameWorkspace($user, $inventoryItem);
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->active($user) && $this->sameWorkspace($user, $inventoryItem) && $user->hasRole(['admin', 'staff']);
    }

    public function adjustStock(User $user, InventoryItem $inventoryItem): bool
    {
        return $inventoryItem->status === config('inventory.defaults.status')
            && $this->sameWorkspace($user, $inventoryItem)
            && $user->hasRole(['admin', 'staff']);
    }

    public function audit(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->active($user) && $this->sameWorkspace($user, $inventoryItem) && $user->hasRole(['admin', 'staff']);
    }

    public function archive(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->active($user) && $this->sameWorkspace($user, $inventoryItem) && $user->hasRole('admin');
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, InventoryItem $item): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $item); }
}
