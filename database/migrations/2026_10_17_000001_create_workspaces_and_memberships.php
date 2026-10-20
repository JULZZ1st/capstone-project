<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 50)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status', 50)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        $legacyWorkspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Legacy Workspace',
            'slug' => 'legacy',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $users = DB::table('users')
            ->select(['id', 'role_id'])
            ->whereNotNull('role_id')
            ->get();

        foreach ($users as $user) {
            DB::table('workspace_memberships')->insert([
                'workspace_id' => $legacyWorkspaceId,
                'user_id' => $user->id,
                'role_id' => $user->role_id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('workspaces');
    }
};
