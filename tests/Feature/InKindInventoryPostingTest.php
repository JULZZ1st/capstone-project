<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\Contributions\LogContribution;
use App\Services\Inventory\PostContributionToInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InKindInventoryPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_in_kind_contribution_posts_new_inventory_and_source_history(): void
    {
        $user = User::factory()->create();
        $donor = $this->donor('New Inventory Donor');

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [[
                'name' => 'Rice',
                'category' => 'food_relief',
                'quantity' => 10,
                'unit' => 'sack',
                'condition' => 'new',
            ]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $contribution = Contribution::query()->latest('id')->firstOrFail();
        $item = ContributionItem::query()->where('contribution_id', $contribution->id)->firstOrFail();
        $inventoryItem = InventoryItem::query()->whereKey($item->inventory_item_id)->firstOrFail();
        $movement = InventoryStockMovement::query()->where('contribution_item_id', $item->id)->firstOrFail();

        $this->assertSame('received', $contribution->status);
        $this->assertSame('food', $inventoryItem->category);
        $this->assertSame('sack', $inventoryItem->unit);
        $this->assertSame(10.0, (float) $inventoryItem->current_quantity);
        $this->assertSame(10.0, (float) $movement->quantity);
        $this->assertSame(0.0, (float) $movement->quantity_before);
        $this->assertSame(10.0, (float) $movement->quantity_after);
        $this->assertSame($contribution->receipt_number, $movement->reference_number);

        $this->actingAs($user)
            ->get(route('inventory.show', $inventoryItem))
            ->assertOk()
            ->assertSee($contribution->receipt_number);
    }

    public function test_received_is_not_a_user_selectable_status_control(): void
    {
        $user = User::factory()->create();
        $contribution = Contribution::query()->create([
            'donor_id' => $this->donor('Status Control Donor')->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => 'pending',
            'received_at' => now(),
            'receipt_number' => 'DON-STATUS-CONTROL-001',
        ]);

        $this->actingAs($user)
            ->get(route('donations.index'))
            ->assertOk()
            ->assertDontSee('<option value="received"', false);

        $this->actingAs($user)
            ->get(route('donations.show', $contribution))
            ->assertOk()
            ->assertDontSee('<option value="received"', false);
    }

    public function test_legacy_in_kind_transition_to_received_posts_inventory_once(): void
    {
        $user = User::factory()->create();
        $contribution = Contribution::query()->create([
            'donor_id' => $this->donor('Legacy Transition Donor')->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => 'pending',
            'received_at' => now(),
            'receipt_number' => 'DON-LEGACY-TRANSITION-001',
        ]);
        $item = $contribution->items()->create([
            'name' => 'Legacy Rice',
            'category' => 'food_relief',
            'quantity' => 6,
            'unit' => 'sack',
            'condition' => 'good',
        ]);

        $this->actingAs($user)
            ->patch(route('donations.update', $contribution), ['status' => 'received'])
            ->assertRedirect();

        $this->assertSame('received', $contribution->fresh()->status);
        $this->assertSame(1, InventoryStockMovement::query()->where('contribution_item_id', $item->id)->count());
        $this->assertSame(6.0, (float) InventoryItem::query()->whereKey($item->fresh()->inventory_item_id)->value('current_quantity'));

        $this->actingAs($user)
            ->patch(route('donations.update', $contribution), ['status' => 'received'])
            ->assertRedirect();

        $this->assertSame(1, InventoryStockMovement::query()->where('contribution_item_id', $item->id)->count());
        $this->assertSame(6.0, (float) InventoryItem::query()->whereKey($item->fresh()->inventory_item_id)->value('current_quantity'));
    }

    public function test_posting_reuses_an_active_matching_inventory_item(): void
    {
        $user = User::factory()->create();
        $donor = $this->donor('Existing Inventory Donor');
        $inventoryItem = InventoryItem::query()->create([
            'name' => 'Rice',
            'category' => 'food',
            'unit' => 'sack',
            'quantity' => 5,
            'current_quantity' => 5,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
            'received_at' => now(),
        ]);

        $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [[
                'name' => 'Rice',
                'category' => 'food_relief',
                'quantity' => 3,
                'unit' => 'sack',
                'condition' => 'good',
            ]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, InventoryItem::query()->where('name', 'Rice')->count());
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_quantity);
        $this->assertDatabaseHas('contribution_items', [
            'inventory_item_id' => $inventoryItem->id,
            'name' => 'Rice',
        ]);
    }

    public function test_reposting_a_contribution_item_does_not_duplicate_stock_in(): void
    {
        $user = User::factory()->create();
        $contribution = Contribution::query()->create([
            'donor_id' => $this->donor('Retry Donor')->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => 'received',
            'received_at' => now(),
            'receipt_number' => 'DON-RETRY-001',
        ]);
        $item = $contribution->items()->create([
            'name' => 'Rice',
            'category' => 'food_relief',
            'quantity' => 4,
            'unit' => 'sack',
            'condition' => 'new',
        ]);
        $poster = app(PostContributionToInventory::class);

        $poster->handle($contribution, $user);
        $poster->handle($contribution->fresh(), $user);

        $this->assertSame(1, InventoryStockMovement::query()->where('contribution_item_id', $item->id)->count());
        $this->assertSame(4.0, (float) InventoryItem::query()->where('name', 'Rice')->firstOrFail()->current_quantity);
    }

    public function test_multi_item_posting_rolls_back_contribution_and_inventory_when_any_item_cannot_post(): void
    {
        $user = User::factory()->create();
        $donor = $this->donor('Rollback Donor');
        InventoryCategory::query()->where('key', 'medical_supplies')->update(['is_active' => false]);

        try {
            app(LogContribution::class)->handle([
                'donation_type' => 'in_kind',
                'donor_id' => $donor->id,
                'items' => [
                    ['name' => 'Rice', 'category' => 'food_relief', 'quantity' => 5, 'unit' => 'sack', 'condition' => 'new'],
                    ['name' => 'Bandages', 'category' => 'medical_health', 'quantity' => 2, 'unit' => 'box', 'condition' => 'new'],
                ],
            ], $user);

            $this->fail('Posting should fail when a mapped inventory category is inactive.');
        } catch (ValidationException) {
            $this->assertSame(0, Contribution::query()->count());
            $this->assertSame(0, ContributionItem::query()->count());
            $this->assertSame(0, InventoryItem::query()->count());
            $this->assertSame(0, InventoryStockMovement::query()->count());
        }
    }

    public function test_negative_in_kind_quantity_is_rejected_before_any_posting(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $this->donor('Negative Quantity Donor')->id,
            'items' => [[
                'name' => 'Rice',
                'category' => 'food_relief',
                'quantity' => -1,
                'unit' => 'sack',
                'condition' => 'new',
            ]],
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertSame(0, Contribution::query()->count());
        $this->assertSame(0, InventoryStockMovement::query()->count());
    }

    public function test_donation_filters_cover_date_donor_amount_item_and_category(): void
    {
        $user = User::factory()->create();
        $matchingDonor = $this->donor('Filter Match Donor');
        $otherDonor = $this->donor('Filter Other Donor');
        $matching = Contribution::query()->create([
            'donor_id' => $matchingDonor->id,
            'created_by' => $user->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 750,
            'received_at' => Carbon::parse('2026-11-05'),
        ]);
        $matching->forceFill(['receipt_number' => 'DON-FILTER-MATCH'])->save();
        $other = Contribution::query()->create([
            'donor_id' => $otherDonor->id,
            'created_by' => $user->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 2500,
            'received_at' => Carbon::parse('2026-10-01'),
        ]);
        $other->forceFill(['receipt_number' => 'DON-FILTER-OTHER'])->save();
        $matching->items()->create([
            'name' => 'Rice Packs',
            'category' => 'food_relief',
            'quantity' => 2,
            'unit' => 'pack',
            'condition' => 'new',
        ]);
        $other->items()->create([
            'name' => 'Blankets',
            'category' => 'furniture_fixtures',
            'quantity' => 2,
            'unit' => 'piece',
            'condition' => 'good',
        ]);

        $this->actingAs($user)->get(route('donations.index', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'donor_id' => $matchingDonor->id,
            'amount_min' => 500,
            'amount_max' => 1000,
            'item' => 'Rice',
            'category' => 'food_relief',
        ]))->assertOk()
            ->assertSee('DON-FILTER-MATCH')
            ->assertDontSee('DON-FILTER-OTHER');
    }

    private function donor(string $name): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $name,
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'status' => config('donors.defaults.status'),
        ]);
    }
}
