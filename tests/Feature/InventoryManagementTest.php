<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_inventory_index(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_inventory_index(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('inventory.index'));

        $response->assertOk();
        $response->assertSee('In-Kind Inventory');
        $response->assertSee('Inventory Alerts');
    }

    public function test_inventory_search_filters_items(): void
    {
        $matching = $this->inventoryItem(['name' => 'Rice Packs', 'storage_location' => 'Main Hall']);
        $other = $this->inventoryItem(['name' => 'Blankets', 'storage_location' => 'Clinic']);

        $response = $this->actingAs(User::factory()->create())->get(route('inventory.index', [
            'search' => 'Rice',
        ]));

        $response->assertOk();
        $response->assertSee($matching->name);
        $response->assertDontSee($other->name);
    }

    public function test_category_filter_limits_inventory_items(): void
    {
        $food = $this->inventoryItem(['name' => 'Canned Goods', 'category' => 'food']);
        $medical = $this->inventoryItem(['name' => 'Bandages', 'category' => 'medical_supplies']);

        $response = $this->actingAs(User::factory()->create())->get(route('inventory.index', [
            'category' => 'food',
        ]));

        $response->assertOk();
        $response->assertSee($food->name);
        $response->assertDontSee($medical->name);
    }

    public function test_inventory_item_creation_records_initial_stock_movement(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inventory.store'), [
            'name' => 'Hygiene Kits',
            'category' => 'hygiene_supplies',
            'description' => 'Packed family hygiene kits',
            'unit' => 'pack',
            'initial_quantity' => 25,
            'minimum_reserve_quantity' => 10,
            'estimated_unit_value' => 150,
            'expires_at' => now()->addYear()->toDateString(),
            'batch_number' => 'HK-001',
            'storage_location' => 'Supply Room',
            'notes' => 'For evacuation center use.',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Hygiene Kits',
            'category' => 'hygiene_supplies',
            'current_quantity' => 25,
            'minimum_reserve_quantity' => 10,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'movement_type' => 'stock_in',
            'quantity' => 25,
            'quantity_before' => 0,
            'quantity_after' => 25,
            'reason' => 'new_donation',
        ]);
    }

    public function test_stock_out_cannot_reduce_inventory_below_zero(): void
    {
        $item = $this->inventoryItem(['current_quantity' => 5, 'quantity' => 5]);

        $response = $this->actingAs(User::factory()->create())->post(route('inventory.adjustments.store', $item), [
            'movement_type' => 'stock_out',
            'quantity' => 6,
            'reason' => 'distribution',
            'reference_number' => null,
            'remarks' => 'Attempted distribution.',
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'current_quantity' => 5,
        ]);
        $this->assertSame(0, InventoryStockMovement::query()->count());
    }

    public function test_stock_adjustment_updates_quantity_and_records_movement(): void
    {
        $user = User::factory()->create();
        $item = $this->inventoryItem(['current_quantity' => 10, 'quantity' => 10]);

        $response = $this->actingAs($user)->post(route('inventory.adjustments.store', $item), [
            'movement_type' => 'stock_in',
            'quantity' => 4,
            'reason' => 'correction',
            'reference_number' => 'ADJ-100',
            'remarks' => 'Count correction.',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'current_quantity' => 14,
            'updated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity_before' => 10,
            'quantity_after' => 14,
            'reference_number' => 'ADJ-100',
        ]);
    }

    public function test_stock_adjustment_is_recorded_in_the_security_audit_log(): void
    {
        $user = User::factory()->create();
        $item = $this->inventoryItem(['current_quantity' => 10, 'quantity' => 10]);

        $this->actingAs($user)->post(route('inventory.adjustments.store', $item), [
            'movement_type' => 'stock_in',
            'quantity' => 4,
            'reason' => 'correction',
            'reference_number' => 'ADJ-AUDIT',
            'remarks' => 'Count correction.',
        ]);

        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'inventory_stock_stock_in',
            'actor_id' => $user->id,
        ]);
    }

    public function test_low_reserve_alert_and_status_render_from_dynamic_stock(): void
    {
        $item = $this->inventoryItem([
            'name' => 'Rice Sacks',
            'current_quantity' => 2,
            'quantity' => 2,
            'minimum_reserve_quantity' => 10,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('inventory.index'));

        $response->assertOk();
        $response->assertSee($item->name);
        $response->assertSee('Critical Stock');
    }

    public function test_authorized_user_can_export_inventory_summary_pdf(): void
    {
        $this->inventoryItem(['name' => 'Rice Packs', 'category' => 'food']);

        $response = $this->actingAs(User::factory()->create())->get(route('inventory.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_inventory_pdf_export_respects_search_filter(): void
    {
        $this->inventoryItem(['name' => 'Rice Packs']);
        $this->inventoryItem(['name' => 'Blankets']);

        $response = $this->actingAs(User::factory()->create())->get(route('inventory.export.pdf', ['search' => 'Rice']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_guest_cannot_export_inventory_summary_pdf(): void
    {
        $this->get(route('inventory.export.pdf'))->assertRedirect(route('login'));
    }

    public function test_staff_can_make_stock_adjustments_within_their_workspace(): void
    {
        $staffRole = Role::query()->firstOrCreate(
            ['name' => 'staff'],
            config('auth_defaults.roles.staff')
        );
        $staff = User::factory()->create(['role_id' => $staffRole->id]);
        $item = $this->inventoryItem();

        $response = $this->actingAs($staff)->post(route('inventory.adjustments.store', $item), [
            'movement_type' => 'stock_in',
            'quantity' => 1,
            'reason' => 'correction',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
        ]);
    }

    private function inventoryItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'name' => 'Inventory Item',
            'category' => 'food',
            'unit' => 'pack',
            'quantity' => 20,
            'current_quantity' => 20,
            'minimum_reserve_quantity' => 5,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ], $overrides));
    }
}
