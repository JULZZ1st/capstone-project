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

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role'],
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
