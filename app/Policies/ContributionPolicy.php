<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function view(User $user, Contribution $contribution): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Contribution $contribution): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $contribution->isEditable()
            && (int) $contribution->created_by === (int) $user->id
            && $user->hasRole(['secretary', 'treasurer']);
    }

    public function verify(User $user, Contribution $contribution): bool
    {
        return $contribution->isCash()
            && $contribution->status === 'pending'
            && $user->hasRole(['admin', 'treasurer']);
    }
}
