<?php

namespace App\Policies;

use App\Models\BankLedgerEntry;
use App\Models\User;

class BankLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function reconcile(User $user, ?BankLedgerEntry $bankLedgerEntry = null): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']) && ($bankLedgerEntry === null || $this->sameWorkspace($user, $bankLedgerEntry));
    }

    public function import(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->hasRole(['admin', 'staff']);
    }

    public function matchWithdrawal(User $user, BankLedgerEntry $bankLedgerEntry): bool
    {
        return $this->sameWorkspace($user, $bankLedgerEntry)
            && $user->hasRole(['admin', 'staff'])
            && $bankLedgerEntry->isWithdrawal()
            && ! $bankLedgerEntry->isReconciled();
    }

    private function active(User $user): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace(); }
    private function sameWorkspace(User $user, BankLedgerEntry $entry): bool { return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccessRecord($user, $entry); }
}
