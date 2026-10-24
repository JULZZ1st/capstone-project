<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\CreateWorkspaceWithAdmin;
use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_workspace_and_assigned_admin(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('workspaces.store'), [
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
            'admin_first_name' => 'Admin',
            'admin_last_name' => 'A',
            'admin_email' => 'admin.a@example.test',
            'admin_password' => 'TemporaryPassword123!',
            'admin_password_confirmation' => 'TemporaryPassword123!',
        ]);

        $response->assertRedirect(route('workspaces.index'));
        $workspace = Workspace::query()->where('slug', 'workspace-a')->firstOrFail();
        $admin = User::query()->where('email', 'admin.a@example.test')->firstOrFail();

        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
        $this->assertTrue($admin->must_change_password);
    }

    public function test_secretary_and_treasurer_cannot_manage_workspaces(): void
    {
        foreach (['secretary', 'treasurer'] as $role) {
            $staff = $this->userWithRole($role);
            $this->actingAs($staff)->get(route('workspaces.index'))->assertForbidden();
            $this->post(route('workspaces.store'), [
                'name' => 'Not Allowed',
                'admin_first_name' => 'Admin',
                'admin_last_name' => 'B',
                'admin_email' => "{$role}.admin@example.test",
                'admin_password' => 'TemporaryPassword123!',
                'admin_password_confirmation' => 'TemporaryPassword123!',
            ])->assertForbidden();
        }
    }

    public function test_admin_can_manage_workspaces_and_open_operational_records(): void
    {
        $admin = $this->admin();
        $workspace = Workspace::query()->create(['name' => 'Managed Workspace', 'slug' => 'managed-workspace']);

        $this->actingAs($admin)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('Admins manage workspaces')
            ->assertSee('Open workspace');

        $this->post(route('workspaces.activate', $workspace))
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_admin_can_open_another_admins_workspace(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $admin = $this->admin();
        $anotherAdmin = User::factory()->create(['role_id' => $adminRole->id]);
        $workspace = Workspace::query()->create([
            'name' => 'Julius Workspace',
            'slug' => 'julius-workspace',
            'status' => 'active',
        ]);
        $workspace->memberships()->create([
            'user_id' => $anotherAdmin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('Open workspace');

        $this->assertTrue(app(WorkspaceAuthorizer::class)->canAccess($admin, $workspace));
    }

    public function test_admin_can_deactivate_and_reactivate_a_workspace(): void
    {
        $admin = $this->admin();
        $workspace = Workspace::query()->create(['name' => 'Toggle Workspace', 'slug' => 'toggle-workspace']);

        $this->actingAs($admin)
            ->patch(route('workspaces.toggle', $workspace))
            ->assertRedirect(route('workspaces.index'));
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'status' => 'inactive']);

        $this->patch(route('workspaces.toggle', $workspace))
            ->assertRedirect(route('workspaces.index'));
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'status' => 'active']);
    }

    public function test_admin_can_delete_empty_workspace_without_disabling_its_assigned_admin(): void
    {
        $admin = $this->admin();
        $workspace = app(CreateWorkspaceWithAdmin::class)->handle([
            'name' => 'Empty Workspace',
            'slug' => 'empty-workspace',
            'admin_first_name' => 'Workspace',
            'admin_last_name' => 'Admin',
            'admin_email' => 'empty.admin@example.test',
            'admin_password' => 'TemporaryPassword123!',
        ], $admin);
        $assignedAdmin = User::query()->where('email', 'empty.admin@example.test')->firstOrFail();

        $this->assertDatabaseHas('security_activity_events', [
            'workspace_id' => $workspace->id,
            'action' => 'workspace_created',
        ]);

        $this->actingAs($admin)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect(route('workspaces.index'));

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('users', ['id' => $assignedAdmin->id, 'status' => 'active']);
        $this->assertDatabaseMissing('workspace_memberships', ['workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('security_activity_events', [
            'workspace_id' => null,
            'action' => 'workspace_created',
        ]);
    }

    public function test_admin_cannot_delete_workspace_with_operational_records(): void
    {
        $admin = $this->admin();
        $workspace = Workspace::query()->create(['name' => 'Used Workspace', 'slug' => 'used-workspace']);
        Donor::query()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Saved',
            'last_name' => 'Donor',
            'full_name' => 'Saved Donor',
            'donor_type' => 'individual',
            'status' => 'active',
            'address' => 'Test Address',
            'contact_number' => '09000000000',
        ]);

        $this->actingAs($admin)
            ->from(route('workspaces.index'))
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect(route('workspaces.index'))
            ->assertSessionHasErrors('workspace');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_workspace_admin_cannot_create_another_admin_from_staff_console(): void
    {
        $admin = $this->userWithRole('admin');
        $workspace = Workspace::query()->create(['name' => 'Staff Workspace', 'slug' => 'staff-workspace']);

        $this->actingAs($admin)
            ->withSession(['active_workspace_id' => $workspace->id])
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

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role'],
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => Role::ADMIN], [
            'display_name' => 'Admin',
            'description' => 'Manages all workspaces.',
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
