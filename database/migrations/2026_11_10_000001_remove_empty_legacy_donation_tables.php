<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove only the repository-confirmed empty legacy tables.
     *
     * The checks intentionally fail closed if historical data or an unexpected
     * inbound foreign key appears before this migration is deployed.
     */
    public function up(): void
    {
        $tables = ['donations', 'cash_transactions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() > 0) {
                throw new \RuntimeException("Refusing to remove non-empty table: {$table}");
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $inbound = DB::select(
                'SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ?
                   AND REFERENCED_TABLE_NAME IN (?, ?)',
                [DB::getDatabaseName(), ...$tables],
            );

            if ($inbound !== []) {
                $references = collect($inbound)
                    ->map(fn (object $foreignKey): string => "{$foreignKey->TABLE_NAME}.{$foreignKey->COLUMN_NAME} -> {$foreignKey->REFERENCED_TABLE_NAME}.{$foreignKey->REFERENCED_COLUMN_NAME}")
                    ->implode(', ');

                throw new \RuntimeException("Refusing to remove tables with inbound foreign keys: {$references}");
            }
        }

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('donations')) {
            $donorTable = Schema::hasTable('legacy_donors') ? 'legacy_donors' : 'donors';

            Schema::create('donations', function (Blueprint $table) use ($donorTable): void {
                $table->id();
                $table->foreignId('donor_id')->constrained($donorTable)->onDelete('cascade');
                $table->string('donation_type');
                $table->decimal('amount', 10, 2);
                $table->text('description')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cash_transactions')) {
            Schema::create('cash_transactions', function (Blueprint $table): void {
                $table->id();
                $table->string('donor_name');
                $table->decimal('amount', 10, 2);
                $table->string('purpose');
                $table->timestamp('received_at')->nullable();
                $table->string('status')->default('verified');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }
};
