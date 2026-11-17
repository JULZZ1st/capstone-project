<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $staffId = DB::table('roles')->where('name', 'staff')->value('id');

            if ($staffId === null) {
                throw new RuntimeException('The required staff role does not exist.');
            }

            $responsibilityRoleIds = DB::table('roles')
                ->whereIn('name', ['staff_1', 'staff_2'])
                ->lockForUpdate()
                ->pluck('id');

            if ($responsibilityRoleIds->isEmpty()) {
                return;
            }

            DB::table('users')
                ->whereIn('role_id', $responsibilityRoleIds)
                ->update(['role_id' => $staffId]);

            DB::table('workspace_memberships')
                ->whereIn('role_id', $responsibilityRoleIds)
                ->update(['role_id' => $staffId]);

            DB::table('roles')
                ->whereIn('id', $responsibilityRoleIds)
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.role_id', 'roles.id');
                })
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('workspace_memberships')
                        ->whereColumn('workspace_memberships.role_id', 'roles.id');
                })
                ->delete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This corrective role collapse cannot be reversed without losing the original Staff assignment mapping.');
    }
};
