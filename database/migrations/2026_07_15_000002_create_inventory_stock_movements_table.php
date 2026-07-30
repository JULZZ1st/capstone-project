<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type')->index();
            $table->decimal('quantity', 12, 2);
            $table->decimal('quantity_before', 12, 2);
            $table->decimal('quantity_after', 12, 2);
            $table->string('reason')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('created_by');
            $table->index('created_at');
            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['movement_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
