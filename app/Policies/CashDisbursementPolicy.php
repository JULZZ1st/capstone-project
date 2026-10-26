<?php

namespace App\Policies;

use App\Models\CashDisbursement;
use App\Models\User;

class CashDisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, CashDisbursement $disbursement): bool
    {
        return $this->viewAny($user) && $this->sameWorkspace($user, $disbursement);
    }

    public function approve(User $user, CashDisbursement $disbursement): bool
    {
        return $this->sameWorkspace($user, $disbursement)
            && $user->hasRole('admin')
            && $disbursement->isPending()
            && (int) $disbursement->requested_by !== (int) $user->id;
    }

    public function reject(User $user, CashDisbursement $disbursement): bool
    {
        return $this->approve($user, $disbursement);
    }

    public function void(User $user, CashDisbursement $disbursement): bool
    {
        return $this->sameWorkspace($user, $disbursement) && $user->hasRole('admin') && $disbursement->status === 'approved' && ! $disbursement->isVoided();
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, CashDisbursement $record): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $record); }
}
