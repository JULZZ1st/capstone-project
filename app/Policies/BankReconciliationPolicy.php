<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    public function viewAny(User $user): bool { return $this->active($user) && $user->hasRole(['admin', 'secretary', 'treasurer']); }
    public function prepare(User $user): bool { return $this->active($user) && $user->hasRole('treasurer'); }
    public function submit(User $user, BankReconciliation $period): bool { return $this->sameWorkspace($user, $period) && $user->hasRole('treasurer') && $period->isDraft(); }
    public function update(User $user, BankReconciliation $period): bool { return $this->sameWorkspace($user, $period) && $user->hasRole('treasurer') && $period->isDraft() && (int) $period->prepared_by === (int) $user->id; }
    public function approve(User $user, BankReconciliation $period): bool
    {
        return $this->sameWorkspace($user, $period) && $user->hasRole('admin') && $period->isReadyForReview() && (int) $period->prepared_by !== (int) $user->id;
    }
    public function reject(User $user, BankReconciliation $period): bool
    {
        return $this->sameWorkspace($user, $period) && $user->hasRole('admin') && $period->isReadyForReview() && (int) $period->prepared_by !== (int) $user->id;
    }
    public function reopen(User $user, BankReconciliation $period): bool { return $this->sameWorkspace($user, $period) && $user->hasRole('admin') && $period->isApproved(); }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, BankReconciliation $period): bool { return $this->active($user) && app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $period); }
}
