<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            throw new RuntimeException('Cannot add discrepancy review fields: reconciliation_discrepancies table is missing.');
        }
        if (! Schema::hasTable('users')) {
            throw new RuntimeException('Cannot add discrepancy review fields: users table is missing.');
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
            if (! Schema::hasColumn('reconciliation_discrepancies', 'reported_by')) {
                $table->foreignId('reported_by')->nullable()->after('bank_reconciliation_id')->constrained('users')->nullOnDelete();
            } elseif (! $this->hasForeignKey('reconciliation_discrepancies', 'reconciliation_discrepancies_reported_by_foreign')) {
                $table->foreign('reported_by', 'reconciliation_discrepancies_reported_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'admin_review_status')) {
                $table->string('admin_review_status')->default('pending')->after('resolution_status');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'admin_reviewed_by')) {
                $table->foreignId('admin_reviewed_by')->nullable()->after('admin_review_status')->constrained('users')->nullOnDelete();
            } elseif (! $this->hasForeignKey('reconciliation_discrepancies', 'reconciliation_discrepancies_admin_reviewed_by_foreign')) {
                $table->foreign('admin_reviewed_by', 'reconciliation_discrepancies_admin_reviewed_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'admin_reviewed_at')) {
                $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'admin_review_note')) {
                $table->text('admin_review_note')->nullable()->after('admin_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            return;
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
            foreach (['reconciliation_discrepancies_reported_by_foreign', 'reconciliation_discrepancies_admin_reviewed_by_foreign'] as $foreign) {
                if ($this->hasForeignKey('reconciliation_discrepancies', $foreign)) {
                    $table->dropForeign($foreign);
                }
            }

            $columns = array_values(array_filter(
                ['reported_by', 'admin_review_status', 'admin_reviewed_by', 'admin_reviewed_at', 'admin_review_note'],
                fn (string $column): bool => Schema::hasColumn('reconciliation_discrepancies', $column),
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }
};
