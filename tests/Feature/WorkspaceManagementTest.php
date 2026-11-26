<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_any_active_workspace_in_the_system_cannot_open_dashboard(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        // TestCase::setUp() seeds an active "legacy" workspace for every test; deactivate it so
        // this scenario genuinely has zero active workspaces anywhere in the system.
        Workspace::query()->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_workspace_admin_cannot_create_another_admin_from_staff_console(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('system-security.index'))
            ->post(route('system-security.users.store'), [
                'first_name' => 'Second',
                'last_name' => 'Administrator',
                'email' => 'second.admin@example.test',
                'role' => 'admin',
                'password' => 'LongPassword123!',
                'password_confirmation' => 'LongPassword123!',
            ])
            ->assertRedirect(route('system-security.index'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'second.admin@example.test']);
    }

    public function test_admin_can_open_workspace_management_without_an_active_workspace(): void
    {
        $admin = $this->userWithRole('admin');
        Workspace::query()->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('Workspace Management');
    }

    public function test_non_admin_cannot_open_workspace_management(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(route('workspaces.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_workspace_and_its_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('workspaces.store'), [
                'name' => 'New Workspace',
                'admin_first_name' => 'New',
                'admin_last_name' => 'Admin',
                'admin_email' => 'new.admin@example.test',
                'admin_password' => 'LongPassword123!',
                'admin_password_confirmation' => 'LongPassword123!',
            ])
            ->assertRedirect(route('workspaces.index'));

        $workspace = Workspace::query()->where('name', 'New Workspace')->firstOrFail();
        $newAdmin = User::query()->where('email', 'new.admin@example.test')->firstOrFail();

        $this->assertSame('new-workspace', $workspace->slug);
        $this->assertTrue($newAdmin->must_change_password);
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $newAdmin->id,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role'],
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
