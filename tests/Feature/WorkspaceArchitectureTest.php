<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SystemSecurity\CreateStaffAccount;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WorkspaceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__workspace-context-test', function (WorkspaceContext $context) {
            return response()->json(['workspace_id' => $context->id()]);
        })->middleware(['web', 'auth', 'workspace']);
    }

    public function test_gina_is_seeded_as_super_admin_without_losing_admin_access(): void
    {
        $this->seed();

        $gina = User::query()->where('email', 'gina@gmail.com')->firstOrFail();

        $this->assertTrue($gina->isSuperAdmin());
        $this->assertTrue($gina->hasRole('admin'));
        $this->assertDatabaseHas('workspace_memberships', [
            'user_id' => $gina->id,
            'workspace_id' => Workspace::query()->where('slug', 'legacy')->value('id'),
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($gina)
            ->withSession(['active_workspace_id' => Workspace::query()->where('slug', 'legacy')->value('id')])
            ->get('/')
            ->assertOk();
    }

    public function test_membership_resolves_active_workspace_into_request_context(): void
    {
        $role = Role::query()->create([
            'name' => 'workspace-secretary',
            'display_name' => 'Workspace Secretary',
            'description' => 'Test role',
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $workspace = Workspace::query()->create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $workspace->memberships()->create(['user_id' => $user->id, 'role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/__workspace-context-test')
            ->assertOk()
            ->assertJson(['workspace_id' => $workspace->id]);
    }

    public function test_user_cannot_activate_a_workspace_without_membership(): void
    {
        $role = Role::query()->create([
            'name' => 'workspace-treasurer',
            'display_name' => 'Workspace Treasurer',
            'description' => 'Test role',
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $workspace = Workspace::query()->create(['name' => 'Private Workspace', 'slug' => 'private-workspace']);

        $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get('/__workspace-context-test')
            ->assertForbidden();
    }

    public function test_super_admin_can_activate_an_authorized_platform_workspace(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $workspace = Workspace::query()->create(['name' => 'Managed Workspace', 'slug' => 'managed-workspace']);

        $this->actingAs($superAdmin)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get('/__workspace-context-test')
            ->assertOk()
            ->assertJson(['workspace_id' => $workspace->id]);
    }

    public function test_staff_created_with_an_active_workspace_receives_that_membership(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->firstOrCreate([
            'name' => 'admin',
        ], [
            'display_name' => 'Administrator',
            'description' => 'Administrator',
        ]);
        $secretaryRole = Role::query()->firstOrCreate([
            'name' => 'secretary',
        ], [
            'display_name' => 'Secretary',
            'description' => 'Secretary',
        ]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $workspace = Workspace::query()->create(['name' => 'Staff Workspace', 'slug' => 'staff-workspace']);
        $workspace->memberships()->create(['user_id' => $admin->id, 'role_id' => $adminRole->id]);

        $this->actingAs($admin)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get('/__workspace-context-test')
            ->assertOk();

        $staff = app(CreateStaffAccount::class)->handle([
            'role' => 'secretary',
            'first_name' => 'Workspace',
            'last_name' => 'Secretary',
            'email' => 'workspace.secretary@example.test',
            'password' => 'A secure password 123!',
        ], $admin);

        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $staff->id,
            'role_id' => $secretaryRole->id,
            'created_by' => $admin->id,
            'status' => 'active',
        ]);
    }
}
