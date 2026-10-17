<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'donors',
        'campaigns',
        'contributions',
        'contribution_items',
        'inventory_categories',
        'inventory_subcategories',
        'inventory_units',
        'inventory_items',
        'inventory_stock_movements',
        'cash_disbursements',
        'bank_ledger_entries',
        'bank_reconciliations',
        'reconciliation_discrepancies',
        'bank_withdrawal_disbursement_matches',
        'security_activity_events',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->after('id');
                $table->index('workspace_id');
                $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['workspace_id']);
                $table->dropIndex(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }
    }
};
