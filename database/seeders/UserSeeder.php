<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $gina = User::query()->firstOrNew(['email' => 'gina@gmail.com']);

        // Preserve an existing account's password and account state. The default
        // password is only assigned when the seeded account is created for the
        // first time in a new database.
        if (! $gina->exists) {
            $gina->password = '@Gina;18_2026_test';
            $gina->must_change_password = false;
            $gina->status = 'active';
            $gina->first_name = 'Gina';
            $gina->last_name = 'Gamboa';
            $gina->name = 'Gina Gamboa';
        }

        $gina->role_id = $role->id;
        $gina->is_super_admin = true;
        $gina->save();

        $gina->refresh();
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'legacy'],
            ['name' => 'Legacy Workspace', 'status' => 'active'],
        );

        $workspace->memberships()->updateOrCreate(
            ['user_id' => $gina->id],
            ['role_id' => $role->id, 'status' => 'active'],
        );
    }
}
