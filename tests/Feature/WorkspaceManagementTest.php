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

    public function test_super_admin_can_create_workspace_and_assigned_admin(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $gina = User::factory()->create([
            'email' => 'gina@example.test',
            'role_id' => $adminRole->id,
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($gina)->post(route('workspaces.store'), [
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

    public function test_regular_admin_cannot_open_or_create_platform_workspaces(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('workspaces.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('workspaces.store'), [
            'name' => 'Not Allowed',
            'admin_first_name' => 'Admin',
            'admin_last_name' => 'B',
            'admin_email' => 'admin.b@example.test',
            'admin_password' => 'TemporaryPassword123!',
            'admin_password_confirmation' => 'TemporaryPassword123!',
        ])->assertForbidden();
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

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role'],
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
