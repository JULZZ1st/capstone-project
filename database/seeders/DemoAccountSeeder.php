<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->firstOrFail();

            foreach ([
                ['role' => Role::ADMIN, 'email' => 'admin@barangay.test', 'password' => 'Admin@2026_Test'],
                ['role' => Role::STAFF, 'email' => 'staff@barangay.test', 'password' => 'Staff@2026_Test'],
            ] as $account) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $account['role']],
                    config("auth_defaults.roles.{$account['role']}"),
                );

                $user = User::query()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'role_id' => $role->id,
                        'first_name' => 'Demo',
                        'last_name' => ucfirst($account['role']),
                        'name' => 'Demo '.ucfirst($account['role']),
                        'password' => $account['password'],
                        'must_change_password' => false,
                        'status' => 'active',
                    ],
                );

                $workspace->memberships()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role_id' => $role->id, 'status' => 'active'],
                );
            }
        });
    }
}
