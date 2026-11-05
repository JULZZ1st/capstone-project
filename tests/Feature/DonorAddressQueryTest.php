<?php

namespace Tests\Feature;

use App\Services\Donors\ResolveDonorAddressOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Schoolees\Psgc\Models\Barangay;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Province;
use Schoolees\Psgc\Models\Region;
use Tests\TestCase;

class DonorAddressQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_forms_share_location_queries_within_the_request(): void
    {
        $this->locations();
        $resolver = app(ResolveDonorAddressOptions::class);

        DB::enableQueryLog();
        try {
            for ($form = 0; $form < 10; $form++) {
                $options = app(ResolveDonorAddressOptions::class)->handle('Region VII', 'Cebu', 'Test City', 'Test Barangay');
                $this->assertSame('Test Barangay', $options['barangay']->name);
            }
            $this->assertSame($resolver, app(ResolveDonorAddressOptions::class));
            $this->assertCount(4, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_independent_cities_and_new_request_data_do_not_share_stale_options(): void
    {
        $this->locations();
        $resolver = app(ResolveDonorAddressOptions::class);
        $provincial = $resolver->handle('Region VII', 'Cebu', 'Test City', 'Test Barangay');
        $independent = $resolver->handle('Region VII', null, 'Independent City', null);

        $this->assertSame('Test City', $provincial['city']->name);
        $this->assertSame('Independent City', $independent['city']->name);
        $this->assertNull($independent['province']);
        $this->assertCount(0, $independent['barangays']);

        Barangay::query()->create(['code' => '722010002', 'name' => 'New Barangay', 'city_code' => '722010000']);
        app()->forgetScopedInstances();
        $newResolver = app(ResolveDonorAddressOptions::class);
        $this->assertNotSame($resolver, $newResolver);
        $fresh = $newResolver->handle('Region VII', 'Cebu', 'Test City', 'New Barangay');
        $this->assertSame('New Barangay', $fresh['barangay']->name);
    }

    private function locations(): void
    {
        Region::query()->create(['code' => '700000000', 'name' => 'Region VII']);
        Province::query()->create(['code' => '722000000', 'name' => 'Cebu', 'region_code' => '700000000']);
        City::query()->create(['code' => '722010000', 'name' => 'Test City', 'region_code' => '700000000', 'province_code' => '722000000']);
        City::query()->create(['code' => '730600000', 'name' => 'Independent City', 'region_code' => '700000000', 'province_code' => null]);
        Barangay::query()->create(['code' => '722010001', 'name' => 'Test Barangay', 'city_code' => '722010000']);
    }
}
