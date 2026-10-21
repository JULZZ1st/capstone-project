<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('role_id')->nullable()->change();
            });
        }

        $gina = DB::table('users')->where('email', 'gina@gmail.com')->first(['id']);

        if ($gina === null) {
            return;
        }

        DB::table('users')->where('id', $gina->id)->update([
            'role_id' => null,
            'is_super_admin' => true,
        ]);

        if (Schema::hasTable('workspace_memberships')) {
            DB::table('workspace_memberships')->where('user_id', $gina->id)->delete();
        }
    }

    public function down(): void
    {
        // Gina's former role and membership are intentionally not recreated.
        // Restoring them would violate the platform-only security boundary.
    }
};
