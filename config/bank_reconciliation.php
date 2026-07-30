<?php

return [
    'audit_lockout_enabled' => (bool) env('BANK_RECONCILIATION_AUDIT_LOCKOUT', true),

    'statuses' => [
        'unmatched' => ['label' => 'Unreconciled', 'class' => 'bg-amber-100 text-amber-800 ring-amber-200'],
        'reconciled' => ['label' => 'Reconciled', 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
    ],

    'receipt_statuses' => [
        'pending' => ['label' => 'Pending Ledger Clear', 'class' => 'bg-indigo-100 text-indigo-800 ring-indigo-200'],
        'reconciled' => ['label' => 'Reconciled', 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
    ],

    // Maps keywords found in a bank statement description to a donations.php payment method,
    // so the matcher can compare like-for-like channels without a manual mapping step per entry.
    'channel_keywords' => [
        'gcash' => 'gcash',
        'maya' => 'maya',
        'paymaya' => 'maya',
        'check' => 'check',
        'cheque' => 'check',
        'pesonet' => 'bank_transfer',
        'instapay' => 'bank_transfer',
        'bdo' => 'bank_transfer',
        'bpi' => 'bank_transfer',
        'landbank' => 'bank_transfer',
        'metrobank' => 'bank_transfer',
        'unionbank' => 'bank_transfer',
        'bank' => 'bank_transfer',
    ],

    'matching' => [
        // Points awarded per matching signal when scoring a contribution against a ledger entry.
        'weights' => [
            'reference_number' => 60,
            'amount' => 30,
            'payment_method' => 10,
            'date_proximity' => 10,
        ],
        'date_window_days' => 5,
        'minimum_score' => 30,
        'max_suggestions' => 3,
    ],

    'limits' => [
        'feed' => (int) env('BANK_RECONCILIATION_FEED_LIMIT', 20),
        'pending_receipts' => (int) env('BANK_RECONCILIATION_RECEIPTS_LIMIT', 20),
    ],
];
