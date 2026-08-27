<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
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

    public function test_donor_can_be_created(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/donors', [
            'donor_type' => config('donors.defaults.type'),
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'notes' => 'Regular donor',
        ]);

        $response->assertRedirect(route('donors.index'));
        $this->assertDatabaseHas('donors', [
            'full_name' => 'Maria Santos',
            'donor_type' => config('donors.defaults.type'),
            'email' => 'maria@example.com',
        ]);
    }

    public function test_donor_management_search_filters_cards(): void
    {
        Donor::query()->create([
            'donor_type' => 'individual',
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
            'search' => 'Maria',
        ]));

        $response->assertOk();
        $response->assertSee('Maria Santos');
        $response->assertDontSee('Helping Hands Org');
    }

    public function test_donor_profile_can_be_updated(): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
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
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ]);
        $otherDonor = Donor::query()->create([
            'donor_type' => 'individual',
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
}
