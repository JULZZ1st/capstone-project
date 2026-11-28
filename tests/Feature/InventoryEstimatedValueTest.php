<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
