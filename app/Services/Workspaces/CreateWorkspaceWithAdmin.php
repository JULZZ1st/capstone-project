<?php

namespace App\Services\Workspaces;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspaceWithAdmin
{
    public function handle(array $data, User $actor): Workspace
    {
        return DB::transaction(function () use ($data, $actor): Workspace {
            $role = Role::query()->firstOrCreate(
                ['name' => Role::ADMIN],
                ['display_name' => 'Admin', 'description' => 'Workspace administrator'],
            );

            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name']),
                'status' => 'active',
            ]);

            $admin = User::query()->create([
                'role_id' => $role->id,
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

            return $workspace;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::limit(Str::slug($name), 90, '');
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($baseSlug, 95, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
