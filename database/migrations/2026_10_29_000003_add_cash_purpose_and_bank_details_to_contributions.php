<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions')) {
            throw new RuntimeException('Cannot add cash purpose and bank details: contributions table is missing.');
        }

        Schema::table('contributions', function (Blueprint $table): void {
            if (! Schema::hasColumn('contributions', 'purpose_type')) {
                $table->string('purpose_type', 30)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('contributions', 'purpose_name')) {
                $table->string('purpose_name', 255)->nullable()->after('purpose_type');
            }
            if (! Schema::hasColumn('contributions', 'registered_bank_account')) {
                $column = $table->string('registered_bank_account', 150)->nullable();
                if (Schema::hasColumn('contributions', 'bank_name')) {
                    $column->after('bank_name');
                }
            }
            if (! Schema::hasColumn('contributions', 'donor_tin')) {
                $column = $table->string('donor_tin', 30)->nullable();
                if (Schema::hasColumn('contributions', 'certificate_issued_at')) {
                    $column->after('certificate_issued_at');
                }
            }
            if (! Schema::hasIndex('contributions', ['type', 'purpose_type'])) {
                $table->index(['type', 'purpose_type']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contributions')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table): void {
            if (Schema::hasIndex('contributions', ['type', 'purpose_type'])) {
                $table->dropIndex(['type', 'purpose_type']);
            }
            $columns = array_values(array_filter(
                ['purpose_type', 'purpose_name', 'registered_bank_account', 'donor_tin'],
                fn (string $column): bool => Schema::hasColumn('contributions', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
