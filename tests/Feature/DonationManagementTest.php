<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_accessible(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertStatus(200);
        $response->assertSee('Total Cash Ledger');
    }

    public function test_individual_donor_can_be_created_with_first_and_last_name(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/donors', [
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'address' => '12 Test Street',
            'barangay' => 'San Isidro',
            'city_municipality' => 'Quezon City',
            'province' => 'Metro Manila',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'notes' => 'Regular donor',
        ]);

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseHas('donors', [
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'contact_person' => null,
            'address' => '12 Test Street',
            'barangay' => 'San Isidro',
            'city_municipality' => 'Quezon City',
            'province' => 'Metro Manila',
            'status' => config('donors.defaults.status'),
        ]);
    }

    public function test_organization_donor_can_be_created_with_organization_name_and_contact_person(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/donors', [
            'donor_type' => 'organization',
            'organization_name' => 'Helping Hands Org',
            'contact_person' => 'Ana Reyes',
            'address' => 'Brgy. Mabini',
            'contact_number' => '09170000000',
            'email' => 'help@example.com',
        ]);

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseHas('donors', [
            'donor_type' => 'organization',
            'first_name' => null,
            'last_name' => null,
            'full_name' => 'Helping Hands Org',
            'contact_person' => 'Ana Reyes',
            'email' => 'help@example.com',
        ]);
    }

    public function test_create_donor_modal_uses_conditional_name_fields(): void
    {
        $individual = $this->actingAs(User::factory()->create())->get(route('donors.index'));

        $individual->assertOk();
        $individual->assertSee('First Name');
        $individual->assertSee('Last Name');
        $individual->assertSee('data-individual-name-field', false);
        $individual->assertDontSee('name="status"', false);

        $organization = $this->actingAs(User::factory()->create())
            ->withSession([
                '_old_input' => [
                    '_modal' => 'create',
                    'donor_type' => 'organization',
                ],
            ])
            ->get(route('donors.index'));

        $organization->assertOk();
        $organization->assertSee('Organization Name');
        $organization->assertSee('Contact Person');
        $organization->assertSee('data-organization-name-field', false);
        $organization->assertSee('data-contact-person-field', false);
    }

    public function test_individual_name_fields_are_required_for_individual_donors(): void
    {
        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            'donor_type' => 'individual',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234568',
        ])->assertSessionHasErrors(['first_name', 'last_name'])
            ->assertSessionDoesntHaveErrors('organization_name');
    }

    public function test_organization_name_is_required_for_organization_donors(): void
    {
        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            'donor_type' => 'organization',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234568',
        ])->assertSessionHasErrors('organization_name')
            ->assertSessionDoesntHaveErrors(['first_name', 'last_name']);
    }

    public function test_create_donor_defaults_status_to_active_for_new_donors(): void
    {
        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            'donor_type' => 'individual',
            'first_name' => 'Default',
            'last_name' => 'Donor',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234568',
            'email' => null,
            'status' => 'inactive',
        ])->assertRedirect(route('donors.index'));

        $this->assertDatabaseHas('donors', [
            'full_name' => 'Default Donor',
            'status' => config('donors.defaults.status'),
        ]);
    }

    public function test_likely_duplicate_individual_donor_warns_before_creating_another_record(): void
    {
        Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '0917-123-4567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            '_modal' => 'create',
            'donor_type' => 'individual',
            'first_name' => '  maria ',
            'last_name' => ' santos  ',
            'address' => 'Another address',
            'contact_number' => '09171234567',
            'email' => 'MARIA@example.com',
        ])->assertRedirect(route('donors.index'))
            ->assertSessionHas('warning')
            ->assertSessionHas('duplicate_donor_detected', true);

        $this->assertSame(1, Donor::query()->count());
    }

    public function test_likely_duplicate_organization_donor_warns_before_creating_another_record(): void
    {
        Donor::query()->create([
            'donor_type' => 'organization',
            'full_name' => 'Helping Hands Org',
            'address' => 'Brgy. Mabini',
            'contact_number' => '09170000000',
            'email' => 'help@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            '_modal' => 'create',
            'donor_type' => 'organization',
            'organization_name' => ' helping   hands org ',
            'contact_person' => 'Ana Reyes',
            'address' => 'Another address',
            'contact_number' => '09170000000',
        ])->assertRedirect(route('donors.index'))
            ->assertSessionHas('warning')
            ->assertSessionHas('duplicate_donor_detected', true);

        $this->assertSame(1, Donor::query()->count());
    }

    public function test_confirmed_likely_duplicate_can_be_created(): void
    {
        Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $this->actingAs(User::factory()->create())->post(route('donors.store'), [
            '_modal' => 'create',
            '_confirm_duplicate' => '1',
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'address' => 'Another address',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
        ])->assertRedirect(route('donors.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Donor::query()->count());
    }

    public function test_donor_management_search_filters_table_rows_by_split_names(): void
    {
        Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        Donor::query()->create([
            'donor_type' => 'organization',
            'full_name' => 'Helping Hands Org',
            'address' => 'Brgy. Mabini',
            'contact_number' => '09170000000',
            'email' => 'help@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('donors.index', [
            'search' => 'Santos',
        ]));

        $response->assertOk();
        $response->assertSee('Maria Santos');
        $response->assertDontSee('Helping Hands Org');
    }

    public function test_donor_profile_can_be_updated_with_existing_full_name_flow(): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $response = $this->actingAs(User::factory()->create())->patch(route('donors.update', $donor), [
            'donor_type' => 'organization',
            'full_name' => 'Maria Santos Foundation',
            'contact_person' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'preferred_contact_method' => 'email',
            'email' => 'foundation@example.com',
            'status' => config('donors.defaults.status'),
            'barangay_area' => 'Zone 1',
            'notes' => 'Updated profile',
        ]);

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'donor_type' => 'organization',
            'full_name' => 'Maria Santos Foundation',
            'email' => 'foundation@example.com',
        ]);
    }

    public function test_donor_history_shows_only_selected_donor_donations(): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);
        $otherDonor = Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Juan',
            'last_name' => 'Reyes',
            'full_name' => 'Juan Reyes',
            'address' => 'Brgy. Mabini',
            'contact_number' => '09170000000',
            'email' => 'juan@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 500,
            'payment_method' => 'cash',
            'notes' => 'Medical support',
            'received_at' => now(),
        ]);
        Contribution::query()->create([
            'donor_id' => $otherDonor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 900,
            'payment_method' => 'cash',
            'notes' => 'Other donor support',
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('donors.show', $donor));

        $response->assertOk();
        $response->assertSee('Medical support');
        $response->assertDontSee('Other donor support');
    }

    public function test_treasurer_can_view_shared_donors_but_cannot_modify_them(): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'first_name' => 'Shared',
            'last_name' => 'Donor',
            'full_name' => 'Shared Donor',
            'address' => 'Shared Address',
            'contact_number' => '09171234567',
            'status' => config('donors.defaults.status'),
        ]);
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)
            ->get(route('donors.index'))
            ->assertOk()
            ->assertSee('Shared Donor')
            ->assertDontSee('data-dialog-open="edit-donor-modal-'.$donor->id.'"', false);

        $this->actingAs($treasurer)
            ->patch(route('donors.update', $donor), [
                'donor_type' => 'individual',
                'first_name' => 'Changed',
                'last_name' => 'Donor',
                'address' => 'Changed Address',
                'contact_number' => '09170000000',
            ])
            ->assertForbidden();

        $this->actingAs($treasurer)
            ->delete(route('donors.destroy', $donor))
            ->assertForbidden();

        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'full_name' => 'Shared Donor']);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
