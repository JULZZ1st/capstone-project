<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'unit')) {
                $table->string('unit')->default(config('inventory.defaults.unit'))->after('category');
            }
            $table->decimal('current_quantity', 12, 2)->default(0)->after('quantity');
            $table->decimal('minimum_reserve_quantity', 12, 2)->default(0)->after('current_quantity');
            $table->decimal('estimated_unit_value', 12, 2)->nullable()->after('minimum_reserve_quantity');
            $table->date('expires_at')->nullable()->after('estimated_unit_value');
            $table->string('batch_number', 100)->nullable()->after('expires_at');
            $table->string('storage_location')->default('Main Storage')->after('batch_number');
            $table->text('notes')->nullable()->after('storage_location');
            $table->timestamp('last_audited_at')->nullable()->after('received_at');
            $table->timestamp('last_movement_at')->nullable()->after('last_audited_at');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index('category');
            $table->index('status');
            $table->index('storage_location');
            $table->index('expires_at');
            $table->index('last_audited_at');
            $table->index('last_movement_at');
            $table->index(['category', 'status']);
            $table->index(['status', 'expires_at']);
        });

        DB::table('inventory_items')->update([
            'current_quantity' => DB::raw('quantity'),
            'minimum_reserve_quantity' => 0,
            'status' => config('inventory.defaults.status'),
            'last_movement_at' => DB::raw('received_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['category']);
            $table->dropIndex(['status']);
            $table->dropIndex(['storage_location']);
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['last_audited_at']);
            $table->dropIndex(['last_movement_at']);
            $table->dropIndex(['category', 'status']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn([
                'unit',
                'current_quantity',
                'minimum_reserve_quantity',
                'estimated_unit_value',
                'expires_at',
                'batch_number',
                'storage_location',
                'notes',
                'last_audited_at',
                'last_movement_at',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
