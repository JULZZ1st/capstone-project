<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;

class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function view(User $user, Donor $donor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }

    public function update(User $user, Donor $donor): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }

    public function delete(User $user, Donor $donor): bool
    {
        return $user->hasRole(['admin', 'secretary']);
    }
}
