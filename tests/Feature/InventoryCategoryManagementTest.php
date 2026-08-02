<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
