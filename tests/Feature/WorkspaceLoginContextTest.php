<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceLoginContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_opens_the_active_workspace_and_dashboard_directly(): void
    {
        $role = $this->adminRole();
        $gina = User::factory()->create([
            'email' => 'gina.no-context@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
        ]);
        $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        $this->post(route('login.store'), [
            'email' => $gina->email,
            'password' => 'PermanentPassword456!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($gina);
        $this->assertSame($workspace->id, session('active_workspace_id'));
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Workspace Management');
    }

    public function test_admin_login_replaces_stale_workspace_context_with_the_active_workspace(): void
    {
        $role = $this->adminRole();
        $gina = User::factory()->create([
            'email' => 'gina.stale-context@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('PermanentPassword456!'),
        ]);
        $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        $this->withSession(['active_workspace_id' => 999999])
            ->post(route('login.store'), [
                'email' => $gina->email,
                'password' => 'PermanentPassword456!',
            ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame($workspace->id, session('active_workspace_id'));
    }

    public function test_admin_login_does_not_preserve_a_manually_selected_workspace(): void
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
        $defaultWorkspace = Workspace::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        $this->withSession(['active_workspace_id' => $workspace->id])
            ->post(route('login.store'), [
                'email' => $gina->email,
                'password' => 'PermanentPassword456!',
            ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame($workspace->id, $defaultWorkspace->id);
        $this->assertSame($defaultWorkspace->id, session('active_workspace_id'));
    }

    public function test_staff_login_replaces_stale_workspace_context(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'staff'], ['display_name' => 'Staff']);
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

    public function test_admin_can_log_in_again_after_changing_temporary_password_without_workspace_selection(): void
    {
        $role = $this->adminRole();
        $workspace = Workspace::query()->create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $workspaceAdmin = User::factory()->create([
            'email' => 'admin.a@example.test',
            'role_id' => $role->id,
            'password' => Hash::make('TemporaryPassword123!'),
            'must_change_password' => true,
        ]);
        $workspace->memberships()->create([
            'user_id' => $workspaceAdmin->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin.a@example.test',
            'password' => 'TemporaryPassword123!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->post(route('password.change.store'), [
            'current_password' => 'TemporaryPassword123!',
            'password' => 'PermanentPassword456!',
            'password_confirmation' => 'PermanentPassword456!',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => 'admin.a@example.test',
            'password' => 'PermanentPassword456!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame($workspace->id, session('active_workspace_id'));
    }

    public function test_workspace_management_routes_are_not_available(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole()->id]);

        $this->actingAs($admin)->get('/workspaces')->assertNotFound();
        $this->actingAs($admin)->post('/workspaces')->assertNotFound();
    }

    private function adminRole(): Role
    {
        return Role::query()->firstOrCreate(['name' => Role::ADMIN], [
            'display_name' => 'Admin',
            'description' => 'Manages the barangay operations system.',
        ]);
    }
}
