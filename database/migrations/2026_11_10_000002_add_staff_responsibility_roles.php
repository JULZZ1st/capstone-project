<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('auth_defaults.roles') as $name => $role) {
            if (! in_array($name, ['staff_1', 'staff_2'], true)) {
                continue;
            }

            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                [
                    'display_name' => $role['display_name'],
                    'description' => $role['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->whereIn('name', ['staff_1', 'staff_2'])->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        if (DB::table('users')->whereIn('role_id', $roleIds)->exists()
            || DB::table('workspace_memberships')->whereIn('role_id', $roleIds)->exists()) {
            throw new RuntimeException('Staff responsibility roles are assigned and cannot be removed safely.');
        }

        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }
};
