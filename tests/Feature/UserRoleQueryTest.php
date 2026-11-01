<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_permission_checks_and_display_share_one_role_query(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'staff'], ['display_name' => 'Staff']);
        $user = User::factory()->create(['role_id' => $role->id])->fresh();

        DB::enableQueryLog();
        try {
            for ($check = 0; $check < 10; $check++) {
                $this->assertTrue($user->hasRole(['admin', 'staff']));
                $this->assertFalse($user->hasRole('admin'));
                $this->assertSame('Staff', $user->roleDisplayName());
            }

            $this->assertCount(1, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_legacy_role_fallback_is_preserved_after_repeated_checks(): void
    {
        $user = new User();
        $user->setRawAttributes(['role_id' => null, 'role' => 'staff']);

        for ($check = 0; $check < 2; $check++) {
            $this->assertTrue($user->hasRole('staff'));
            $this->assertFalse($user->hasRole('admin'));
            $this->assertSame(config('auth_defaults.roles.staff.display_name'), $user->roleDisplayName());
        }
    }

    public function test_user_without_a_role_is_denied(): void
    {
        $user = new User();

        $this->assertFalse($user->hasRole(['admin', 'staff']));
        $this->assertFalse($user->hasRole(['admin', 'staff']));
        $this->assertSame('', $user->roleDisplayName());
    }
}
