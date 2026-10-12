<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        foreach (config('auth_defaults.roles') as $name => $role) {
            DB::table('roles')->insert([
                'name' => $name,
                'display_name' => $role['display_name'],
                'description' => $role['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('id');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('status', 50)->default(config('auth_defaults.user_status'))->after('password');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['role_id', 'status']);
        });

        DB::table('users')
            ->select(['id', 'name', 'role'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);
                $roleName = match ($user->role) {
                    'administrator', 'captain' => 'admin',
                    default => $user->role,
                };
                $roleId = DB::table('roles')->where('name', $roleName)->value('id')
                    ?? DB::table('roles')->where('name', config('auth_defaults.default_role'))->value('id');

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $nameParts[0] ?: 'User',
                    'last_name' => $nameParts[1] ?? '',
                    'role_id' => $roleId,
                    'status' => config('auth_defaults.user_status'),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
            $table->foreignId('role_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropIndex(['role_id', 'status']);
            $table->dropColumn(['role_id', 'first_name', 'last_name', 'status', 'last_login_at']);
        });

        Schema::dropIfExists('roles');
    }
};
