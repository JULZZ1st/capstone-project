<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('date');
            $table->date('period_end')->nullable()->after('period_start');
            $table->decimal('bank_ending_balance', 12, 2)->nullable()->after('opening_balance');
            $table->decimal('calculated_bank_cash_in', 12, 2)->default(0)->after('cash_in');
            $table->decimal('calculated_bank_cash_out', 12, 2)->default(0)->after('cash_out');
            $table->decimal('financial_ledger_cash_in', 12, 2)->default(0)->after('calculated_bank_cash_out');
            $table->decimal('financial_ledger_cash_out', 12, 2)->default(0)->after('financial_ledger_cash_in');
            $table->decimal('financial_ledger_ending_balance', 12, 2)->nullable()->after('closing_balance');
            $table->decimal('reconciliation_difference', 12, 2)->nullable()->after('financial_ledger_ending_balance');
            $table->string('status')->default('draft')->after('reconciliation_difference');
            $table->foreignId('locked_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->timestamp('calculated_at')->nullable()->after('locked_at');
            $table->decimal('accounting_adjustment', 12, 2)->default(0)->after('reconciliation_difference');
            $table->decimal('adjusted_bank_balance', 12, 2)->nullable()->after('accounting_adjustment');
            $table->decimal('unresolved_discrepancy_amount', 12, 2)->default(0)->after('adjusted_bank_balance');

            $table->index(['period_start', 'period_end']);
            $table->index(['status', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropIndex(['period_start', 'period_end']);
            $table->dropIndex(['status', 'period_end']);
            $table->dropColumn([
                'period_start', 'period_end', 'bank_ending_balance',
                'calculated_bank_cash_in', 'calculated_bank_cash_out',
                'financial_ledger_cash_in', 'financial_ledger_cash_out',
                'financial_ledger_ending_balance', 'reconciliation_difference',
                'status', 'locked_by', 'locked_at', 'calculated_at',
                'accounting_adjustment', 'adjusted_bank_balance', 'unresolved_discrepancy_amount',
            ]);
        });
    }
};
