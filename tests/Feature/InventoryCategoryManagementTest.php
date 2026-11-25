<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySubcategory;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_categories_and_units_are_seeded_from_config(): void
    {
        $this->assertDatabaseHas('inventory_categories', ['key' => 'food', 'label' => 'Food', 'is_active' => true]);
        $this->assertDatabaseHas('inventory_units', ['key' => 'piece', 'label' => 'Piece', 'is_active' => true]);
        $this->assertSame(count(config('inventory.categories')), DB::table('inventory_categories')->count());
        $this->assertSame(count(config('inventory.units')), DB::table('inventory_units')->count());
    }

    public function test_inventory_category_options_only_include_active_categories(): void
    {
        InventoryCategory::query()->create(['key' => 'test_active', 'label' => 'Test Active', 'is_active' => true]);
        InventoryCategory::query()->create(['key' => 'test_inactive', 'label' => 'Test Inactive', 'is_active' => false]);

        $options = InventoryCategory::options();

        $this->assertArrayHasKey('test_active', $options);
        $this->assertArrayNotHasKey('test_inactive', $options);
    }

    public function test_inventory_unit_options_only_include_active_units(): void
    {
        InventoryUnit::query()->create(['key' => 'test_unit', 'label' => 'Test Unit', 'is_active' => true]);
        InventoryUnit::query()->create(['key' => 'test_unit_inactive', 'label' => 'Test Unit Inactive', 'is_active' => false]);

        $options = InventoryUnit::options();

        $this->assertArrayHasKey('test_unit', $options);
        $this->assertArrayNotHasKey('test_unit_inactive', $options);
    }

    public function test_inventory_category_policy_restricts_mutations_to_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $secretary = $this->userWithRole('staff');
        $category = InventoryCategory::query()->first();

        $this->assertTrue($admin->can('create', InventoryCategory::class));
        $this->assertFalse($secretary->can('create', InventoryCategory::class));
        $this->assertTrue($admin->can('update', $category));
        $this->assertFalse($secretary->can('update', $category));
        $this->assertTrue($admin->can('delete', $category));
        $this->assertFalse($secretary->can('delete', $category));
        $this->assertTrue($secretary->can('viewAny', InventoryCategory::class));
    }

    public function test_inventory_unit_policy_restricts_mutations_to_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $secretary = $this->userWithRole('staff');

        $this->assertTrue($admin->can('create', InventoryUnit::class));
        $this->assertFalse($secretary->can('create', InventoryUnit::class));
        $this->assertTrue($secretary->can('viewAny', InventoryUnit::class));
    }

    public function test_manage_inventory_reference_item_service_creates_updates_and_deletes(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);

        $category = $manager->create(InventoryCategory::class, ['key' => 'service_test', 'label' => 'Service Test', 'is_active' => true]);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test']);

        $manager->update($category, ['key' => 'service_test', 'label' => 'Service Test Updated']);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test Updated', 'is_active' => true]);

        $manager->delete($category);
        $this->assertDatabaseMissing('inventory_categories', ['key' => 'service_test']);
    }

    public function test_manage_inventory_reference_item_service_can_deactivate_and_reactivate_a_category(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $manager->deactivate($category);
        $this->assertFalse($category->fresh()->is_active);

        $manager->reactivate($category);
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_updating_key_and_label_does_not_change_is_active(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);
        $category = InventoryCategory::query()->create(['key' => 'toggle_test', 'label' => 'Toggle Test', 'is_active' => false]);

        $manager->update($category, ['key' => 'toggle_test', 'label' => 'Toggle Test Renamed']);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_manage_inventory_reference_item_service_blocks_delete_when_category_has_a_subcategory(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);
        $category = InventoryCategory::query()->create(['key' => 'has_subcat', 'label' => 'Has Subcat']);
        InventorySubcategory::query()->create([
            'inventory_category_id' => $category->id, 'key' => 'unused_child', 'label' => 'Unused Child', 'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $manager->delete($category);
    }

    public function test_manage_inventory_reference_item_service_blocks_delete_when_in_use(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        InventoryItem::query()->create([
            'name' => 'In Use Item',
            'category' => 'food',
            'unit' => 'piece',
            'quantity' => 1,
            'current_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $manager->delete($category);
    }

    public function test_used_category_and_unit_keys_cannot_be_renamed(): void
    {
        $item = $this->referenceItem();
        foreach ([InventoryCategory::class => 'food', InventoryUnit::class => 'piece'] as $model => $key) {
            $reference = $model::query()->where('key', $key)->firstOrFail();
            try {
                app(ManageInventoryReferenceItem::class)->update($reference, [
                    'key' => 'renamed_'.$key, 'label' => 'Renamed', 'is_active' => true,
                ]);
                $this->fail('Renaming an in-use reference must be rejected.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('key', $error->errors());
            }
            $this->assertSame($key, $reference->fresh()->key);
        }
        $this->assertSame('food', $item->fresh()->category);
        $this->assertSame('piece', $item->fresh()->unit);
    }

    public function test_reference_used_in_another_workspace_cannot_be_deleted(): void
    {
        $workspace = Workspace::query()->create([
            'slug' => 'reference-other', 'name' => 'Other Workspace', 'status' => 'active',
        ]);
        $this->referenceItem(['workspace_id' => $workspace->id]);
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(ManageInventoryReferenceItem::class)->delete($category);
    }

    public function test_reference_used_in_another_workspace_cannot_be_renamed(): void
    {
        $workspace = Workspace::query()->create([
            'slug' => 'rename-other', 'name' => 'Other Workspace', 'status' => 'active',
        ]);
        $this->referenceItem(['workspace_id' => $workspace->id]);
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(ManageInventoryReferenceItem::class)->update($category, [
            'key' => 'renamed_food', 'label' => 'Renamed', 'is_active' => true,
        ]);
    }

    public function test_unused_reference_can_be_renamed(): void
    {
        $category = InventoryCategory::query()->create(['key' => 'unused', 'label' => 'Unused']);
        app(ManageInventoryReferenceItem::class)->update($category, [
            'key' => 'unused_renamed', 'label' => 'Renamed', 'is_active' => true,
        ]);
        $this->assertSame('unused_renamed', $category->fresh()->key);
    }

    private function referenceItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'name' => 'Reference regression', 'category' => 'food', 'unit' => 'piece',
            'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ], $overrides));
    }

    public function test_guest_is_redirected_from_categories_index(): void
    {
        $this->get(route('inventory.categories.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_manage_categories(): void
    {
        $secretary = $this->userWithRole('staff');

        $this->actingAs($secretary)->post(route('inventory.categories.store'), ['key' => 'blocked', 'label' => 'Blocked'])->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_a_category(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('inventory.categories.store'), [
            'key' => 'new_category',
            'label' => 'New Category',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_categories', ['key' => 'new_category', 'label' => 'New Category']);

        $category = InventoryCategory::query()->where('key', 'new_category')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.categories.update', $category), [
            'key' => 'new_category',
            'label' => 'Renamed Category',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id, 'label' => 'Renamed Category']);

        $this->actingAs($admin)->delete(route('inventory.categories.destroy', $category))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseMissing('inventory_categories', ['id' => $category->id]);
    }

    public function test_admin_can_create_and_delete_a_unit(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('inventory.units.store'), [
            'key' => 'new_unit',
            'label' => 'New Unit',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $unit = InventoryUnit::query()->where('key', 'new_unit')->firstOrFail();

        $this->actingAs($admin)->delete(route('inventory.units.destroy', $unit))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseMissing('inventory_units', ['id' => $unit->id]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_category_over_http(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.categories.deactivate', $category))
            ->assertRedirect(route('inventory.categories.index'));
        $this->assertFalse($category->fresh()->is_active);

        $this->actingAs($admin)->patch(route('inventory.categories.reactivate', $category))
            ->assertRedirect(route('inventory.categories.index'));
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_non_admin_cannot_deactivate_or_reactivate_a_category(): void
    {
        $secretary = $this->userWithRole('staff');
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->actingAs($secretary)->patch(route('inventory.categories.deactivate', $category))->assertForbidden();
        $this->actingAs($secretary)->patch(route('inventory.categories.reactivate', $category))->assertForbidden();
    }

    public function test_admin_can_deactivate_and_reactivate_a_unit_over_http(): void
    {
        $admin = $this->userWithRole('admin');
        $unit = InventoryUnit::query()->where('key', 'piece')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.units.deactivate', $unit))->assertRedirect(route('inventory.categories.index'));
        $this->assertFalse($unit->fresh()->is_active);

        $this->actingAs($admin)->patch(route('inventory.units.reactivate', $unit))->assertRedirect(route('inventory.categories.index'));
        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_non_admin_cannot_deactivate_or_reactivate_a_unit(): void
    {
        $secretary = $this->userWithRole('staff');
        $unit = InventoryUnit::query()->where('key', 'piece')->firstOrFail();

        $this->actingAs($secretary)->patch(route('inventory.units.deactivate', $unit))->assertForbidden();
        $this->actingAs($secretary)->patch(route('inventory.units.reactivate', $unit))->assertForbidden();
    }

    public function test_updating_a_category_over_http_no_longer_accepts_is_active(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->patch(route('inventory.categories.update', $category), [
            'key' => 'food', 'label' => 'Food', 'is_active' => '0',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_updating_a_unit_over_http_no_longer_accepts_is_active(): void
    {
        $admin = $this->userWithRole('admin');
        $unit = InventoryUnit::query()->where('key', 'piece')->firstOrFail();
        $this->assertTrue($unit->is_active);

        $this->actingAs($admin)->patch(route('inventory.units.update', $unit), [
            'key' => 'piece', 'label' => 'Piece', 'is_active' => '0',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_manage_categories_page_lists_seeded_categories_and_units(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee('Manage Item Categories');
        $response->assertSee('Food');
        $response->assertSee('Piece');
    }

    public function test_manage_categories_page_computes_category_and_unit_dependency_counts(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        InventoryItem::query()->create([
            'name' => 'Count Fixture', 'category' => 'food', 'unit' => 'piece',
            'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
        $response->assertOk();

        $categories = $response->viewData('categories');
        $units = $response->viewData('units');
        $freshFood = $categories->firstWhere('key', 'food');
        $piece = $units->firstWhere('key', 'piece');

        $this->assertSame(1, $freshFood->inventory_item_count);
        $this->assertSame($food->subcategories()->count(), $freshFood->subcategory_count);
        $this->assertSame(1, $piece->inventory_item_count);
    }

    public function test_manage_categories_page_computes_subcategory_dependency_counts(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();

        InventoryItem::query()->create([
            'name' => 'Subcat FK Fixture', 'category' => 'food', 'inventory_subcategory_id' => $subcategory->id, 'unit' => 'piece',
            'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
        ]);
        $this->createContributionItem($admin, 'food', 'rice_grains');

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
        $response->assertOk();

        $categories = $response->viewData('categories');
        $freshSubcategory = $categories->firstWhere('key', 'food')->subcategories->firstWhere('key', 'rice_grains');

        $this->assertSame(1, $freshSubcategory->inventory_item_count);
        $this->assertSame(1, $freshSubcategory->donation_item_count);
    }

    public function test_category_dependency_count_matches_the_cross_workspace_delete_guard(): void
    {
        $admin = $this->userWithRole('admin');
        $workspace = Workspace::query()->create(['slug' => 'count-other', 'name' => 'Other Workspace', 'status' => 'active']);
        $this->referenceItem(['workspace_id' => $workspace->id]); // category 'food', unit 'piece', in a different workspace

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
        $response->assertOk();

        $food = $response->viewData('categories')->firstWhere('key', 'food');
        $piece = $response->viewData('units')->firstWhere('key', 'piece');

        $this->assertGreaterThan(0, $food->inventory_item_count);
        $this->assertGreaterThan(0, $piece->inventory_item_count);
    }

    public function test_in_use_active_category_shows_deactivate_not_delete(): void
    {
        $admin = $this->userWithRole('admin');
        $this->referenceItem(); // uses category 'food', unit 'piece'
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee("data-dialog-open=\"category-deactivate-{$food->id}\"", false);
        $response->assertDontSee("data-dialog-open=\"category-delete-{$food->id}\"", false);
    }

    public function test_unused_inactive_category_shows_reactivate_and_delete(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->create(['key' => 'retired_unused', 'label' => 'Retired Unused', 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee("data-dialog-open=\"category-reactivate-{$category->id}\"", false);
        $response->assertSee("data-dialog-open=\"category-delete-{$category->id}\"", false);
    }

    public function test_edit_modals_no_longer_have_an_active_checkbox(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
        $response->assertOk();

        // Exactly 3 "create" dialogs (category/unit/subcategory) should still carry the
        // is_active control — each renders 2 inputs named is_active (hidden "0" + checkbox
        // "1", see reference-form-modal.blade.php). No "edit" dialog should contribute any,
        // regardless of how many categories/units/subcategories exist, so the total must
        // stay exactly 6 — not "zero", which would wrongly break the Create forms too.
        $this->assertSame(6, substr_count($response->getContent(), 'name="is_active"'));
    }

    public function test_non_admin_cannot_view_categories_index(): void
    {
        $secretary = $this->userWithRole('staff');

        $this->actingAs($secretary)->get(route('inventory.categories.index'))->assertForbidden();
    }

    public function test_manage_categories_button_hidden_from_non_admin_on_inventory_index(): void
    {
        $secretary = $this->userWithRole('staff');

        $response = $this->actingAs($secretary)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertDontSee('Manage Categories');
    }

    public function test_manage_categories_button_visible_to_admin_on_inventory_index(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertSee('Manage Categories');
    }

    public function test_deleting_a_category_in_use_is_blocked_over_http(): void
    {
        $admin = $this->userWithRole('admin');

        InventoryItem::query()->create([
            'name' => 'Blocks Delete',
            'category' => 'food',
            'unit' => 'piece',
            'quantity' => 1,
            'current_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);

        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->actingAs($admin)->delete(route('inventory.categories.destroy', $category))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id]);
    }

    public function test_newly_created_category_is_immediately_valid_for_inventory_item_creation(): void
    {
        $admin = $this->userWithRole('admin');
        InventoryCategory::query()->create(['key' => 'emergency_kits', 'label' => 'Emergency Kits', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Emergency Kit Bundle',
            'category' => 'emergency_kits',
            'unit' => 'piece',
            'initial_quantity' => 5,
            'minimum_reserve_quantity' => 1,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('inventory_items', ['name' => 'Emergency Kit Bundle', 'category' => 'emergency_kits']);
    }

    public function test_inventory_create_page_exposes_subcategories_grouped_by_category(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get(route('inventory.create'));

        $response->assertOk()
            ->assertSee('data-inventory-item-form', false)
            ->assertSee('Cookware')
            ->assertSee('Utensils')
            ->assertSee('Dinnerware');
    }

    public function test_inventory_item_rejects_a_subcategory_from_another_category(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $subcategory = InventorySubcategory::query()
            ->whereHas('category', fn ($query) => $query->where('key', 'kitchen_supplies'))
            ->firstOrFail();

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Invalid Subcategory Item',
            'category' => $food->key,
            'subcategory_selection' => $subcategory->key,
            'unit' => 'piece',
            'initial_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('subcategory_selection');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Invalid Subcategory Item']);
    }

    public function test_inventory_item_rejects_an_inactive_subcategory(): void
    {
        $admin = $this->userWithRole('admin');
        $subcategory = InventorySubcategory::query()->where('key', 'cookware')->firstOrFail();
        $subcategory->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Inactive Subcategory Item',
            'category' => 'kitchen_supplies',
            'subcategory_selection' => $subcategory->key,
            'unit' => 'piece',
            'initial_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('subcategory_selection');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Inactive Subcategory Item']);
    }

    public function test_inventory_item_can_be_created_without_a_subcategory(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->post(route('inventory.store'), [
            'name' => 'Uncategorized School Item',
            'category' => 'school_supplies',
            'unit' => 'piece',
            'initial_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Uncategorized School Item',
            'inventory_subcategory_id' => null,
        ]);
    }

    public function test_admin_can_create_and_reuse_custom_category_and_subcategory(): void
    {
        $admin = $this->userWithRole('admin');
        $payload = [
            'name' => 'Custom Kitchen Item',
            'category' => '__other__',
            'custom_category' => '  Outdoor Cooking  ',
            'subcategory_selection' => '__other__',
            'custom_subcategory' => '  Portable Stoves  ',
            'unit' => 'piece',
            'initial_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ];

        $this->actingAs($admin)->post(route('inventory.store'), $payload)->assertRedirect(route('inventory.index'));
        $category = InventoryCategory::query()->where('label', 'Outdoor Cooking')->firstOrFail();
        $subcategory = InventorySubcategory::query()->where('label', 'Portable Stoves')->firstOrFail();

        $this->actingAs($admin)->post(route('inventory.store'), [...$payload, 'name' => 'Second Custom Kitchen Item'])
            ->assertRedirect(route('inventory.index'));

        $this->assertSame(1, InventoryCategory::query()->whereRaw('LOWER(label) = ?', ['outdoor cooking'])->count());
        $this->assertSame(1, InventorySubcategory::query()->where('inventory_category_id', $category->id)->whereRaw('LOWER(label) = ?', ['portable stoves'])->count());
        $this->assertDatabaseHas('inventory_items', ['name' => 'Custom Kitchen Item', 'category' => $category->key, 'inventory_subcategory_id' => $subcategory->id]);
    }

    public function test_non_admin_cannot_create_custom_inventory_classifications(): void
    {
        $response = $this->actingAs($this->userWithRole('staff'))->post(route('inventory.store'), [
            'name' => 'Unauthorized Custom Item',
            'category' => '__other__',
            'custom_category' => 'Restricted Category',
            'subcategory_selection' => '__other__',
            'custom_subcategory' => 'Restricted Subcategory',
            'unit' => 'piece',
            'initial_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('custom_category');
        $this->assertDatabaseMissing('inventory_categories', ['label' => 'Restricted Category']);
    }

    public function test_existing_inventory_item_without_subcategory_remains_visible(): void
    {
        $item = InventoryItem::query()->create([
            'name' => 'Legacy Inventory Item',
            'category' => 'food',
            'unit' => 'piece',
            'quantity' => 1,
            'current_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('inventory.show', $item))
            ->assertOk()
            ->assertSee('Food')
            ->assertDontSee('Food ·');
    }

    public function test_deactivated_category_is_rejected_for_inventory_item_creation(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->where('key', 'other')->firstOrFail();
        $category->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Should Fail',
            'category' => 'other',
            'unit' => 'piece',
            'initial_quantity' => 5,
            'minimum_reserve_quantity' => 1,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_inventory_subcategories_are_seeded_from_config(): void
    {
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->assertDatabaseHas('inventory_subcategories', [
            'inventory_category_id' => $food->id,
            'key' => 'rice_grains',
            'label' => 'Rice & Grains',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_update_and_delete_a_subcategory(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->where('key', 'other')->firstOrFail();

        $this->actingAs($admin)->post(route('inventory.subcategories.store'), [
            'inventory_category_id' => $category->id,
            'key' => 'custom_subcategory',
            'label' => 'Custom Subcategory',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $subcategory = InventorySubcategory::query()->where('key', 'custom_subcategory')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory), [
            'inventory_category_id' => $category->id,
            'key' => 'custom_subcategory',
            'label' => 'Renamed Subcategory',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->actingAs($admin)->patch(route('inventory.subcategories.deactivate', $subcategory))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_subcategories', [
            'id' => $subcategory->id,
            'label' => 'Renamed Subcategory',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->delete(route('inventory.subcategories.destroy', $subcategory))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseMissing('inventory_subcategories', ['id' => $subcategory->id]);
    }

    public function test_referenced_subcategory_can_be_deactivated_but_not_renamed(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $subcategory = InventorySubcategory::query()
            ->where('inventory_category_id', $food->id)
            ->where('key', 'rice_grains')
            ->firstOrFail();

        $this->createContributionItem($admin, 'food', 'rice_grains');

        $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory), [
            'inventory_category_id' => $food->id,
            'key' => 'rice_grains',
            'label' => 'Rice and Grains',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->actingAs($admin)->patch(route('inventory.subcategories.deactivate', $subcategory))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_subcategories', [
            'id' => $subcategory->id,
            'key' => 'rice_grains',
            'label' => 'Rice and Grains',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory->fresh()), [
            'inventory_category_id' => $food->id,
            'key' => 'renamed_rice',
            'label' => 'Renamed Rice',
        ])->assertSessionHasErrors('key');

        $this->assertDatabaseMissing('inventory_subcategories', [
            'id' => $subcategory->id,
            'key' => 'renamed_rice',
        ]);
    }

    public function test_referenced_subcategory_cannot_be_moved_or_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $kitchen = InventoryCategory::query()->where('key', 'kitchen_supplies')->firstOrFail();
        $subcategory = InventorySubcategory::query()
            ->where('inventory_category_id', $food->id)
            ->where('key', 'rice_grains')
            ->firstOrFail();

        $this->createContributionItem($admin, 'food', 'rice_grains');

        $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory), [
            'inventory_category_id' => $kitchen->id,
            'key' => 'rice_grains',
            'label' => 'Rice & Grains',
            'is_active' => '1',
        ])->assertSessionHasErrors('key');

        $this->actingAs($admin)->delete(route('inventory.subcategories.destroy', $subcategory))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseHas('inventory_subcategories', [
            'id' => $subcategory->id,
            'inventory_category_id' => $food->id,
            'key' => 'rice_grains',
        ]);
    }

    public function test_subcategory_referenced_only_by_an_inventory_item_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();

        InventoryItem::query()->create([
            'name' => 'FK-Linked Item', 'category' => 'food', 'inventory_subcategory_id' => $subcategory->id, 'unit' => 'piece',
            'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('inventory.subcategories.destroy', $subcategory))->assertSessionHasErrors('key');
        $this->assertDatabaseHas('inventory_subcategories', ['id' => $subcategory->id]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_subcategory_over_http(): void
    {
        $admin = $this->userWithRole('admin');
        $subcategory = InventorySubcategory::query()->where('key', 'rice_grains')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.subcategories.deactivate', $subcategory))->assertRedirect(route('inventory.categories.index'));
        $this->assertFalse($subcategory->fresh()->is_active);

        $this->actingAs($admin)->patch(route('inventory.subcategories.reactivate', $subcategory))->assertRedirect(route('inventory.categories.index'));
        $this->assertTrue($subcategory->fresh()->is_active);
    }

    public function test_non_admin_cannot_deactivate_or_reactivate_a_subcategory(): void
    {
        $secretary = $this->userWithRole('staff');
        $subcategory = InventorySubcategory::query()->where('key', 'rice_grains')->firstOrFail();

        $this->actingAs($secretary)->patch(route('inventory.subcategories.deactivate', $subcategory))->assertForbidden();
        $this->actingAs($secretary)->patch(route('inventory.subcategories.reactivate', $subcategory))->assertForbidden();
    }

    public function test_updating_a_subcategory_over_http_no_longer_accepts_is_active(): void
    {
        $admin = $this->userWithRole('admin');
        $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();
        $this->assertTrue($subcategory->is_active);

        $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory), [
            'inventory_category_id' => $food->id, 'key' => 'rice_grains', 'label' => 'Rice & Grains', 'is_active' => '0',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertTrue($subcategory->fresh()->is_active);
    }

    private function createContributionItem(User $user, string $category, string $subcategory): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Reference Donor',
            'address' => 'Main Street',
            'contact_number' => '09170000000',
            'email' => 'reference@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => config('donations.default_status.in_kind'),
            'received_at' => now(),
        ]);

        $contribution->items()->create([
            'name' => 'Referenced Item',
            'category' => $category,
            'subcategory' => $subcategory,
            'quantity' => 1,
            'unit' => 'piece',
            'condition' => 'new',
        ]);
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
