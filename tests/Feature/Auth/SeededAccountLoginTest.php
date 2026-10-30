<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Workspace;
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
            'staff' => ['staff@barangay.test', 'Staff@2026_Test', 'staff'],
        ];
    }

    #[DataProvider('accounts')]
    public function test_seeded_account_can_log_in_and_open_the_dashboard(string $email, string $password, string $role): void
    {
        $this->seed(DatabaseSeeder::class);
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

        foreach (self::accounts() as [$email, $password, $role]) {
            $this->assertSame(1, User::query()->where('email', $email)->count());
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame(1, $user->workspaceMemberships()->count());
            $this->assertSame($user->role_id, $user->workspaceMemberships()->firstOrFail()->role_id);
        }
    }
}
