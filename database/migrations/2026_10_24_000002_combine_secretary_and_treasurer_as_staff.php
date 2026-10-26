<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'staff'],
            [
                'display_name' => 'Staff',
                'description' => 'Manages donors, donations, inventory, cash verification, and bank reconciliation; cannot manage workspaces, staff, or approve restricted transactions.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $staffRoleId = DB::table('roles')->where('name', 'staff')->value('id');
        $legacyRoleIds = DB::table('roles')
            ->whereIn('name', ['secretary', 'treasurer'])
            ->pluck('id');

        if ($legacyRoleIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('role_id', $legacyRoleIds)
                ->update(['role_id' => $staffRoleId, 'updated_at' => now()]);

            DB::table('workspace_memberships')
                ->whereIn('role_id', $legacyRoleIds)
                ->update(['role_id' => $staffRoleId, 'updated_at' => now()]);

            DB::table('roles')->whereIn('id', $legacyRoleIds)->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This role consolidation cannot be rolled back because the original Staff role assignments are no longer distinguishable.',
        );
    }
};
