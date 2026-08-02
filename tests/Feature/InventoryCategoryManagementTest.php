<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
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
        $secretary = $this->userWithRole('secretary');
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
        $secretary = $this->userWithRole('secretary');

        $this->assertTrue($admin->can('create', InventoryUnit::class));
        $this->assertFalse($secretary->can('create', InventoryUnit::class));
        $this->assertTrue($secretary->can('viewAny', InventoryUnit::class));
    }

    public function test_manage_inventory_reference_item_service_creates_updates_and_deletes(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);

        $category = $manager->create(InventoryCategory::class, ['key' => 'service_test', 'label' => 'Service Test', 'is_active' => true]);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test']);

        $manager->update($category, ['key' => 'service_test', 'label' => 'Service Test Updated', 'is_active' => false]);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test Updated', 'is_active' => false]);

        $manager->delete($category);
        $this->assertDatabaseMissing('inventory_categories', ['key' => 'service_test']);
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

    public function test_guest_is_redirected_from_categories_index(): void
    {
        $this->get(route('inventory.categories.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_manage_categories(): void
    {
        $secretary = $this->userWithRole('secretary');

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

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
