<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'admin'],
            [
                'display_name' => 'Admin',
                'description' => 'Manages all workspaces, staff accounts, and operational workflows.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');

        if ($superAdminRoleId !== null) {
            DB::table('users')
                ->where('role_id', $superAdminRoleId)
                ->update(['role_id' => $adminRoleId, 'updated_at' => now()]);

            DB::table('workspace_memberships')
                ->where('role_id', $superAdminRoleId)
                ->update(['role_id' => $adminRoleId, 'updated_at' => now()]);

            DB::table('roles')->where('id', $superAdminRoleId)->delete();
        }

        if (Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['is_super_admin']);
                $table->dropColumn('is_super_admin');
            });
        }
    }

    public function down(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Legacy platform administrator role.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        DB::table('roles')
            ->where('name', 'admin')
            ->update([
                'display_name' => 'Administrator',
                'description' => 'Manages operational records and staff within the assigned workspace.',
                'updated_at' => now(),
            ]);

        if (! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_super_admin')->default(false)->index();
            });
        }

        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        DB::table('users')
            ->where('email', 'gina@gmail.com')
            ->update([
                'role_id' => $superAdminRoleId,
                'is_super_admin' => true,
                'updated_at' => now(),
            ]);
    }
};
