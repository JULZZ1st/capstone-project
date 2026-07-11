<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEEDED_USER_PASSWORD');

        if (! $password) {
            return;
        }

        foreach (config('auth_defaults.roles') as $roleName => $roleConfig) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $firstName = $roleConfig['display_name'];

            User::query()->updateOrCreate(
                ['email' => "{$roleName}@example.com"],
                [
                    'role_id' => $role->id,
                    'first_name' => $firstName,
                    'last_name' => 'User',
                    'name' => $firstName.' User',
                    'password' => $password,
                    'status' => config('auth_defaults.user_status'),
                ],
            );
        }
    }
}
