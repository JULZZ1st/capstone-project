<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_is_redirected_to_dashboard(): void
    {
        $user = $this->userWithRole('secretary', ['email' => 'active@example.com']);

        $response = $this->post(route('login.store'), [
            'email' => ' Active@Example.com ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout_and_login_again(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('secretary', [
            'email' => 'maria.santos@example.com',
            'password' => Hash::make($password),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'maria.santos@example.com',
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_user_can_login_again_after_session_expires(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('secretary', [
            'email' => 'juan.delacruz@example.com',
            'password' => Hash::make($password),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertAuthenticatedAs($user);

        $this->flushSession();
        Auth::guard('web')->logout();
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'juan.delacruz@example.com',
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_incorrect_password_is_rejected(): void
    {
        $this->userWithRole('secretary', ['email' => 'active@example.com']);

        $this->post(route('login.store'), [
            'email' => 'active@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('auth')
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();
    }

    public function test_user_wrong_password_is_rejected(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('secretary', [
            'email' => 'ana.reyes@example.com',
            'password' => Hash::make($password),
        ]);

        $this->assertTrue(Hash::check($password, $user->password));

        $this->post(route('login.store'), [
            'email' => 'ana.reyes@example.com',
            'password' => 'WrongPass123!',
        ])->assertSessionHasErrors('auth')
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();
    }

    public function test_nonexistent_email_is_rejected(): void
    {
        $this->post(route('login.store'), [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('auth')
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();
    }

    public function test_login_validation_errors_stay_on_fields(): void
    {
        $this->post(route('login.store'), [
            'email' => 'not-an-email',
            'password' => '',
        ])->assertSessionHasErrors(['email', 'password'])
            ->assertSessionDoesntHaveErrors('auth');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->userWithRole('secretary', [
            'email' => 'deactivated@example.com',
            'status' => 'deactivated',
        ]);

        $this->post(route('login.store'), [
            'email' => 'deactivated@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('auth')
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(array_merge(['role_id' => $roleModel->id], $attributes));
    }
}
