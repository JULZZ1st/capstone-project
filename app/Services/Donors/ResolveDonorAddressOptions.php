<?php

namespace App\Services\Donors;

use Illuminate\Support\Collection;
use Schoolees\Psgc\Models\Barangay;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Province;
use Schoolees\Psgc\Models\Region;

class ResolveDonorAddressOptions
{
    private ?Collection $regions = null;

    private array $provincesByRegion = [];

    private array $citiesByParent = [];

    private array $barangaysByCity = [];

    public function handle(?string $regionName, ?string $provinceName, ?string $cityName, ?string $barangayName): array
    {
        $this->regions ??= Region::query()->orderBy('name')->get(['code', 'name']);
        $region = $this->matchByName($this->regions, $regionName);

        $provinces = collect();
        $province = null;
        $cities = collect();
        $city = null;
        $barangays = collect();
        $barangay = null;

        if ($region) {
            $provinces = $this->provincesByRegion[$region->code]
                ??= Province::query()->where('region_code', $region->code)->orderBy('name')->get(['code', 'name']);
            $province = $this->matchByName($provinces, $provinceName);

            $cityKey = $province ? 'province:'.$province->code : 'region:'.$region->code;
            $cities = $this->citiesByParent[$cityKey] ??= $province
                ? City::query()->where('province_code', $province->code)->orderBy('name')->get(['code', 'name'])
                : City::query()->where('region_code', $region->code)->whereNull('province_code')->orderBy('name')->get(['code', 'name']);

            $city = $this->matchByName($cities, $cityName);

            if ($city) {
                $barangays = $this->barangaysByCity[$city->code]
                    ??= Barangay::query()->where('city_code', $city->code)->orderBy('name')->get(['code', 'name']);
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
