<?php

return [
    'barangay_name' => env('BARANGAY_NAME', env('APP_NAME', 'Barangay Donation Platform')),

    'limits' => [
        'recent_items' => (int) env('DASHBOARD_RECENT_ITEMS_LIMIT', 5),
        'funding_categories' => (int) env('DASHBOARD_FUNDING_CATEGORIES_LIMIT', 5),
    ],

    'cache' => [
        'safe_summary_ttl' => (int) env('DASHBOARD_SAFE_SUMMARY_CACHE_TTL', 300),
    ],

    'cash_statuses' => [
        'pending' => env('DASHBOARD_CASH_PENDING_STATUS', 'pending'),
        'verified' => env('DASHBOARD_CASH_VERIFIED_STATUS', 'verified'),
        'approved' => env('DASHBOARD_CASH_APPROVED_STATUS', 'approved'),
    ],

    'inventory_statuses' => [
        'received' => env('DASHBOARD_INVENTORY_RECEIVED_STATUS', 'received'),
    ],
];
