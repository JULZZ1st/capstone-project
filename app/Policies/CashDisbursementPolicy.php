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

    public function view(User $user, CashDisbursement $cashDisbursement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }
}
