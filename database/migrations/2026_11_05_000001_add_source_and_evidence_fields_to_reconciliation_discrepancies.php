<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliation_discrepancies', 'bank_ledger_entry_id')) {
                $table->foreignId('bank_ledger_entry_id')->nullable()->after('bank_reconciliation_id')->constrained('bank_ledger_entries')->nullOnDelete();
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'system_record_type')) {
                $table->string('system_record_type')->nullable()->after('bank_ledger_entry_id');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'system_record_id')) {
                $table->unsignedBigInteger('system_record_id')->nullable()->after('system_record_type');
                $table->index(['system_record_type', 'system_record_id'], 'recon_discrepancies_system_record_idx');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'evidence_path')) {
                $table->string('evidence_path')->nullable()->after('evidence_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            if (Schema::hasColumn('reconciliation_discrepancies', 'bank_ledger_entry_id')) {
                $table->dropForeign(['bank_ledger_entry_id']);
                $table->dropColumn('bank_ledger_entry_id');
            }
            if (Schema::hasColumn('reconciliation_discrepancies', 'system_record_id')) {
                $table->dropIndex('recon_discrepancies_system_record_idx');
                $table->dropColumn(['system_record_type', 'system_record_id']);
            }
            if (Schema::hasColumn('reconciliation_discrepancies', 'evidence_path')) {
                $table->dropColumn('evidence_path');
            }
        });
    }
};
