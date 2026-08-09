<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_log_a_cash_contribution(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 750,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => config('donations.default_status.cash'),
            'amount' => 750,
            'payment_method' => 'cash',
            'created_by' => $user->id,
        ]);
    }

    public function test_cash_contribution_does_not_require_items(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 100,
            'payment_method' => 'gcash',
            'reference_number' => 'GC-1001',
            // No "items" key sent at all, mirroring a genuine cash submission.
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertSame(0, \App\Models\ContributionItem::query()->count());
    }

    public function test_in_kind_contribution_still_requires_at_least_one_item(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_in_kind_contribution_can_still_be_logged_with_items(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'type' => 'in_kind',
        ]);
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Rice',
            'category' => 'food',
        ]);
    }

    private function donor(array $overrides = []): Donor
    {
        return Donor::query()->create(array_merge([
            'donor_type' => 'individual',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ], $overrides));
    }
}
