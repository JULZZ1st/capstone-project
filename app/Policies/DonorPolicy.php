<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;

class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function view(User $user, Donor $donor): bool
    {
        return $this->viewAny($user) && $this->sameWorkspace($user, $donor);
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function update(User $user, Donor $donor): bool
    {
        return $this->active($user) && $this->sameWorkspace($user, $donor) && $user->hasRole(['admin', 'staff']);
    }

    public function delete(User $user, Donor $donor): bool
    {
        return $this->active($user) && $this->sameWorkspace($user, $donor) && $user->hasRole(['admin', 'staff']);
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, Donor $donor): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $donor); }
}
