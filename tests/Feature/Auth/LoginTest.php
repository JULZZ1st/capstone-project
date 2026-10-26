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
        $user = $this->userWithRole('staff', ['email' => 'active@example.com']);

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
        $user = $this->userWithRole('staff', [
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

    public function test_remember_me_uses_laravels_recaller_cookie_and_logout_clears_it(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('staff', [
            'email' => 'remembered@example.com',
            'password' => Hash::make($password),
        ]);
        $recallerName = Auth::guard('web')->getRecallerName();

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
            'remember' => '1',
        ]);

        $loginResponse->assertRedirect(route('dashboard', absolute: false));
        $recallerCookie = $loginResponse->getCookie($recallerName);
        $this->assertNotNull($recallerCookie);
        $this->assertAuthenticatedAs($user);

        $logoutResponse = $this
            ->withCookie($recallerName, $recallerCookie->getValue())
            ->post(route('logout'));

        $logoutResponse->assertRedirect(route('login'));
        $logoutResponse->assertCookieExpired($recallerName);
        $this->assertGuest();
    }

    public function test_login_without_remember_me_does_not_set_a_recaller_cookie(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('staff', [
            'email' => 'session-only@example.com',
            'password' => Hash::make($password),
        ]);
        $recallerName = Auth::guard('web')->getRecallerName();

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $loginResponse->assertRedirect(route('dashboard', absolute: false));
        $this->assertNull($loginResponse->getCookie($recallerName));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_again_after_session_expires(): void
    {
        $password = 'StrongPass123!';
        $user = $this->userWithRole('staff', [
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
        $this->userWithRole('staff', ['email' => 'active@example.com']);

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
        $user = $this->userWithRole('staff', [
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
        $this->userWithRole('staff', [
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
