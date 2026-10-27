<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_without_contribution_history_is_permanently_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $response = $this->actingAs($admin)->delete(route('donors.destroy', $donor));

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseMissing('donors', ['id' => $donor->id]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'donor_deleted', 'actor_id' => $admin->id]);
    }

    public function test_donor_with_contribution_history_is_deactivated_instead_of_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 1000,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('donors.destroy', $donor));

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'donor_deactivated', 'actor_id' => $admin->id]);
    }

    public function test_guest_cannot_remove_donor(): void
    {
        $donor = $this->donor();

        $this->delete(route('donors.destroy', $donor))->assertRedirect(route('login'));
        $this->assertDatabaseHas('donors', ['id' => $donor->id]);
    }

    public function test_donor_list_has_sticky_table_header_and_centered_remove_confirmation(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $response = $this->actingAs($admin)->get(route('donors.index'));

        $response->assertOk();
        $response->assertSee('Donor Name');
        $response->assertSee('City / Municipality');
        $response->assertSee('sticky top-0', false);
        $response->assertSee('aria-label="Actions for '.$donor->full_name.'"', false);
        $response->assertSee('view-donor-modal-'.$donor->id);
        $response->assertSee('data-dialog-open="remove-donor-modal-'.$donor->id.'"', false);
        $response->assertSee('Confirm Delete');
        $response->assertSee('Donation History');
        $response->assertDontSee('onsubmit="return confirm(', false);
    }

    public function test_donor_with_history_gets_a_centered_view_modal_and_safe_deactivation_confirmation(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 125,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('donors.index'));

        $response->assertOk()
            ->assertSee('class="m-auto w-[min(920px,calc(100vw-2rem))] max-h-[90vh]', false)
            ->assertSee('sticky top-0 z-10', false)
            ->assertSee('Confirm Deactivation');
    }

    public function test_create_and_edit_forms_render_four_wizard_steps(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $response = $this->actingAs($admin)->get(route('donors.index'));

        $response->assertOk();
        $response->assertSee('Donor profile steps');
        $response->assertSee('data-wizard-step-title="Basic Information"', false);
        $response->assertSee('data-wizard-step-title="Contact Information"', false);
        $response->assertSee('data-wizard-step-title="Address"', false);
        $response->assertSee('data-wizard-step-title="Additional Information"', false);
        $response->assertSee('data-dialog-open="edit-donor-modal-'.$donor->id.'"', false);
        $response->assertSee('data-wizard-next', false);
        $response->assertSee('data-wizard-submit', false);
        $response->assertSee('data-wizard-error role="alert" hidden', false);
    }

    private function donor(): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Test Donor '.random_int(1, 1000000),
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 1000000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role']);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
