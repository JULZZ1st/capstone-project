<?php

namespace App\Services\Donors;

use Illuminate\Support\Collection;
use Schoolees\Psgc\Models\Barangay;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Province;
use Schoolees\Psgc\Models\Region;

class ResolveDonorAddressOptions
{
    public function handle(?string $regionName, ?string $provinceName, ?string $cityName, ?string $barangayName): array
    {
        $region = $this->matchByName(Region::query()->orderBy('name')->get(['code', 'name']), $regionName);

        $provinces = collect();
        $province = null;
        $cities = collect();
        $city = null;
        $barangays = collect();
        $barangay = null;

        if ($region) {
            $provinces = Province::query()->where('region_code', $region->code)->orderBy('name')->get(['code', 'name']);
            $province = $this->matchByName($provinces, $provinceName);

            $cities = $province
                ? City::query()->where('province_code', $province->code)->orderBy('name')->get(['code', 'name'])
                : City::query()->where('region_code', $region->code)->whereNull('province_code')->orderBy('name')->get(['code', 'name']);

            $city = $this->matchByName($cities, $cityName);

            if ($city) {
                $barangays = Barangay::query()->where('city_code', $city->code)->orderBy('name')->get(['code', 'name']);
                $barangay = $this->matchByName($barangays, $barangayName);
            }
        }

        return compact('region', 'provinces', 'province', 'cities', 'city', 'barangays', 'barangay');
    }

    private function matchByName(Collection $options, ?string $name): ?object
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $options->first(fn ($row) => strcasecmp($row->name, $name) === 0);
    }
}
