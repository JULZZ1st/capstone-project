<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\SecurityPermission;
use App\Models\User;
use Database\Seeders\SystemSecuritySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSecurityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_security_administration_page(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(SystemSecuritySeeder::class);

        $response = $this->actingAs($admin)->get(route('system-security.index'));

        $response->assertOk();
        $response->assertSee('Staff Account Management');
        $response->assertSee('Create Staff Account');
        $response->assertSee('Security Activity Trail');
        $response->assertSee('Admin and staff accounts', false);
        $response->assertDontSee('<option value="admin">Administrator</option>', false);
        $response->assertSee('value="staff"', false);
        $response->assertDontSee('TOTP');
        $response->assertDontSee('Simulated Key');
        $response->assertDontSee('Task permissions');
        $this->assertDatabaseHas('security_permissions', ['name' => SecurityPermission::query()->firstOrFail()->name]);
    }

    public function test_staff_cannot_view_system_security_administration_page(): void
    {
        $staff = $this->userWithRole('staff');
        $this->seed(SystemSecuritySeeder::class);

        $response = $this->actingAs($staff)->get(route('system-security.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_filter_security_activity_events(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(SystemSecuritySeeder::class);

        SecurityActivityEvent::query()->create([
            'user_id' => $admin->id,
            'actor_id' => $admin->id,
            'action' => 'permission_reviewed',
            'summary' => 'Reviewed administrative security permissions.',
            'ip_address' => '10.0.0.15',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('system-security.index', [
            'action' => 'permission_reviewed',
            'search' => 'permissions',
        ]));

        $response->assertOk();
        $response->assertSee('Reviewed administrative security permissions.');
    }

    public function test_unused_two_factor_controls_are_disabled_without_dropping_legacy_storage(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seed(SystemSecuritySeeder::class);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('system-security.two-factor-enforcement.update'));
        $this->assertDatabaseCount('user_security_profiles', 0);
        $this->actingAs($admin)->get(route('system-security.index'))
            ->assertOk()
            ->assertDontSee('two-factor-enforcement')
            ->assertDontSee('2FA Authorization');
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            [
                'display_name' => ucfirst($role),
                'description' => ucfirst($role).' role',
            ],
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
