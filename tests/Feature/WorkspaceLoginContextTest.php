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

    public function test_non_super_admin_login_replaces_stale_workspace_context(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $legacy = Workspace::query()->where('slug', 'legacy')->firstOrFail();
        $workspace = Workspace::query()->create(['name' => 'Workspace B', 'slug' => 'workspace-b', 'status' => 'active']);
        $user = User::factory()->create([
            'email' => 'admin.b@example.test',
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
        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $workspace = Workspace::query()->create([
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
            'status' => 'active',
        ]);
        $admin = User::factory()->create([
            'email' => 'admin.a@example.test',
            'role_id' => $role->id,
            'password' => 'TemporaryPassword123!',
            'must_change_password' => true,
        ]);
        $workspace->memberships()->create(['user_id' => $admin->id, 'role_id' => $role->id, 'status' => 'active']);

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
}
