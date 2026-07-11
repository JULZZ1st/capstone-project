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
            'display_name' => 'Administrator',
            'description' => 'Full system access and user management.',
        ],
        'secretary' => [
            'display_name' => 'Secretary',
            'description' => 'Donor records and in-kind inventory workflows.',
        ],
        'treasurer' => [
            'display_name' => 'Treasurer',
            'description' => 'Cash transactions and bank reconciliation workflows.',
        ],
    ],
];
