<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Creates workspaces and assigned Admin accounts, and manages workspace status and deletion.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');

        DB::table('users')
            ->where('email', 'gina@gmail.com')
            ->update([
                'role_id' => $superAdminRoleId,
                'is_super_admin' => true,
                'updated_at' => now(),
            ]);

        $ginaId = DB::table('users')->where('email', 'gina@gmail.com')->value('id');
        if ($ginaId !== null) {
            DB::table('workspace_memberships')->where('user_id', $ginaId)->delete();
        }
    }

    public function down(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        $ginaId = DB::table('users')->where('email', 'gina@gmail.com')->value('id');

        if ($adminRoleId !== null && $superAdminRoleId !== null) {
            DB::table('users')
                ->where('role_id', $superAdminRoleId)
                ->update([
                    'role_id' => $adminRoleId,
                    'is_super_admin' => true,
                    'updated_at' => now(),
                ]);
        }

        if ($ginaId !== null && $adminRoleId !== null) {
            $legacyWorkspaceId = DB::table('workspaces')->where('slug', 'legacy')->value('id');
            if ($legacyWorkspaceId !== null) {
                DB::table('workspace_memberships')->updateOrInsert(
                    ['workspace_id' => $legacyWorkspaceId, 'user_id' => $ginaId],
                    [
                        'role_id' => $adminRoleId,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        DB::table('roles')->where('name', 'super_admin')->delete();
    }
};
