<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions')) {
            throw new RuntimeException('Cannot add donation receipt details: contributions table is missing.');
        }

        Schema::table('contributions', function (Blueprint $table): void {
            if (! Schema::hasColumn('contributions', 'check_number')) {
                $table->string('check_number', 100)->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('contributions', 'bank_name')) {
                $table->string('bank_name', 150)->nullable()->after('check_number');
            }
            if (! Schema::hasColumn('contributions', 'official_receipt_number')) {
                $table->string('official_receipt_number', 100)->nullable()->after('bank_name');
            }
            if (! Schema::hasIndex('contributions', ['payment_method', 'check_number'])) {
                $table->index(['payment_method', 'check_number']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contributions')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            if (Schema::hasIndex('contributions', ['payment_method', 'check_number'])) {
                $table->dropIndex(['payment_method', 'check_number']);
            }
            $columns = array_values(array_filter(
                ['check_number', 'bank_name', 'official_receipt_number'],
                fn (string $column): bool => Schema::hasColumn('contributions', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
