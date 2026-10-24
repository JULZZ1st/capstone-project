<?php

return [
    'default_role' => env('AUTH_DEFAULT_ROLE', 'secretary'),
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
            'description' => 'Manages all workspaces, staff accounts, and operational workflows.',
        ],
        'secretary' => [
            'display_name' => 'Secretary',
            'description' => 'Manages donor and in-kind records and inventory; cannot manage staff, verify cash, or reconcile banks.',
        ],
        'treasurer' => [
            'display_name' => 'Treasurer',
            'description' => 'Verifies cash transactions and reconciles banks; cannot manage workspaces, staff, or inventory.',
        ],
    ],
];
