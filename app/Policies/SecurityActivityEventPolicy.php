<?php

namespace App\Policies;

use App\Models\User;

class SecurityActivityEventPolicy
{
    public function viewAny(User $user): bool
    {
        return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace()
            && $user->hasRole(['admin', 'staff']);
    }

    public function updateSecuritySettings(User $user): bool
    {
        return app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->hasActiveWorkspace()
            && $user->hasRole('admin');
    }
}
