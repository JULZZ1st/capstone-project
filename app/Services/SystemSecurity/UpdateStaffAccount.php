<?php

namespace App\Services\SystemSecurity;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use App\Support\Workspaces\WorkspaceAuthorizer;

class UpdateStaffAccount
{
    public function handle(User $user, array $data, User $actor): User
    {
        abort_unless(app(WorkspaceAuthorizer::class)->canManageStaffRole($actor, $data['role']), 403);
        $role = Role::query()->firstOrCreate(
            ['name' => $data['role']],
            ['display_name' => ucfirst($data['role']), 'description' => ucfirst($data['role'])]
        );

        $user->forceFill([
            'role_id' => $role->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'status' => $data['status'] ?? $user->status,
        ])->save();

        $workspace = app(WorkspaceContext::class)->get();
        if ($workspace !== null) {
            $workspace->memberships()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'role_id' => $role->id,
                    'status' => $user->status === 'active' ? 'active' : 'deactivated',
                    'created_by' => $user->workspaceMemberships()
                        ->where('workspace_id', $workspace->id)
                        ->value('created_by') ?? $actor->id,
                ],
            );
        }

        SecurityActivityEvent::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'action' => 'staff_account_updated',
            'summary' => sprintf('%s updated staff account %s', $actor->name, $user->email),
            'metadata' => ['role' => $role->name],
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);

        return $user;
    }
}
