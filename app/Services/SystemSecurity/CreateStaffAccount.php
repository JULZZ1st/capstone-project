<?php

namespace App\Services\SystemSecurity;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

class CreateStaffAccount
{
    public function handle(array $data, User $actor): User
    {
        abort_unless(app(WorkspaceAuthorizer::class)->canManageStaffRole($actor, $data['role']), 403);
        return DB::transaction(function () use ($data, $actor): User {
            $role = Role::query()->firstOrCreate(
                ['name' => $data['role']],
                ['display_name' => ucfirst($data['role']), 'description' => ucfirst($data['role'])]
            );

            $user = User::query()->create([
                'role_id' => $role->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'must_change_password' => true,
                'status' => config('auth_defaults.user_status'),
            ]);

            $workspace = app(WorkspaceContext::class)->get();
            if ($workspace !== null) {
                $workspace->memberships()->create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'status' => 'active',
                    'created_by' => $actor->id,
                ]);
            }

            SecurityActivityEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => 'staff_account_created',
                'summary' => sprintf('%s created staff account %s', $actor->name, $user->email),
                'metadata' => ['role' => $role->name],
                'ip_address' => request()->ip(),
                'occurred_at' => now(),
            ]);

            return $user;
        });
    }
}
