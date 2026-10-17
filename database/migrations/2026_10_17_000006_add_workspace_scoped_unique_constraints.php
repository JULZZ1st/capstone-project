<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceUnique('campaigns', ['slug'], ['workspace_id', 'slug'], 'campaigns_workspace_slug_unique');
        $this->replaceUnique('contributions', ['payment_method', 'reference_number'], ['workspace_id', 'payment_method', 'reference_number'], 'contributions_workspace_payment_reference_unique');
        $this->replaceUnique('inventory_categories', ['key'], ['workspace_id', 'key'], 'inventory_categories_workspace_key_unique');
        $this->replaceUnique('inventory_units', ['key'], ['workspace_id', 'key'], 'inventory_units_workspace_key_unique');
        $this->replaceUnique('bank_ledger_entries', ['statement_id'], ['workspace_id', 'statement_id'], 'bank_ledger_entries_workspace_statement_unique');
    }

    public function down(): void
    {
        $this->dropIfPresent('campaigns', 'campaigns_workspace_slug_unique');
        $this->dropIfPresent('contributions', 'contributions_workspace_payment_reference_unique');
        $this->dropIfPresent('inventory_categories', 'inventory_categories_workspace_key_unique');
        $this->dropIfPresent('inventory_units', 'inventory_units_workspace_key_unique');
        $this->dropIfPresent('bank_ledger_entries', 'bank_ledger_entries_workspace_statement_unique');

        Schema::table('campaigns', fn (Blueprint $table) => $table->unique('slug'));
        Schema::table('contributions', fn (Blueprint $table) => $table->unique(['payment_method', 'reference_number']));
        Schema::table('inventory_categories', fn (Blueprint $table) => $table->unique('key'));
        Schema::table('inventory_units', fn (Blueprint $table) => $table->unique('key'));
        Schema::table('bank_ledger_entries', fn (Blueprint $table) => $table->unique('statement_id'));
    }

    private function replaceUnique(string $tableName, array $oldColumns, array $newColumns, string $newName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($oldColumns): void {
            $table->dropUnique($oldColumns);
        });

        Schema::table($tableName, function (Blueprint $table) use ($newColumns, $newName): void {
            $table->unique($newColumns, $newName);
        });
    }

    private function dropIfPresent(string $tableName, string $indexName): void
    {
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($indexName));
        }
    }
};
