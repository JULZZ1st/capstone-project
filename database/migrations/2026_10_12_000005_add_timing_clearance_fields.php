<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reconciliation_discrepancies', 'cleared_bank_ledger_entry_id')) {
            Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
                $table->unsignedBigInteger('cleared_bank_ledger_entry_id')->nullable()->unique();
            });
        }
        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            $table->foreign('cleared_bank_ledger_entry_id', 'recon_discrepancies_cleared_entry_fk')->references('id')->on('bank_ledger_entries')->nullOnDelete();
        });
        if (! Schema::hasColumn('reconciliation_discrepancies', 'cleared_at')) {
            Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
                $table->timestamp('cleared_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('reconciliation_discrepancies', function (Blueprint $table) {
            $table->dropForeign('recon_discrepancies_cleared_entry_fk');
            $table->dropUnique(['cleared_bank_ledger_entry_id']);
            $table->dropColumn(['cleared_bank_ledger_entry_id', 'cleared_at']);
        });
    }
};
