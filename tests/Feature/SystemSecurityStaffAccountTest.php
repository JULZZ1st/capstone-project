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

        $response = $this->actingAs($admin)
            ->from(route('system-security.index'))
            ->post(route('system-security.users.store'), [
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

    public function test_admin_can_create_treasurer_account(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->from(route('system-security.index'))
            ->post(route('system-security.users.store'), [
            'first_name' => 'New',
            'last_name' => 'Treasurer',
            'email' => 'new.treasurer@example.test',
            'role' => 'treasurer',
            'password' => 'LongPassword123!',
            'password_confirmation' => 'LongPassword123!',
        ]);

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new.treasurer@example.test',
            'role_id' => Role::query()->where('name', 'treasurer')->value('id'),
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_created']);
    }

    public function test_workspace_admin_cannot_create_another_admin_account(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->from(route('system-security.index'))
            ->post(route('system-security.users.store'), [
            'first_name' => 'Second',
            'last_name' => 'Administrator',
            'email' => 'second.admin@example.test',
            'role' => 'admin',
            'password' => 'LongPassword123!',
            'password_confirmation' => 'LongPassword123!',
        ]);

        $response->assertRedirect(route('system-security.index'))
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'second.admin@example.test']);
    }

    public function test_invalid_staff_account_creation_shows_errors_and_preserves_form_values(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('system-security.index'))
            ->post(route('system-security.users.store'), [
                '_token' => csrf_token(),
                '_method' => 'POST',
                '_modal' => 'create',
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => 'invalid.admin@example.test',
                'role' => 'admin',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('system-security.index'))
            ->assertSessionHasErrors('password')
            ->assertSessionHas('_old_input.first_name', 'New');

        $this->actingAs($admin)
            ->get(route('system-security.index'))
            ->assertOk()
            ->assertSee('id="create-staff-account-details"', false)
            ->assertSee('open >', false)
            ->assertSee('The password field must be at least 12 characters.', false)
            ->assertSee('value="New"', false);
    }

    public function test_created_staff_accounts_complete_the_temporary_password_flow_for_each_role(): void
    {
        $admin = $this->userWithRole('admin');
        $temporaryPassword = 'TemporaryPassword123!';
        $newPassword = 'PermanentPassword456!';

        foreach (['secretary', 'treasurer'] as $role) {
            $email = "new.{$role}@example.test";

            $response = $this->actingAs($admin)->post(route('system-security.users.store'), [
                'first_name' => 'New',
                'last_name' => ucfirst($role),
                'email' => $email,
                'role' => $role,
                'password' => $temporaryPassword,
                'password_confirmation' => $temporaryPassword,
            ]);

            $response->assertRedirect(route('system-security.index'));

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'role_id' => Role::query()->where('name', $role)->value('id'),
                'must_change_password' => true,
                'status' => config('auth_defaults.user_status'),
            ]);

            $this->post(route('logout'));

            $this->post(route('login.store'), [
                'email' => $email,
                'password' => $temporaryPassword,
            ])->assertRedirect(route('dashboard', absolute: false));

            $this->get(route('dashboard'))
                ->assertRedirect(route('password.change'));

            $this->post(route('password.change.store'), [
                'current_password' => $temporaryPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])->assertRedirect(route('dashboard', absolute: false));

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'must_change_password' => false,
            ]);

            $this->post(route('logout'));

            $this->post(route('login.store'), [
                'email' => $email,
                'password' => $newPassword,
            ])->assertRedirect(route('dashboard', absolute: false));
        }
    }

    public function test_secretary_and_treasurer_cannot_access_user_management(): void
    {
        $secretary = $this->userWithRole('secretary');
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($secretary)
            ->get(route('system-security.index'))
            ->assertForbidden();

        $this->actingAs($treasurer)
            ->get(route('system-security.index'))
            ->assertForbidden();
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

    public function test_treasurer_cannot_create_or_assign_an_admin_account(): void
    {
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)
            ->post(route('system-security.users.store'), [
                'first_name' => 'Unauthorized',
                'last_name' => 'Administrator',
                'email' => 'unauthorized.admin@example.test',
                'role' => 'admin',
                'password' => 'LongPassword123!',
                'password_confirmation' => 'LongPassword123!',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.admin@example.test']);
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

    public function test_workspace_admin_cannot_modify_another_admin_account(): void
    {
        $admin = $this->userWithRole('admin');
        $otherAdmin = User::factory()->create([
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
        ]);

        $this->actingAs($admin)
            ->patch(route('system-security.users.update', $otherAdmin), [
                'first_name' => 'Edited',
                'last_name' => 'Admin',
                'email' => 'edited-admin@example.test',
                'role' => 'secretary',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'email' => $otherAdmin->email,
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
        ]);
        $this->assertDatabaseMissing('security_activity_events', [
            'user_id' => $otherAdmin->id,
            'action' => 'staff_account_updated',
        ]);
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

    public function test_workspace_admin_can_deactivate_staff_but_not_an_admin_account(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->userWithRole('secretary');

        // Deactivate non-last admin should work
        $response = $this->actingAs($admin)->patch(route('system-security.users.deactivate', $other));

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_deactivated']);

        // Admin accounts are outside the workspace Admin staff-management scope.
        $this->actingAs($admin)
            ->patch(route('system-security.users.deactivate', $admin))
            ->assertForbidden();
    }

    public function test_deactivated_account_remains_visible_and_can_be_reactivated(): void
    {
        $admin = $this->userWithRole('admin');
        $user = User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'secretary'])->id,
            'status' => 'deactivated',
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('system-security.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($user->email);
        $indexResponse->assertSee('Deactivated');
        $indexResponse->assertSee('This account is disabled and retained for audit history.');
        $indexResponse->assertSee('0 Active Accounts');

        $response = $this->actingAs($admin)->patch(route('system-security.users.update', $user), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => 'secretary',
            'status' => config('auth_defaults.user_status'),
        ]);

        $response->assertRedirect(route('system-security.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => config('auth_defaults.user_status')]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'staff_account_updated', 'user_id' => $user->id]);
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
