<?php

return [
    'types' => [
        'cash' => 'Cash Donation',
        'in_kind' => 'In-Kind Goods',
    ],

    'statuses' => [
        'pending' => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-800 ring-amber-200'],
        'verified' => ['label' => 'Verified', 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
        'received' => ['label' => 'Received', 'class' => 'bg-blue-100 text-blue-800 ring-blue-200'],
        'distributed' => ['label' => 'Distributed', 'class' => 'bg-slate-100 text-slate-800 ring-slate-200'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-800 ring-red-200'],
        'rejected' => ['label' => 'Rejected', 'class' => 'bg-red-100 text-red-800 ring-red-200'],
    ],

    'default_status' => [
        'cash' => 'pending',
        'in_kind' => 'received',
    ],

    'payment_methods' => [
        'cash' => 'Cash (Handed in Person)',
        'check' => 'Check',
        'bank_transfer' => 'Bank Transfer / Deposit',
    ],

    // Retained only for displaying historical records. It is intentionally
    // excluded from payment_methods so new contributions cannot select it.
    'legacy_payment_methods' => [
        'money_order' => 'Money Order',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'other' => 'Other',
    ],

    // Accepted only for backward-compatible direct imports/submissions; these
    // methods are intentionally absent from the new-donation UI.
    'legacy_input_payment_methods' => [
        'gcash',
        'maya',
        'other',
    ],

    'electronic_payment_methods' => [
        'bank_transfer',
        'gcash',
        'maya',
        'check',
    ],

    'item_categories' => [
        'food' => 'Food',
        'medical_supplies' => 'Medical Supplies',
        'clothing' => 'Clothing',
        'hygiene_supplies' => 'Hygiene Supplies',
        'baby_supplies' => 'Baby Supplies',
        'shelter_materials' => 'Shelter Materials',
        'school_supplies' => 'School Supplies',
        'kitchen_supplies' => 'Kitchen Supplies',
        'water_beverages' => 'Water & Beverages',
        'disaster_relief_supplies' => 'Disaster Relief Supplies',
        'electronics' => 'Electronics',
        'furniture' => 'Furniture',
        'bedding_linens' => 'Bedding & Linens',
        'cleaning_supplies' => 'Cleaning Supplies',
        'tools_hardware' => 'Tools & Hardware',
        'ppe' => 'Personal Protective Equipment',
        'senior_care_supplies' => 'Senior Care Supplies',
        'mobility_assistive_devices' => 'Mobility & Assistive Devices',
        'animal_feed' => 'Animal Feed',
        'livelihood_supplies' => 'Livelihood Supplies',
        'toys_recreational_items' => 'Toys & Recreational Items',
        'other' => 'Other',
    ],

    // Fixed, simplified category list for In-Kind Donation items. Decoupled
    // from the InventoryCategory taxonomy used by the standalone Inventory
    // module — these keys are never created/edited by users.
    'item_main_categories' => [
        'food_relief' => 'Food / Relief Goods',
        'medical_health' => 'Medical & Health Supplies',
        'it_office_equipment' => 'IT & Office Equipment',
        'disaster_safety' => 'Disaster Response & Safety Gear',
        'construction_hardware' => 'Construction & Hardware Materials',
        'furniture_fixtures' => 'Furniture & Fixtures',
        'others' => 'Others',
    ],

    // Categories for which the Add Item modal shows an Expiration Date field.
    'item_expiration_categories' => ['food_relief', 'medical_health'],

    // Categories for which the Add Item modal shows Brand/Model/Serial Number fields.
    'item_equipment_categories' => ['it_office_equipment', 'furniture_fixtures'],

    'item_conditions' => [
        'new' => 'New',
        'good' => 'Good',
        'used' => 'Used',
        'damaged' => 'Damaged',
    ],

    'units' => [
        'piece' => 'Piece',
        'pack' => 'Pack',
        'box' => 'Box',
        'sack' => 'Sack',
        'bottle' => 'Bottle',
        'can' => 'Can',
        'kilogram' => 'Kilogram',
        'gram' => 'Gram',
        'liter' => 'Liter',
        'meter' => 'Meter',
        'set' => 'Set',
        'pair' => 'Pair',
        'bundle' => 'Bundle',
        'carton' => 'Carton',
        'other' => 'Other',
    ],

    'pagination' => [
        'per_page' => 10,
    ],
];
