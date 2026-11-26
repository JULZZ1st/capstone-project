<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_items', function (Blueprint $table): void {
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->after('contribution_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            $table->index(['inventory_item_id', 'contribution_id']);
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->foreignId('contribution_item_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('contribution_items')
                ->restrictOnDelete();

            // A contribution item can create at most one automatic stock-in.
            // Nullable source rows preserve existing manual and imported history.
            $table->unique('contribution_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['contribution_item_id']);
            $table->dropUnique(['contribution_item_id']);
            $table->dropColumn('contribution_item_id');
        });

        Schema::table('contribution_items', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
            $table->dropIndex(['inventory_item_id', 'contribution_id']);
            $table->dropColumn('inventory_item_id');
        });
    }
};
