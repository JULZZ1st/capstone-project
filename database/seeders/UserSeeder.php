<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
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

        $gina->role_id = null;
        $gina->is_super_admin = true;
        $gina->save();

        // Gina is platform-only. Remove only her legacy membership; preserve
        // the legacy workspace and every other user's membership.
        $gina->workspaceMemberships()->delete();
    }
}
