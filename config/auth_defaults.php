<?php

return [
    'default_role' => env('AUTH_DEFAULT_ROLE', 'staff'),
    'user_status' => env('AUTH_DEFAULT_USER_STATUS', 'active'),

    'password' => [
        'min' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 12),
        'check_uncompromised' => (bool) env('AUTH_PASSWORD_CHECK_UNCOMPROMISED', false),
    ],

    'email' => [
        'validate_dns' => (bool) env('AUTH_EMAIL_VALIDATE_DNS', false),
    ],

    'roles' => [
        'admin' => [
            'display_name' => 'Admin',
            'description' => 'Administers the barangay system, staff accounts, and restricted operational workflows.',
        ],
        'staff' => [
            'display_name' => 'Staff',
            'description' => 'Manages donors, donations, inventory, cash verification, and bank reconciliation; cannot manage accounts or approve restricted transactions.',
        ],
    ],
];
