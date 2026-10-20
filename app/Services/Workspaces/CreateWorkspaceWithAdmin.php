<?php

namespace App\Services\Workspaces;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspaceWithAdmin
{
    public function handle(array $data, User $actor): Workspace
    {
        return DB::transaction(function () use ($data, $actor): Workspace {
            $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
            $slug = $data['slug'] ?: Str::slug($data['name']);
            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'status' => 'active',
            ]);

            $admin = User::query()->create([
                'role_id' => $role->id,
                'is_super_admin' => false,
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'name' => $data['admin_first_name'].' '.$data['admin_last_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'must_change_password' => true,
                'status' => config('auth_defaults.user_status'),
            ]);

            $workspace->memberships()->create([
                'user_id' => $admin->id,
                'role_id' => $role->id,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            SecurityActivityEvent::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $admin->id,
                'actor_id' => $actor->id,
                'action' => 'workspace_created',
                'summary' => sprintf('%s created workspace %s and assigned %s', $actor->name, $workspace->name, $admin->email),
                'metadata' => ['workspace_id' => $workspace->id, 'admin_id' => $admin->id],
                'ip_address' => request()->ip(),
                'occurred_at' => now(),
            ]);

            return $workspace;
        });
    }
}
