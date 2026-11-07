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
        $response->assertSee('Donor');
        $response->assertSee('Location');
        $response->assertSee('sticky top-0', false);
        $response->assertSee('data-dialog-open="remove-donor-modal-'.$donor->id.'"', false);
        $response->assertSee('Confirm Delete');
        $response->assertSee('Donation History');
        $response->assertSee('view-donor-modal-'.$donor->id);
        $response->assertSee('aria-label="Actions for '.$donor->full_name.'"', false);
        $response->assertDontSee('onsubmit="return confirm(', false);
    }

    public function test_create_and_edit_forms_render_three_wizard_steps(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $response = $this->actingAs($admin)->get(route('donors.index'));

        $response->assertOk();
        $response->assertSee('Donor profile steps');
        $response->assertSee('data-wizard-step-title="Basic & Contact Information"', false);
        $response->assertSee('data-wizard-step-title="Address Information"', false);
        $response->assertSee('data-wizard-step-title="Additional Information"', false);
        $response->assertSee('data-dialog-open="edit-donor-modal-'.$donor->id.'"', false);
        $response->assertSee('data-wizard-next', false);
        $response->assertSee('data-wizard-submit', false);
        $response->assertSee('data-wizard-error role="alert" hidden', false);
    }

    public function test_donor_can_be_created_with_cascading_address_selection(): void
    {
        $admin = $this->userWithRole('admin');
        $region = \Schoolees\Psgc\Models\Region::query()->create(['code' => '9900000000', 'name' => 'Test Region']);
        $province = \Schoolees\Psgc\Models\Province::query()->create(['code' => '9900100000', 'name' => 'Test Province', 'region_code' => $region->code]);
        $city = \Schoolees\Psgc\Models\City::query()->create(['code' => '9900100100', 'name' => 'Test City', 'region_code' => $region->code, 'province_code' => $province->code]);
        $barangay = \Schoolees\Psgc\Models\Barangay::query()->create(['code' => '9900100100001', 'name' => 'Test Barangay', 'city_code' => $city->code]);

        $response = $this->actingAs($admin)->post(route('donors.store'), [
            'donor_type' => 'individual',
            'first_name' => 'Cascading',
            'last_name' => 'Address',
            'contact_number' => '09170000001',
            'region' => $region->name,
            'province' => $province->name,
            'city_municipality' => $city->name,
            'barangay' => $barangay->name,
        ]);

        $response->assertRedirect(route('donors.index'));
        $response->assertSessionDoesntHaveErrors();

        $donor = Donor::query()->where('contact_number', '09170000001')->firstOrFail();
        $this->assertSame($region->name, $donor->region);
        $this->assertSame($province->name, $donor->province);
        $this->assertSame($city->name, $donor->city_municipality);
        $this->assertSame($barangay->name, $donor->barangay);

        $showResponse = $this->actingAs($admin)->get(route('donors.show', $donor));
        $showResponse->assertOk();
        $showResponse->assertSee($region->name);
        $showResponse->assertSee($province->name);
        $showResponse->assertSee($city->name);
        $showResponse->assertSee($barangay->name);
    }

    public function test_fresh_create_form_defaults_address_but_respects_explicit_clear(): void
    {
        $admin = $this->userWithRole('admin');
        \Schoolees\Psgc\Models\Region::query()->create(['code' => '700000000', 'name' => 'Region VII (Central Visayas)']);
        \Schoolees\Psgc\Models\City::query()->create(['code' => '730600000', 'name' => 'City of Cebu', 'region_code' => '700000000']);

        $freshResponse = $this->actingAs($admin)->get(route('donors.index'));
        $freshResponse->assertOk();
        $freshResponse->assertSee('value="Region VII (Central Visayas)"', false);
        $freshResponse->assertSee('>Central Visayas</div>', false);
        $freshResponse->assertSee('value="City of Cebu" data-code="730600000"', false);

        $failedSubmit = $this->from(route('donors.index'))->actingAs($admin)->post(route('donors.store'), [
            'donor_type' => 'individual',
            'first_name' => '',
            'last_name' => '',
            'contact_number' => '',
            'region' => '',
            'province' => '',
            'city_municipality' => '',
            'barangay' => '',
        ]);
        $failedSubmit->assertRedirect(route('donors.index'));
        $failedSubmit->assertSessionHasErrors();

        $redisplayed = $this->actingAs($admin)->get(route('donors.index'));
        $redisplayed->assertOk();
        $redisplayed->assertDontSee('value="City of Cebu" data-code="730600000" selected', false);
    }

    public function test_editing_donor_with_non_matching_legacy_address_does_not_break(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $response = $this->actingAs($admin)->patch(route('donors.update', $donor), [
            'donor_type' => 'individual',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'contact_number' => $donor->contact_number,
            'barangay' => $donor->address,
        ]);

        $response->assertRedirect(route('donors.index'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'first_name' => 'Updated']);
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

    public function test_invalid_create_reopens_the_form_at_the_basic_and_contact_step(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('donors.index'))
            ->post(route('donors.store'), [
                '_modal' => 'create',
                'donor_type' => 'individual',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'address' => 'San Isidro',
            ])
            ->assertRedirect(route('donors.index'))
            ->assertSessionHasErrors('contact_number');

        $response = $this->get(route('donors.index'))->assertOk();

        $this->assertMatchesRegularExpression('/<dialog id="create-donor-modal"[^>]*\\sopen\\s*>/', $response->getContent());
        $response->assertSee('data-wizard-start-step="0"', false);
        $this->assertDatabaseCount('donors', 0);
    }

    public function test_invalid_edit_reopens_the_form_at_the_address_step(): void
    {
        $admin = $this->userWithRole('admin');
        $donor = $this->donor();

        $this->actingAs($admin)
            ->from(route('donors.index'))
            ->patch(route('donors.update', $donor), [
                '_modal' => 'edit-'.$donor->id,
                'donor_type' => 'individual',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'contact_number' => '09171234567',
                'region' => str_repeat('x', 256),
            ])
            ->assertRedirect(route('donors.index'))
            ->assertSessionHasErrors('region');

        $response = $this->get(route('donors.index'))->assertOk();

        $this->assertMatchesRegularExpression('/<dialog id="edit-donor-modal-'.$donor->id.'"[^>]*\\sopen\\s*>/', $response->getContent());
        $response->assertSee('data-wizard-start-step="1"', false);
        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'address' => $donor->address]);
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
