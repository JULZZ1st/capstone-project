<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySubcategory;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Inventory\AdjustInventoryStock;
use App\Services\Inventory\ResolveInventoryClassification;
use App\Services\Inventory\UpdateInventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryEstimatedValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimated_value_is_saved_and_visible_on_the_list_details_and_live_refresh(): void
    {
        $this->actingAs(User::factory()->create())->post(route('inventory.store'), $this->payload())
            ->assertSessionDoesntHaveErrors()->assertRedirect(route('inventory.index'));

        $item = InventoryItem::query()->where('name', 'Valued Rice Packs')->firstOrFail();
        $this->assertSame('1250.75', $item->estimated_unit_value);
        $this->get(route('inventory.index'))->assertOk()->assertSee("\u{20B1}1,250.75");
        $this->get(route('inventory.show', $item))->assertOk()->assertSee("\u{20B1}1,250.75")->assertSee(route('inventory.edit', $item), false);
        $live = $this->getJson(route('inventory.index'))->assertOk();
        $this->assertStringContainsString("\u{20B1}1,250.75", $live->json('html'));
        $this->assertStringContainsString('per Pack', $live->json('html'));

        $document = $this->document($live->json('html'));
        $this->assertSame(7, $document->query('//thead/tr/th')->length);
        $this->assertSame(7, $document->query('//tbody/tr[1]/td')->length);
    }

    public function test_existing_item_value_can_be_edited_without_changing_stock_or_creating_movements(): void
    {
        $item = $this->item(['estimated_unit_value' => '1250.75']);
        $this->actingAs(User::factory()->create());

        $edit = $this->get(route('inventory.edit', $item))->assertOk();
        $fields = $this->document($edit->getContent());
        $this->assertSame('1250.75', $fields->evaluate('string(//input[@name="estimated_unit_value"]/@value)'));
        $this->assertSame(0, $fields->query('//input[@name="initial_quantity"]')->length);

        $this->patch(route('inventory.update', $item), $this->payload([
            'estimated_unit_value' => '9876.50', 'current_quantity' => '999',
        ]))->assertSessionDoesntHaveErrors()->assertRedirect(route('inventory.show', $item));

        $this->assertSame('9876.50', $item->fresh()->estimated_unit_value);
        $this->assertSame('20.00', $item->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_stock_movements', 0);
        $this->assertStringContainsString("\u{20B1}9,876.50", $this->getJson(route('inventory.show', $item))->assertOk()->json('html'));
    }

    public function test_unset_values_remain_distinct_from_zero_and_can_be_cleared(): void
    {
        $unset = $this->item(['name' => 'Unknown Value', 'estimated_unit_value' => null]);
        $zero = $this->item(['name' => 'Zero Value', 'estimated_unit_value' => '0.00']);
        $this->actingAs(User::factory()->create());

        $this->get(route('inventory.show', $unset))->assertOk()->assertSee('Not set')->assertDontSee("\u{20B1}0.00");
        $this->get(route('inventory.show', $zero))->assertOk()->assertSee("\u{20B1}0.00");
        $this->get(route('inventory.index'))->assertOk()->assertSee('Not set')->assertSee("\u{20B1}0.00");

        $this->patch(route('inventory.update', $zero), $this->payload(['estimated_unit_value' => '']))
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($zero->fresh()->estimated_unit_value);
    }

    public function test_invalid_amounts_are_rejected_on_create_and_edit_without_changing_the_saved_value(): void
    {
        $item = $this->item(['estimated_unit_value' => '10.25']);
        $this->actingAs(User::factory()->create());

        foreach (['-0.01', '12.345', '100000000', 'invalid'] as $invalid) {
            $payload = $this->payload(['estimated_unit_value' => $invalid]);
            $this->postJson(route('inventory.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('estimated_unit_value');
            $this->patchJson(route('inventory.update', $item), $payload)->assertUnprocessable()->assertJsonValidationErrors('estimated_unit_value');
        }

        $this->assertSame('10.25', $item->fresh()->estimated_unit_value);
        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_item_forms_keep_unique_fields_and_restore_invalid_input(): void
    {
        $item = $this->item();
        $this->actingAs(User::factory()->create());

        foreach ([route('inventory.create'), route('inventory.edit', $item)] as $url) {
            $page = $this->get($url)->assertOk();
            $document = $this->document($page->getContent());
            $this->assertSame(1, $document->query('//input[@name="estimated_unit_value"]')->length);
            $this->assertSame(1, $document->query('//label[@for="estimated_unit_value"]')->length);
            $this->assertSame(0, $document->query('//*[starts-with(name(), "x-")]')->length);
            $ids = [];
            foreach ($document->query('//*[@id]') as $element) {
                $this->assertArrayNotHasKey($element->getAttribute('id'), $ids);
                $ids[$element->getAttribute('id')] = true;
            }
        }

        $this->from(route('inventory.edit', $item))->patch(route('inventory.update', $item), $this->payload(['estimated_unit_value' => '12.345']))
            ->assertRedirect(route('inventory.edit', $item))->assertSessionHasErrors('estimated_unit_value');
        $page = $this->get(route('inventory.edit', $item))->assertOk();
        $document = $this->document($page->getContent());
        $this->assertSame('12.345', $document->evaluate('string(//input[@name="estimated_unit_value"]/@value)'));
        $this->assertSame('estimated-unit-value-help estimated-unit-value-error', $document->evaluate('string(//input[@name="estimated_unit_value"]/@aria-describedby)'));
    }

    public function test_edit_page_requires_login_and_denies_another_workspaces_item(): void
    {
        $item = $this->item();
        $this->get(route('inventory.edit', $item))->assertRedirect(route('login'));

        $other = Workspace::query()->create(['name' => 'Other', 'slug' => 'other-value-workspace', 'status' => 'active']);
        $foreign = $this->item(['workspace_id' => $other->id]);
        $this->actingAs(User::factory()->create())->get(route('inventory.edit', $foreign))->assertNotFound();
        $this->patch(route('inventory.update', $foreign), $this->payload())->assertNotFound();
    }

    public function test_unit_changes_cannot_relabel_existing_stock_or_movement_history(): void
    {
        $item = $this->item(['unit' => 'sack', 'estimated_unit_value' => '2500.00']);
        $this->actingAs(User::factory()->create());
        $this->patchJson(route('inventory.update', $item), $this->payload(['unit' => 'kilogram']))
            ->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->assertSame('sack', $item->fresh()->unit);
        $this->assertSame('2500.00', $item->fresh()->estimated_unit_value);

        $item->update(['quantity' => 0, 'current_quantity' => '0']);
        $item->stockMovements()->create([
            'created_by' => auth()->id(), 'movement_type' => 'stock_out', 'quantity' => '20',
            'quantity_before' => '20', 'quantity_after' => '0', 'reason' => 'distribution',
        ]);
        $this->patchJson(route('inventory.update', $item), $this->payload(['unit' => 'kilogram']))
            ->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->assertSame('sack', $item->fresh()->unit);
    }

    public function test_omitted_optional_values_are_preserved_and_explicit_blank_values_can_be_cleared(): void
    {
        $subcategory = InventorySubcategory::query()->create([
            'inventory_category_id' => InventoryCategory::query()->where('key', 'food')->firstOrFail()->id,
            'key' => 'rice_test', 'label' => 'Rice Test', 'is_active' => true,
        ]);
        $item = $this->item([
            'estimated_unit_value' => '55.25', 'description' => 'Keep description', 'notes' => 'Keep notes',
            'inventory_subcategory_id' => $subcategory->id, 'batch_number' => 'KEEP-1', 'expires_at' => '2027-12-31',
        ]);
        $payload = $this->payload();
        unset($payload['estimated_unit_value']);
        $this->actingAs(User::factory()->create())->patch(route('inventory.update', $item), $payload)->assertSessionDoesntHaveErrors();
        $item->refresh();
        $this->assertSame('55.25', $item->estimated_unit_value);
        $this->assertSame('Keep description', $item->description);
        $this->assertSame('Keep notes', $item->notes);
        $this->assertSame('KEEP-1', $item->batch_number);
        $this->assertSame('2027-12-31', $item->expires_at->toDateString());
        $this->assertSame($subcategory->id, $item->inventory_subcategory_id);

        $this->patch(route('inventory.update', $item), $this->payload([
            'estimated_unit_value' => '', 'notes' => '', 'subcategory_selection' => '',
        ]))->assertSessionDoesntHaveErrors();
        $this->assertNull($item->fresh()->estimated_unit_value);
        $this->assertNull($item->fresh()->notes);
        $this->assertNull($item->fresh()->inventory_subcategory_id);
    }

    public function test_existing_inactive_classification_can_be_retained_while_updating_the_value(): void
    {
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $unit = InventoryUnit::query()->where('key', 'pack')->firstOrFail();
        $subcategory = InventorySubcategory::query()->create([
            'inventory_category_id' => $category->id, 'key' => 'old_rice', 'label' => 'Old Rice', 'is_active' => false,
        ]);
        $item = $this->item(['inventory_subcategory_id' => $subcategory->id]);
        $category->update(['is_active' => false]);
        $unit->update(['is_active' => false]);
        $this->actingAs(User::factory()->create());

        $page = $this->get(route('inventory.edit', $item))->assertOk();
        $document = $this->document($page->getContent());
        $this->assertSame('food', $document->evaluate('string(//select[@name="category"]/option[@selected]/@value)'));
        $this->assertSame('pack', $document->evaluate('string(//input[@name="unit"]/@value)'));
        $this->assertSame('old_rice', $document->evaluate('string(//select[@name="subcategory_selection"]/option[@selected]/@value)'));

        $this->patch(route('inventory.update', $item), $this->payload(['subcategory_selection' => 'old_rice']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('1250.75', $item->fresh()->estimated_unit_value);
        $this->assertSame($subcategory->id, $item->fresh()->inventory_subcategory_id);

        $other = $this->item(['category' => 'clothing', 'unit' => 'piece']);
        $this->patchJson(route('inventory.update', $other), $this->payload(['unit' => 'piece']))
            ->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_failed_custom_classification_update_does_not_leave_an_unused_category(): void
    {
        $role = Role::query()->where('name', 'admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $item = $this->item();
        $response = $this->actingAs($user)->patchJson(route('inventory.update', $item), $this->payload([
            'category' => '__other__', 'custom_category' => 'Must Roll Back', 'subcategory_selection' => 'missing',
        ]));
        $this->assertDatabaseMissing('inventory_categories', ['label' => 'Must Roll Back']);
        $response->assertUnprocessable()->assertJsonValidationErrors('subcategory_selection');
        $this->assertSame('food', $item->fresh()->category);
    }

    public function test_an_empty_item_without_stock_history_can_still_correct_its_unit(): void
    {
        $item = $this->item(['quantity' => 0, 'current_quantity' => '0', 'unit' => 'sack']);
        $this->actingAs(User::factory()->create())->patch(route('inventory.update', $item), $this->payload(['unit' => 'kilogram']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('kilogram', $item->fresh()->unit);
    }

    public function test_staff_cannot_bypass_archive_permissions_through_item_create_or_update(): void
    {
        $item = $this->item();
        $staff = User::factory()->create();
        $this->assertFalse($staff->can('archive', $item));
        $this->actingAs($staff)->patchJson(route('inventory.update', $item), $this->payload(['status' => 'archived']))->assertForbidden();
        $this->assertSame('active', $item->fresh()->status);
        $this->postJson(route('inventory.store'), $this->payload(['status' => 'archived']))->assertForbidden();
        $this->assertDatabaseCount('inventory_items', 1);

        $item->update(['status' => 'archived']);
        $this->patchJson(route('inventory.update', $item), $this->payload(['status' => 'active']))->assertForbidden();
        $this->assertSame('archived', $item->fresh()->status);
        $this->patch(route('inventory.update', $item), $this->payload(['status' => 'archived']))->assertSessionDoesntHaveErrors();
    }

    public function test_admin_can_archive_and_restore_an_inventory_item(): void
    {
        $role = Role::query()->where('name', 'admin')->firstOrFail();
        $item = $this->item();
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        foreach (['archived', 'active'] as $status) {
            $this->patch(route('inventory.update', $item), $this->payload(['status' => $status]))->assertSessionDoesntHaveErrors();
            $this->assertSame($status, $item->fresh()->status);
        }
    }

    public function test_subcategory_keys_shared_by_different_categories_resolve_to_the_selected_category(): void
    {
        foreach (['food', 'clothing'] as $key) {
            $category = InventoryCategory::query()->where('key', $key)->firstOrFail();
            $subcategory = InventorySubcategory::query()->create([
                'inventory_category_id' => $category->id, 'key' => 'shared', 'label' => 'Shared', 'is_active' => true,
            ]);
        }
        $item = $this->item(['category' => 'clothing', 'inventory_subcategory_id' => $subcategory->id]);
        $this->actingAs(User::factory()->create());
        $payload = $this->payload(['category' => 'clothing', 'subcategory_selection' => 'shared']);
        $this->patch(route('inventory.update', $item), $payload)->assertSessionDoesntHaveErrors();
        $this->assertSame($subcategory->id, $item->fresh()->inventory_subcategory_id);
        $this->post(route('inventory.store'), $payload)->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('inventory_items', 2);
    }

    public function test_unit_guard_reads_current_stock_instead_of_a_stale_edit_model(): void
    {
        $user = User::factory()->create();
        $item = $this->item(['quantity' => 0, 'current_quantity' => '0', 'unit' => 'sack']);
        app(AdjustInventoryStock::class)->handle($item, [
            'movement_type' => 'stock_in', 'quantity' => '2', 'reason' => 'correction',
        ], $user);
        $this->assertSame('0.00', $item->current_quantity);

        try {
            app(UpdateInventoryItem::class)->handle(
                $item, $this->payload(['unit' => 'kilogram']), $user,
                app(ResolveInventoryClassification::class),
            );
            $this->fail('A stale edit must not relabel newly recorded stock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('unit', $error->errors());
        }
        $this->assertSame('sack', $item->fresh()->unit);
        $this->assertSame('2.00', $item->fresh()->current_quantity);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Valued Rice Packs', 'category' => 'food', 'unit' => 'pack',
            'initial_quantity' => '20', 'minimum_reserve_quantity' => '5',
            'estimated_unit_value' => '1250.75', 'storage_location' => 'Main Storage', 'status' => 'active',
        ], $overrides);
    }

    private function item(array $overrides = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'name' => 'Rice Packs', 'category' => 'food', 'unit' => 'pack',
            'quantity' => 20, 'current_quantity' => '20', 'minimum_reserve_quantity' => '5',
            'storage_location' => 'Main Storage', 'status' => 'active',
        ], $overrides));
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }
}
