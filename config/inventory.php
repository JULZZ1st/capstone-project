<?php

return [
    'categories' => [
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

    'subcategories' => [
        'kitchen_supplies' => [
            'cookware' => 'Cookware',
            'utensils' => 'Utensils',
            'dinnerware' => 'Dinnerware',
            'food_storage' => 'Food Storage',
            'small_appliances' => 'Small Appliances',
        ],
        'food' => [
            'rice_grains' => 'Rice & Grains',
            'canned_goods' => 'Canned Goods',
            'noodles' => 'Noodles',
            'snacks' => 'Snacks',
            'condiments' => 'Condiments',
        ],
        'medical_supplies' => [
            'first_aid' => 'First Aid',
            'medicines' => 'Medicines',
            'ppe' => 'PPE',
            'medical_devices' => 'Medical Devices',
        ],
        'clothing' => [
            'shirts' => 'Shirts',
            'pants' => 'Pants',
            'underwear' => 'Underwear',
            'footwear' => 'Footwear',
            'jackets' => 'Jackets',
        ],
        'hygiene_supplies' => [
            'soap' => 'Soap',
            'shampoo' => 'Shampoo',
            'toothcare' => 'Toothcare',
            'sanitary_products' => 'Sanitary Products',
            'cleaning_hygiene' => 'Cleaning Hygiene',
        ],
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

    'item_statuses' => [
        'active' => ['label' => 'Active'],
        'inactive' => ['label' => 'Inactive'],
        'archived' => ['label' => 'Archived'],
    ],

    'stock_statuses' => [
        'adequate' => ['label' => 'Adequate', 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
        'low_stock' => ['label' => 'Low Stock', 'class' => 'bg-amber-100 text-amber-800 ring-amber-200'],
        'critical_stock' => ['label' => 'Critical Stock', 'class' => 'bg-orange-100 text-orange-800 ring-orange-200'],
        'out_of_stock' => ['label' => 'Out of Stock', 'class' => 'bg-red-100 text-red-800 ring-red-200'],
        'expired' => ['label' => 'Expired', 'class' => 'bg-slate-200 text-slate-800 ring-slate-300'],
    ],

    'movement_types' => [
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'adjustment' => 'Stock Adjustment',
    ],

    'adjustment_reasons' => [
        'new_donation' => 'New Donation',
        'distribution' => 'Distribution',
        'physical_count' => 'Physical Count',
        'damaged_goods' => 'Damaged Goods',
        'expired_goods' => 'Expired Goods',
        'correction' => 'Correction',
        'other' => 'Other',
    ],

    'defaults' => [
        'status' => 'active',
        'unit' => 'piece',
    ],

    'pagination' => [
        'per_page' => 10,
    ],

    'alerts' => [
        'limit' => 5,
    ],

    'critical_stock_ratio' => 0.25,
    'expiring_soon_days' => 30,
];
