<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_staff_can_log_a_bank_transfer_contribution_for_an_active_workspace_donor(): void
    {
        $donor = $this->donor();
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-26001',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-26001',
            'created_by' => $staff->id,
        ]);
    }

    public function test_inactive_donor_is_rejected_before_contribution_is_saved(): void
    {
        $donor = $this->donor(['status' => 'inactive']);

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-INACTIVE-001',
        ]);

        $response->assertSessionHasErrors('donor_id');
        $this->assertDatabaseMissing('contributions', ['reference_number' => 'TRF-INACTIVE-001']);
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
        $this->assertSame(0, ContributionItem::query()->count());
    }

    public function test_cash_contribution_does_not_store_an_external_reference(): void
    {
        $contribution = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $this->donor()->id,
                'amount' => 100,
                'payment_method' => 'cash',
                'reference_number' => 'SHOULD-NOT-BE-STORED',
            ]);

        $contribution->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contributions', [
            'payment_method' => 'cash',
            'reference_number' => null,
        ]);
    }

    public function test_non_cash_payment_methods_require_external_references(): void
    {
        foreach (['gcash', 'maya', 'bank_transfer', 'check'] as $paymentMethod) {
            $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $this->donor()->id,
                'amount' => 100,
                'payment_method' => $paymentMethod,
            ]);

            $response->assertSessionHasErrors('reference_number');
        }
    }

    public function test_other_payment_method_requires_a_name_but_not_an_external_reference(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $missingName = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'other',
        ]);

        $missingName->assertSessionHasErrors('payment_method_name');
        $this->assertDatabaseCount('contributions', 0);

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'other',
            'payment_method_name' => 'PayMaya QR',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $contribution = Contribution::query()->firstOrFail();

        $this->assertSame('PayMaya QR', $contribution->payment_method_name);
        $this->assertNull($contribution->reference_number);
        $this->get(route('donations.index'))->assertSee('PayMaya QR');
        $this->get(route('donations.show', $contribution))->assertSee('PayMaya QR');
    }

    public function test_money_order_is_not_a_new_payment_method_but_historical_rows_remain_visible(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();
        $historical = Contribution::query()->create([
            'donor_id' => $donor->id,
            'created_by' => $user->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 500,
            'payment_method' => 'money_order',
            'reference_number' => 'MO-1001',
        ]);

        $this->actingAs($user)->get(route('donations.index'))
            ->assertSee('Money Order')
            ->assertDontSee('value="money_order"', false);
        $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'money_order',
            'reference_number' => 'MO-1002',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseHas('contributions', ['id' => $historical->id, 'payment_method' => 'money_order']);
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
                ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'rice_grains', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
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
            'subcategory' => 'rice_grains',
        ]);
    }

    public function test_subcategories_are_grouped_by_selected_category_for_the_modal(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('donations.index'));

        $response->assertOk();

        $subcategories = $response->viewData('subcategoriesByCategory');

        $this->assertContains('rice_grains', collect($subcategories['food'])->pluck('key'));
        $this->assertNotContains('cookware', collect($subcategories['food'])->pluck('key'));
        $this->assertContains('cookware', collect($subcategories['kitchen_supplies'])->pluck('key'));
    }

    public function test_invalid_cross_category_subcategory_is_rejected(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $this->donor()->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'cookware', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.subcategory');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_inactive_subcategory_is_rejected(): void
    {
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        InventorySubcategory::query()
            ->where('inventory_category_id', $food->id)
            ->where('key', 'rice_grains')
            ->update(['is_active' => false]);

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $this->donor()->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'rice_grains', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.subcategory');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_dynamic_multiple_item_rows_store_independent_subcategories(): void
    {
        $donor = $this->donor();

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'rice_grains', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
                ['name' => 'Pot', 'category' => 'kitchen_supplies', 'subcategory' => 'cookware', 'quantity' => 2, 'unit' => 'piece', 'condition' => 'good'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contribution_items', ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'rice_grains']);
        $this->assertDatabaseHas('contribution_items', ['name' => 'Pot', 'category' => 'kitchen_supplies', 'subcategory' => 'cookware']);
    }

    public function test_validation_error_restores_selected_category_and_subcategory_state(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('donations.index'))
            ->post(route('donations.store'), [
                'donation_type' => 'in_kind',
                'donor_id' => $this->donor()->id,
                'items' => [
                    ['name' => 'Rice', 'category' => 'food', 'subcategory' => 'rice_grains', 'quantity' => null, 'unit' => 'sack', 'condition' => 'new'],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.quantity');
        $response->assertSessionHasInput('items.0.category', 'food');
        $response->assertSessionHasInput('items.0.subcategory', 'rice_grains');

        $this->followingRedirects()
            ->actingAs(User::factory()->create())
            ->get(route('donations.index'))
            ->assertSee('data-selected-subcategory="rice_grains"', false);
    }

    public function test_categories_without_subcategories_do_not_require_subcategory(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $this->donor()->id,
            'items' => [
                ['name' => 'Notebook', 'category' => 'school_supplies', 'quantity' => 5, 'unit' => 'piece', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Notebook',
            'category' => 'school_supplies',
            'subcategory' => null,
        ]);
    }

    public function test_admin_can_create_custom_in_kind_category_and_subcategory(): void
    {
        $admin = User::factory()->create();
        $admin->update(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);

        $response = $this->actingAs($admin)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $this->donor()->id,
            'items' => [[
                'name' => 'Portable Stove',
                'category' => '__other__',
                'custom_category' => 'Outdoor Cooking',
                'subcategory' => '__other__',
                'custom_subcategory' => 'Portable Stoves',
                'quantity' => 1,
                'unit' => 'piece',
                'condition' => 'new',
            ]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Portable Stove',
            'category' => 'outdoor_cooking',
            'subcategory' => 'portable_stoves',
        ]);
    }

    public function test_existing_records_without_subcategory_remain_valid_on_detail_page(): void
    {
        $user = User::factory()->create();
        $contribution = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => config('donations.default_status.in_kind'),
            'received_at' => now(),
        ]);

        $contribution->items()->create([
            'name' => 'Legacy Rice',
            'category' => 'food',
            'quantity' => 1,
            'unit' => 'sack',
            'condition' => 'good',
        ]);

        $this->actingAs($user)
            ->get(route('donations.show', $contribution))
            ->assertOk()
            ->assertSee('Legacy Rice')
            ->assertSee('Food');
    }


    public function test_contribution_receipt_numbers_are_generated_from_persisted_ids(): void
    {
        Carbon::setTestNow('2026-09-05 10:15:00');

        $donor = $this->donor();
        $user = User::factory()->create();

        try {
            $this->actingAs($user)->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $donor->id,
                'amount' => 100,
                'payment_method' => 'cash',
            ])->assertSessionDoesntHaveErrors();

            $this->actingAs($user)->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $donor->id,
                'amount' => 200,
                'payment_method' => 'cash',
            ])->assertSessionDoesntHaveErrors();
        } finally {
            Carbon::setTestNow();
        }

        $contributions = Contribution::query()->orderBy('id')->get();

        $this->assertCount(2, $contributions);
        $this->assertSame(
            sprintf('DON-20260905-%06d', $contributions[0]->id),
            $contributions[0]->receipt_number
        );
        $this->assertSame(
            sprintf('DON-20260905-%06d', $contributions[1]->id),
            $contributions[1]->receipt_number
        );
        $this->assertNotSame($contributions[0]->receipt_number, $contributions[1]->receipt_number);
    }

    public function test_electronic_payment_requires_external_reference_number(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $this->donor()->id,
            'amount' => 500,
            'payment_method' => 'gcash',
        ]);

        $response->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_external_reference_number_remains_unique_per_payment_method(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-1001',
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 600,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-1001',
        ]);

        $response->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_admin_can_verify_pending_donation_for_reconciliation(): void
    {
        $admin = User::factory()->create();
        $admin->update(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-VERIFY-001',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('donations.verify', $donation))
            ->assertRedirect();

        $donation->refresh();
        $this->assertSame('verified', $donation->status);
        $this->assertSame($admin->id, $donation->verified_by);
        $this->assertNotNull($donation->verified_at);
        $this->assertTrue(Contribution::query()->whereKey($donation)->eligibleForReconciliation()->exists());
    }

    public function test_staff_cannot_verify_or_reject_a_pending_donation(): void
    {
        $staff = User::factory()->create();
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => $staff->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-STAFF-001',
            'received_at' => now(),
        ]);

        $this->actingAs($staff)
            ->patch(route('donations.verify', $donation))
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertForbidden();

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'pending']);
    }

    public function test_admin_can_reject_pending_donation_and_it_is_not_reconcilable(): void
    {
        $admin = User::factory()->create();
        $admin->update(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-REJECT-001',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertRedirect(route('donations.show', $donation));

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'rejected']);
        $this->assertFalse(Contribution::query()->whereKey($donation)->eligibleForReconciliation()->exists());
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
