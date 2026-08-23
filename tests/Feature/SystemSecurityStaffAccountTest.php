<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSecurityStaffAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_account(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post(route('system-security.users.store'), [
            'first_name' => 'New',
            'last_name' => 'Staff',
            'email' => 'new.staff@example.test',
            'role' => 'secretary',
            'password' => 'LongPassword123!',
            'password_confirmation' => 'LongPassword123!',
        ]);

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('users', ['email' => 'new.staff@example.test']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_created']);
    }

    public function test_non_admin_cannot_create_staff_account(): void
    {
        $secretary = $this->userWithRole('secretary');

        $this->actingAs($secretary)
            ->post(route('system-security.users.store'), [
                'first_name' => 'New',
                'last_name' => 'Staff',
                'email' => 'new.staff2@example.test',
                'role' => 'secretary',
                'password' => 'LongPassword123!',
                'password_confirmation' => 'LongPassword123!',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_edit_staff_account(): void
    {
        $admin = $this->userWithRole('admin');
        $user = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name'=>'secretary'])->id]);

        $response = $this->actingAs($admin)->patch(route('system-security.users.update', $user), [
            'first_name' => 'Edited',
            'last_name' => 'Name',
            'email' => 'edited@example.test',
            'role' => 'treasurer',
        ]);

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('users', ['email' => 'edited@example.test']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_updated']);
    }

    public function test_non_admin_cannot_edit_staff_account(): void
    {
        $secretary = $this->userWithRole('secretary');
        $user = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name'=>'secretary'])->id]);

        $this->actingAs($secretary)
            ->patch(route('system-security.users.update', $user), [
                'first_name' => 'Edited',
                'last_name' => 'Name',
                'email' => 'edited2@example.test',
                'role' => 'treasurer',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_deactivate_staff_account_but_not_last_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->userWithRole('admin');

        // Deactivate non-last admin should work
        $response = $this->actingAs($admin)->patch(route('system-security.users.deactivate', $other));

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_deactivated']);

        // Attempt to deactivate the last remaining admin should fail (deactivate $admin now)
        $this->actingAs($admin)
            ->patch(route('system-security.users.deactivate', $admin))
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_deactivate_staff_account(): void
    {
        $secretary = $this->userWithRole('secretary');
        $user = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name'=>'secretary'])->id]);

        $this->actingAs($secretary)
            ->patch(route('system-security.users.deactivate', $user))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role']
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
