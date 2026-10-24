<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'gina@gmail.com'],
            [
                'role_id' => $role->id,
                'first_name' => 'Gina',
                'last_name' => 'Gamboa',
                'name' => 'Gina Gamboa',
                'password' => '@Gina;18_2026_test',
                'must_change_password' => false,
                'status' => 'active',
            ],
        );
    }
}
