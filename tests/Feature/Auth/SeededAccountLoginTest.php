<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeededAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    public static function accounts(): array
    {
        return [
            'admin' => ['admin@barangay.test', 'Admin@2026_Test', 'admin'],
            'secretary' => ['secretary@barangay.test', 'Secretary@2026_Test', 'staff'],
            'treasurer' => ['treasurer@barangay.test', 'Treasurer@2026_Test', 'staff'],
        ];
    }

    #[DataProvider('accounts')]
    public function test_seeded_account_can_log_in_and_open_the_dashboard(string $email, string $password, string $role): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseMissing('users', ['email' => 'gina@gmail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'staff@barangay.test']);
        $user = User::query()->where('email', $email)->firstOrFail();
        $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        $this->assertSame($role, $user->roleName());
        $this->assertFalse($user->must_change_password);

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => $password,
        ])->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($workspace->id, session('active_workspace_id'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_reseeding_does_not_duplicate_accounts_or_memberships(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('workspace_memberships', 3);

        foreach (self::accounts() as [$email, $password, $role]) {
            $this->assertSame(1, User::query()->where('email', $email)->count());
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame(1, $user->workspaceMemberships()->count());
            $this->assertSame($user->role_id, $user->workspaceMemberships()->firstOrFail()->role_id);
        }
    }

    public function test_accounts_can_be_seeded_after_all_workspaces_are_removed(): void
    {
        app(WorkspaceContext::class)->clear();
        Workspace::query()->delete();

        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('workspace_memberships', 3);
        $workspace = Workspace::query()->sole();

        foreach (self::accounts() as [$email, $password]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($workspace->id, $user->workspaceMemberships()->sole()->workspace_id);
            $this->post(route('login.store'), ['email' => $email, 'password' => $password])
                ->assertSessionDoesntHaveErrors()
                ->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticatedAs($user);
            $this->get(route('dashboard'))->assertOk();
            $this->post(route('logout'));
        }
    }
}
