<?php

return [
    'pagination' => [
        'activity_events' => (int) env('SYSTEM_SECURITY_ACTIVITY_EVENTS_PER_PAGE', 15),
    ],

    'permissions' => [
        [
            'key' => 'manage_accounts_permissions',
            'name' => 'Manage Accounts & Key Permissions',
            'description' => 'Create users, update role access, and maintain authorization keys.',
            'sort_order' => 10,
        ],
        [
            'key' => 'access_audit_logs',
            'name' => 'Access System Audit Logs & Security Tickets',
            'description' => 'Review administrative audit trails and security incident tickets.',
            'sort_order' => 20,
        ],
        [
            'key' => 'register_in_kind_supplies',
            'name' => 'Register & Allocate In-Kind Supplies',
            'description' => 'Manage incoming goods and inventory allocation workflows.',
            'sort_order' => 30,
        ],
        [
            'key' => 'adjust_stock_levels',
            'name' => 'Adjust Warehouse Stock Levels',
            'description' => 'Update inventory counts and warehouse stock movement records.',
            'sort_order' => 40,
        ],
        [
            'key' => 'record_cash_credits',
            'name' => 'Record Cash / GCash Ledger Credits',
            'description' => 'Record cash and electronic donation ledger credits.',
            'sort_order' => 50,
        ],
    ],

    'role_permissions' => [
        'admin' => [
            'manage_accounts_permissions',
            'access_audit_logs',
        ],
        'staff' => [
            'register_in_kind_supplies',
            'adjust_stock_levels',
            'access_audit_logs',
            'record_cash_credits',
        ],
    ],
];
