<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class E2eUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'treasurer', 'secretary'] as $roleName) {
            $role = Role::query()->firstOrCreate(['name' => $roleName], config("auth_defaults.roles.{$roleName}"));
            $email = "e2e.{$roleName}@example.test";
            User::query()->updateOrCreate(['email' => $email], [
                'role_id' => $role->id,
                'first_name' => ucfirst($roleName),
                'last_name' => 'E2E',
                'name' => ucfirst($roleName).' E2E',
                'password' => 'E2e!Pass2026#'.$roleName,
                'status' => 'active',
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]);
        }
    }
}
