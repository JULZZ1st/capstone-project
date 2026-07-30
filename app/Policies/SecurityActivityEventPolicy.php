<?php

namespace App\Policies;

use App\Models\User;

class SecurityActivityEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function updateSecuritySettings(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
