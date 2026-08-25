<?php

namespace App\Policies;

use App\Models\CashDisbursement;
use App\Models\User;

class CashDisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, CashDisbursement $disbursement): bool
    {
        return $user->hasRole('admin')
            && $disbursement->isPending()
            && (int) $disbursement->requested_by !== (int) $user->id;
    }

    public function reject(User $user, CashDisbursement $disbursement): bool
    {
        return $this->approve($user, $disbursement);
    }

    public function void(User $user, CashDisbursement $disbursement): bool
    {
        return $user->hasRole('admin') && $disbursement->status === 'approved' && ! $disbursement->isVoided();
    }
}