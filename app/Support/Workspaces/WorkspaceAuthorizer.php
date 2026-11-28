<?php

namespace App\Support\Workspaces;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class WorkspaceAuthorizer
{
    public function hasActiveWorkspace(): bool
    {
        return app(WorkspaceContext::class)->hasWorkspace();
    }

    public function canAccessRecord(User $user, Model $record): bool
    {
        $workspace = app(WorkspaceContext::class)->get();

        return $workspace !== null
            && $record->getAttribute('workspace_id') !== null
            && (int) $record->getAttribute('workspace_id') === (int) $workspace->getKey()
            && $this->canAccess($user, $workspace);
    }

    public function canManageMember(User $actor, User $member): bool
    {
        $workspaceId = app(WorkspaceContext::class)->id();
        $workspace = app(WorkspaceContext::class)->get();

        if ($workspaceId === null || $workspace === null || ! $this->canManageMembers($actor, $workspace)) {
            return false;
        }

        // Admin accounts are platform-level accounts and are listed globally on
        // the administration screen. They remain manageable only by another
        // Admin; self-management is rejected by the request/service layer.
        if ($member->hasRole(Role::ADMIN)) {
            return $actor->hasRole(Role::ADMIN);
        }

        if (! $member->hasRole([Role::ADMIN, Role::STAFF])) {
            return false;
        }

        if ($member->relationLoaded('workspaceMemberships')) {
            return $member->workspaceMemberships->contains(function ($membership) use ($workspaceId): bool {
                return (int) $membership->workspace_id === (int) $workspaceId
                    && in_array($membership->status, ['active', 'deactivated'], true);
            });
        }

        return $member->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['active', 'deactivated'])
            ->exists();
    }

    public function canManageStaffRole(User $actor, string $role): bool
    {
        return $actor->hasRole(Role::ADMIN) && $role === Role::STAFF;
    }

    public function canAccess(User $user, Workspace $workspace): bool
    {
        if (! $workspace->isActive()) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        return $user->workspaceMemberships()
            ->where('workspace_id', $workspace->getKey())
            ->where('status', 'active')
            ->exists();
    }

    public function assertCanAccess(User $user, Workspace $workspace): void
    {
        if (! $this->canAccess($user, $workspace)) {
            throw new AuthorizationException('You are not authorized to access this workspace.');
        }
    }

    public function canManageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isActive() && $user->hasRole(Role::ADMIN);
    }
}
