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
            $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->first()
                ?? Workspace::query()->create([
                    'name' => 'Barangay Workspace',
                    'slug' => 'barangay',
                    'status' => 'active',
                ]);

            foreach ([
                ['name' => 'Admin', 'role' => Role::ADMIN, 'email' => 'admin@barangay.test', 'password' => 'Admin@2026_Test'],
                ['name' => 'Secretary', 'role' => Role::STAFF, 'email' => 'secretary@barangay.test', 'password' => 'Secretary@2026_Test'],
                ['name' => 'Treasurer', 'role' => Role::STAFF, 'email' => 'treasurer@barangay.test', 'password' => 'Treasurer@2026_Test'],
            ] as $account) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $account['role']],
                    config("auth_defaults.roles.{$account['role']}"),
                );

                $user = User::query()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'role_id' => $role->id,
                        'first_name' => 'Barangay',
                        'last_name' => $account['name'],
                        'name' => 'Barangay '.$account['name'],
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
