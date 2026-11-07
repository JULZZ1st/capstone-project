<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Schoolees\Psgc\Models\Barangay;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Province;

class LocationController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $regionCode = (string) $request->query('region_code', '');

        $provinces = $regionCode === ''
            ? collect()
            : Province::query()->where('region_code', $regionCode)->orderBy('name')->get(['code', 'name']);

        return response()->json($provinces->values());
    }

    public function cities(Request $request): JsonResponse
    {
        $regionCode = (string) $request->query('region_code', '');
        $provinceCode = (string) $request->query('province_code', '');

        $query = City::query()->orderBy('name');

        if ($provinceCode !== '') {
            $query->where('province_code', $provinceCode);
        } elseif ($regionCode !== '') {
            $query->where('region_code', $regionCode)->whereNull('province_code');
        } else {
            return response()->json([]);
        }

        return response()->json($query->get(['code', 'name']));
    }

    public function barangays(Request $request): JsonResponse
    {
        $cityCode = (string) $request->query('city_code', '');

        $barangays = $cityCode === ''
            ? collect()
            : Barangay::query()->where('city_code', $cityCode)->orderBy('name')->get(['code', 'name']);

        return response()->json($barangays->values());
    }
}
