<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inventory_items', 'inventory_subcategory_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_subcategory_id')
                ->nullable()
                ->after('category')
                ->constrained('inventory_subcategories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_items', 'inventory_subcategory_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_subcategory_id']);
            $table->dropColumn('inventory_subcategory_id');
        });
    }
};