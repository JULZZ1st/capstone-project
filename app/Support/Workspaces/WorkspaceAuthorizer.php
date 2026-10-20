<?php

namespace App\Support\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Authorization\AuthorizationException;

class WorkspaceAuthorizer
{
    public function hasActiveWorkspace(): bool
    {
        return app(WorkspaceContext::class)->hasWorkspace();
    }

    public function canAccessRecord(User $user, Model $record): bool
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        return $workspaceId !== null
            && $record->getAttribute('workspace_id') !== null
            && (int) $record->getAttribute('workspace_id') === (int) $workspaceId
            && $this->canAccess($user, $record->workspace()->firstOrFail());
    }

    public function canManageMember(User $actor, User $member): bool
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        if ($workspaceId === null || ! $this->canManageMembers($actor, Workspace::findOrFail($workspaceId))) {
            return false;
        }

        if (! $actor->isSuperAdmin() && ! $member->hasRole(['secretary', 'treasurer'])) {
            return false;
        }

        return $member->workspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->exists();
    }

    public function canManageStaffRole(User $actor, string $role): bool
    {
        return $actor->isSuperAdmin() || in_array($role, ['secretary', 'treasurer'], true);
    }

    public function canAccess(User $user, Workspace $workspace): bool
    {
        if (! $workspace->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
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

    public function canManageWorkspaces(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function canManageMembers(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->workspaceMemberships()
            ->where('workspace_id', $workspace->getKey())
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->exists();
    }
}
