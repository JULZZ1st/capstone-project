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
        if ($contribution->isVoided()) {
            return false;
        }

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
            && ! $contribution->isVoided()
            && $contribution->status === 'pending'
            && $this->sameWorkspace($user, $contribution)
            && $user->hasRole(['admin', 'staff']);
    }

    public function uploadDeed(User $user, Contribution $contribution): bool
    {
        return $contribution->isCash()
            && $contribution->status === 'pending'
            && ! $contribution->isVoided()
            && $this->sameWorkspace($user, $contribution)
            && ($user->hasRole('admin')
                || ($user->hasRole('staff') && (int) $contribution->created_by === (int) $user->id));
    }

    public function requestDocuments(User $user, Contribution $contribution): bool
    {
        return $contribution->isCash()
            && ! $contribution->isVoided()
            && $contribution->status === 'pending'
            && $this->sameWorkspace($user, $contribution)
            && $user->hasRole('admin');
    }

    public function issueCertificate(User $user, Contribution $contribution): bool
    {
        return $user->hasRole('admin')
            && ! $contribution->isVoided()
            && $contribution->certificate_requested
            && $contribution->certificate_status === 'pending'
            && $this->sameWorkspace($user, $contribution);
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
