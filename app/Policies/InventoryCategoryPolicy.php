<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->hasRole('admin');
    }

    public function update(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $this->sameWorkspace($user, $inventoryCategory) && $user->hasRole('admin');
    }

    public function delete(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $this->sameWorkspace($user, $inventoryCategory) && $user->hasRole('admin');
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, InventoryCategory $record): bool { return $this->active($user) && app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $record); }
}
