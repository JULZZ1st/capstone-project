<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function view(User $user, Contribution $contribution): bool
    {
        return $this->viewAny($user) && $this->sameWorkspace($user, $contribution);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Contribution $contribution): bool
    {
        if ($user->hasRole('admin')) {
            return $this->sameWorkspace($user, $contribution);
        }

        return $contribution->isEditable()
            && (int) $contribution->created_by === (int) $user->id
            && $this->sameWorkspace($user, $contribution)
            && $user->hasRole('staff');
    }

    public function verify(User $user, Contribution $contribution): bool
    {
        return $contribution->isCash()
            && $contribution->status === 'pending'
            && $this->sameWorkspace($user, $contribution)
            && $user->hasRole('admin');
    }

    public function void(User $user, Contribution $contribution): bool
    {
        return $this->sameWorkspace($user, $contribution)
            && $user->hasRole('admin')
            && $contribution->isCash()
            && $contribution->status === 'pending'
            && ! $contribution->isVoided()
            && ! $contribution->ledgerEntry()->exists();
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, Contribution $contribution): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $contribution); }
}
