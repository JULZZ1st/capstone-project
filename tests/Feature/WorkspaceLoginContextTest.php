<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\CreateWorkspaceWithAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceLoginContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_active_workspace_is_sent_to_workspace_management(): void
    {
        $role = $this->adminRole();
        $gina = User::factory()->create([
            'email' => 'gina.no-context@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
        ]);

        $this->post(route('login.store'), [
            'email' => $gina->email,
            'password' => 'PermanentPassword456!',
        ])->assertRedirect(route('workspaces.index', absolute: false));

        $this->assertAuthenticatedAs($gina);
        $this->assertNull(session('active_workspace_id'));
    }

    public function test_admin_with_stale_workspace_context_is_sent_to_workspace_management(): void
    {
        $role = $this->adminRole();
        $gina = User::factory()->create([
            'email' => 'gina.stale-context@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
        ]);

        $this->withSession(['active_workspace_id' => 999999])
            ->post(route('login.store'), [
                'email' => $gina->email,
                'password' => 'PermanentPassword456!',
            ])->assertRedirect(route('workspaces.index', absolute: false));

        $this->assertNull(session('active_workspace_id'));
    }

    public function test_admin_with_workspace_context_is_sent_to_management_and_context_is_cleared(): void
    {
        $role = $this->adminRole();
        $gina = User::factory()->create([
            'email' => 'gina.active-context@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
        ]);
        $workspace = Workspace::query()->create([
            'name' => 'Authorized Workspace',
            'slug' => 'authorized-workspace',
            'status' => 'active',
        ]);

        $this->withSession(['active_workspace_id' => $workspace->id])
            ->post(route('login.store'), [
                'email' => $gina->email,
                'password' => 'PermanentPassword456!',
            ])->assertRedirect(route('workspaces.index', absolute: false));

        $this->assertNull(session('active_workspace_id'));
    }

    public function test_staff_login_replaces_stale_workspace_context(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'secretary'], ['display_name' => 'Secretary']);
        $legacy = Workspace::query()->where('slug', 'legacy')->firstOrFail();
        $workspace = Workspace::query()->create(['name' => 'Workspace B', 'slug' => 'workspace-b', 'status' => 'active']);
        $user = User::factory()->create([
            'email' => 'secretary.b@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
            'must_change_password' => false,
        ]);
        $workspace->memberships()->create(['user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active']);

        $this->withSession(['active_workspace_id' => $legacy->id])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'PermanentPassword456!'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame($workspace->id, session('active_workspace_id'));
    }

    public function test_workspace_admin_can_log_in_again_after_changing_temporary_password(): void
    {
        $role = $this->adminRole();
        $admin = User::factory()->create([
            'email' => 'gina@example.test',
            'role_id' => $role->id,
        ]);

        $workspace = app(CreateWorkspaceWithAdmin::class)->handle([
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
            'admin_first_name' => 'Admin',
            'admin_last_name' => 'A',
            'admin_email' => 'admin.a@example.test',
            'admin_password' => 'TemporaryPassword123!',
        ], $admin);

        $this->post(route('login.store'), [
            'email' => 'admin.a@example.test',
            'password' => 'TemporaryPassword123!',
        ])->assertRedirect(route('workspaces.index', absolute: false));

        $this->post(route('password.change.store'), [
            'current_password' => 'TemporaryPassword123!',
            'password' => 'PermanentPassword456!',
            'password_confirmation' => 'PermanentPassword456!',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => 'admin.a@example.test',
            'password' => 'PermanentPassword456!',
        ])->assertRedirect(route('workspaces.index', absolute: false));

        $this->post(route('workspaces.activate', $workspace))
            ->assertRedirect(route('dashboard'));
        $this->assertSame($workspace->id, session('active_workspace_id'));
    }

    private function adminRole(): Role
    {
        return Role::query()->firstOrCreate(['name' => Role::ADMIN], [
            'display_name' => 'Admin',
            'description' => 'Manages all workspaces.',
        ]);
    }
}
