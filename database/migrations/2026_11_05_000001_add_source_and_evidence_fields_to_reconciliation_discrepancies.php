<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            throw new RuntimeException('Cannot add discrepancy source/evidence fields: reconciliation_discrepancies table is missing.');
        }
        foreach (['bank_ledger_entries', 'users'] as $requiredTable) {
            if (! Schema::hasTable($requiredTable)) {
                throw new RuntimeException("Cannot add discrepancy source/evidence fields: missing {$requiredTable} table.");
            }
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_discrepancies', 'bank_ledger_entry_id')) {
                $table->foreignId('bank_ledger_entry_id')->nullable()->after('bank_reconciliation_id')->constrained('bank_ledger_entries')->nullOnDelete();
            } elseif (! $this->hasForeignKey('reconciliation_discrepancies', 'recon_discrepancies_bank_ledger_entry_id_foreign')) {
                $table->foreign('bank_ledger_entry_id', 'recon_discrepancies_bank_ledger_entry_id_foreign')
                    ->references('id')->on('bank_ledger_entries')->nullOnDelete();
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'system_record_type')) {
                $table->string('system_record_type')->nullable()->after('bank_ledger_entry_id');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'system_record_id')) {
                $table->unsignedBigInteger('system_record_id')->nullable()->after('system_record_type');
                if (! Schema::hasIndex('reconciliation_discrepancies', 'recon_discrepancies_system_record_idx')) {
                    $table->index(['system_record_type', 'system_record_id'], 'recon_discrepancies_system_record_idx');
                }
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'evidence_path')) {
                $table->string('evidence_path')->nullable()->after('evidence_reference');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            return;
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            $foreignKey = $this->foreignKeyName('reconciliation_discrepancies', 'bank_ledger_entry_id');
            if ($foreignKey !== null) {
                $table->dropForeign($foreignKey);
            }
            if (Schema::hasColumn('reconciliation_discrepancies', 'bank_ledger_entry_id')) {
                $table->dropColumn('bank_ledger_entry_id');
            }
            if (Schema::hasIndex('reconciliation_discrepancies', 'recon_discrepancies_system_record_idx')) {
                $table->dropIndex('recon_discrepancies_system_record_idx');
            }
            $columns = array_values(array_filter(
                ['system_record_type', 'system_record_id'],
                fn (string $column): bool => Schema::hasColumn('reconciliation_discrepancies', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
            if (Schema::hasColumn('reconciliation_discrepancies', 'evidence_path')) {
                $table->dropColumn('evidence_path');
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $foreignKey = collect(Schema::getForeignKeys($table))
            ->first(fn (array $candidate): bool => in_array($column, $candidate['columns'] ?? [], true));

        return $foreignKey['name'] ?? null;
    }
};
