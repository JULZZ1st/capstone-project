<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Region;
use Tests\TestCase;

class AddressReferenceSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_populates_address_options_and_preserves_existing_locations_on_rerun(): void
    {
        $customRegion = Region::query()->create(['code' => '9900000000', 'name' => 'Existing custom region']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('regions', ['name' => 'Region VII (Central Visayas)']);
        $this->assertDatabaseHas('provinces', ['name' => 'Cebu']);
        $this->assertTrue(City::query()->where('name', 'City of Cebu')->whereNull('province_code')->exists());
        $this->assertGreaterThan(0, DB::table('barangays')->count());
        $this->assertModelExists($customRegion);

        $counts = collect(['regions', 'provinces', 'cities', 'barangays'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->seed(DatabaseSeeder::class);

        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }

        $this->assertModelExists($customRegion);
    }
}
