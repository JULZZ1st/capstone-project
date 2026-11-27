<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_stock_movements') || ! Schema::hasTable('inventory_items')) {
            return;
        }

        $orphans = DB::table('inventory_stock_movements as movements')
            ->leftJoin('inventory_items as items', 'items.id', '=', 'movements.inventory_item_id')
            ->whereNull('items.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Cannot repair inventory stock movement foreign key: {$orphans} stock movement(s) reference missing inventory items."
            );
        }

        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
            $table->foreign('inventory_item_id', 'inventory_stock_movements_inventory_item_id_foreign')
                ->references('id')
                ->on('inventory_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_stock_movements') || ! Schema::hasTable('legacy_inventory_items')) {
            return;
        }

        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
            $table->foreign('inventory_item_id', 'inventory_stock_movements_inventory_item_id_foreign')
                ->references('id')
                ->on('legacy_inventory_items')
                ->restrictOnDelete();
        });
    }
};
