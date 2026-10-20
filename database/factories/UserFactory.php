<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user): void {
            $workspace = Workspace::query()->firstOrCreate(
                ['slug' => 'legacy'],
                ['name' => 'Legacy Workspace', 'status' => 'active'],
            );

            if ($workspace !== null && $user->role_id !== null) {
                $workspace->memberships()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role_id' => $user->role_id, 'status' => 'active'],
                );
                app(WorkspaceContext::class)->set($workspace);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $role = Role::query()->firstOrCreate(
            ['name' => config('auth_defaults.default_role')],
            config('auth_defaults.roles.'.config('auth_defaults.default_role')),
        );

        return [
            'role_id' => $role->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $firstName.' '.$lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => config('auth_defaults.user_status'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
