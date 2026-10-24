<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SystemSecurity\CreateStaffAccount;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

    public function test_gina_is_seeded_as_admin_with_workspace_management_access(): void
    {
        $this->seed();

        $gina = User::query()->where('email', 'gina@gmail.com')->firstOrFail();

        $this->assertTrue($gina->hasRole(Role::ADMIN));
        $this->assertSame('Admin', $gina->roleDisplayName());
        $this->assertDatabaseMissing('roles', ['name' => 'super_admin']);
        $this->assertFalse(Schema::hasColumn('users', 'is_super_admin'));
        $this->assertDatabaseMissing('workspace_memberships', ['user_id' => $gina->id]);
        $workspace = Workspace::query()->where('slug', 'legacy')->firstOrFail();
        $this->assertTrue(app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canAccess($gina, $workspace));

        $this->actingAs($gina)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get('/__workspace-context-test')
            ->assertOk()
            ->assertJson(['workspace_id' => $workspace->id]);
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

    public function test_admin_can_activate_any_active_workspace_without_membership(): void
    {
        $role = Role::query()->firstOrCreate(['name' => Role::ADMIN], ['display_name' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $workspace = Workspace::query()->create(['name' => 'Managed Workspace', 'slug' => 'managed-workspace']);

        $this->actingAs($admin)
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
