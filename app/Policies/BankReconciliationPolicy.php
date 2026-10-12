<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    public function viewAny(User $user): bool { return $user->hasRole(['admin', 'secretary', 'treasurer']); }
    public function prepare(User $user): bool { return $user->hasRole('treasurer'); }
    public function submit(User $user, BankReconciliation $period): bool { return $user->hasRole('treasurer') && $period->isDraft(); }
    public function update(User $user, BankReconciliation $period): bool { return $user->hasRole('treasurer') && $period->isDraft() && (int) $period->prepared_by === (int) $user->id; }
    public function approve(User $user, BankReconciliation $period): bool
    {
        return $user->hasRole('admin') && $period->isReadyForReview() && (int) $period->prepared_by !== (int) $user->id;
    }
    public function reject(User $user, BankReconciliation $period): bool
    {
        return $user->hasRole('admin') && $period->isReadyForReview() && (int) $period->prepared_by !== (int) $user->id;
    }
    public function reopen(User $user, BankReconciliation $period): bool { return $user->hasRole('admin') && $period->isApproved(); }
}
