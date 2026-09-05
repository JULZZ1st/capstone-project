<?php

namespace App\Policies;

use App\Models\BankLedgerEntry;
use App\Models\User;

class BankLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function reconcile(User $user, ?BankLedgerEntry $bankLedgerEntry = null): bool
    {
        return $user->hasRole(['admin', 'treasurer']);
    }

    public function import(User $user): bool
    {
        return $user->hasRole(['admin', 'treasurer']);
    }
}
