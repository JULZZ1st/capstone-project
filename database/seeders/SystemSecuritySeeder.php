<?php

namespace Database\Seeders;

use App\Models\SecurityActivityEvent;
use App\Models\SecurityPermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemSecuritySeeder extends Seeder
{
    public function run(): void
    {
        collect(config('system_security.permissions'))->each(function (array $permission): void {
            SecurityPermission::query()->updateOrCreate(
                ['key' => $permission['key']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'sort_order' => $permission['sort_order'],
                ],
            );
        });

        $permissions = SecurityPermission::query()->pluck('id', 'key');

        User::query()
            ->with('role')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($permissions): void {
                $rolePermissions = config("system_security.role_permissions.{$user->roleName()}", []);
                $sync = collect($rolePermissions)
                    ->filter(fn (string $key) => $permissions->has($key))
                    ->mapWithKeys(fn (string $key) => [$permissions[$key] => ['granted_by' => null]])
                    ->all();

                if ($sync !== []) {
                    $user->securityPermissions()->syncWithoutDetaching($sync);
                }
            });

        User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->get()
            ->each(function (User $admin): void {
                SecurityActivityEvent::query()->firstOrCreate(
                    [
                        'user_id' => $admin->id,
                        'action' => 'admin_authenticated',
                        'summary' => "Authorized admin user {$admin->name} successfully authenticated.",
                    ],
                    [
                        'actor_id' => $admin->id,
                        'metadata' => ['source' => 'system_security_seed'],
                        'ip_address' => '127.0.0.1',
                        'occurred_at' => now(),
                    ],
                );
            });
    }
}
