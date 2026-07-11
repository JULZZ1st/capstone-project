<?php

return [
    'types' => [
        'individual' => 'Individual',
        'organization' => 'Organization',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'blocked' => 'Blocked',
    ],

    'contact_methods' => [
        'phone' => 'Phone',
        'email' => 'Email',
        'sms' => 'SMS',
        'in_person' => 'In Person',
    ],

    'defaults' => [
        'type' => env('DONOR_DEFAULT_TYPE', 'individual'),
        'status' => env('DONOR_DEFAULT_STATUS', 'active'),
    ],

    'pagination' => [
        'per_page' => (int) env('DONOR_PER_PAGE', 9),
        'history_per_page' => (int) env('DONOR_HISTORY_PER_PAGE', 10),
    ],

    'email_rule' => env('DONOR_EMAIL_VALIDATE_DNS', false) ? 'email:rfc,dns' : 'email:rfc',

    'donation_types' => [
        'cash' => env('DONOR_CASH_DONATION_TYPE', 'cash'),
        'in_kind' => env('DONOR_IN_KIND_DONATION_TYPE', 'in_kind'),
    ],
];
