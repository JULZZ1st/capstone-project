<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $password = 'ginagamboa123s!';

        $gina = User::query()->firstOrNew(['email' => 'gina@gmail.com']);
        $gina->fill([
            'role_id' => $role->id,
            'first_name' => 'Gina',
            'last_name' => 'Gamboa',
            'name' => 'Gina Gamboa',
            'must_change_password' => false,
            'status' => 'active',
        ]);

        if (! $gina->exists || ! Hash::check($password, (string) $gina->password)) {
            $gina->password = $password;
        }

        $gina->save();

        $workspace = $gina->workspaceMemberships()
            ->where('status', 'active')
            ->with('workspace')
            ->latest('id')
            ->first()?->workspace;

        $workspace ??= Workspace::query()->firstOrCreate(
            ['slug' => 'legacy'],
            ['name' => 'Legacy Workspace', 'status' => 'active'],
        );

        $gina->workspaceMemberships()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            ['role_id' => $role->id, 'status' => 'active'],
        );
    }
}
