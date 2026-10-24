<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->hasRole('admin');
    }

    public function update(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $this->sameWorkspace($user, $inventoryUnit) && $user->hasRole('admin');
    }

    public function delete(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $this->sameWorkspace($user, $inventoryUnit) && $user->hasRole('admin');
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, InventoryUnit $record): bool { return $this->active($user) && app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $record); }
}
