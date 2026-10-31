<?php

return [
    // These are deployment-configured choices, not a claim about an official COA list.
    'fund_sources' => array_values(array_filter(array_map(
        static fn (string $source): string => trim($source),
        explode(',', (string) env('FUND_SOURCES', 'General Fund')),
    ))),

    'dv_number' => [
        'period_format' => 'Y-m',
        'sequence_width' => 4,
    ],

    'barangay' => [
        'name' => env('BARANGAY_NAME', env('APP_NAME', 'Barangay Donation Platform')),
        'city_municipality' => env('BARANGAY_CITY_MUNICIPALITY'),
        'province' => env('BARANGAY_PROVINCE'),
        'location_barangay' => env('BARANGAY_BARANGAY'),
    ],
];
