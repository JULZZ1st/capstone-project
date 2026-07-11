<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'terms' => '1',
        ]);

        $user = User::query()->where('email', 'maria.santos@example.com')->first();

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertSame(config('auth_defaults.default_role'), $user->roleName());
        $this->assertDatabaseHas('roles', [
            'name' => config('auth_defaults.default_role'),
        ]);
    }

    public function test_registration_creates_configured_default_role_when_missing(): void
    {
        Role::query()->delete();

        $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'juan.delacruz@example.com',
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => config('auth_defaults.default_role'),
        ]);
    }
}
