<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\SecurityPermission;
use App\Models\User;
use App\Models\UserSecurityProfile;
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
        $response->assertSee('System Security Administration');
        $response->assertSee('Authorized Administrative Personnel');
        $response->assertSee('Security Activity Trail Ledger');
        $response->assertSee(SecurityPermission::query()->firstOrFail()->name);
    }

    public function test_non_admin_cannot_view_system_security_administration_page(): void
    {
        $secretary = $this->userWithRole('secretary');
        $this->seed(SystemSecuritySeeder::class);

        $this->actingAs($secretary)
            ->get(route('system-security.index'))
            ->assertForbidden();
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

    public function test_admin_can_toggle_two_factor_enforcement_and_audit_event_is_recorded(): void
    {
        $admin = $this->userWithRole('admin');
        $secretary = $this->userWithRole('secretary');
        $this->seed(SystemSecuritySeeder::class);

        UserSecurityProfile::query()->updateOrCreate(
            ['user_id' => $secretary->id],
            ['two_factor_enabled' => true, 'two_factor_enforced' => false],
        );

        $response = $this->actingAs($admin)->patch(route('system-security.two-factor-enforcement.update', $secretary), [
            'enforced' => true,
        ]);

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('user_security_profiles', [
            'user_id' => $secretary->id,
            'two_factor_enforced' => true,
        ]);
        $this->assertDatabaseHas('security_activity_events', [
            'user_id' => $secretary->id,
            'actor_id' => $admin->id,
            'action' => 'two_factor_enforced',
        ]);
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
