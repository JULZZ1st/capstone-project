<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'donors' => 'legacy_donors',
            'inventory_items' => 'legacy_inventory_items',
        ] as $table => $legacyTable) {
            if (Schema::hasTable($table) && ! Schema::hasTable($legacyTable)) {
                Schema::rename($table, $legacyTable);
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'legacy_donors' => 'donors',
            'legacy_inventory_items' => 'inventory_items',
        ] as $legacyTable => $table) {
            if (Schema::hasTable($legacyTable) && ! Schema::hasTable($table)) {
                Schema::rename($legacyTable, $table);
            }
        }
    }
};