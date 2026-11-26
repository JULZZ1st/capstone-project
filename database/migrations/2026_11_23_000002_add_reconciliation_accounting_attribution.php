<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_ledger_entries', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('workspace_id');
                $table->index(['workspace_id', 'bank_account', 'transaction_date'], 'bank_entries_workspace_account_date_idx');
            }
        });

        Schema::table('cash_disbursement_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_disbursement_payments', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('workspace_id');
                $table->index(['workspace_id', 'bank_account', 'payment_date'], 'payments_workspace_account_date_idx');
            }
        });

        Schema::table('contributions', function (Blueprint $table): void {
            if (! Schema::hasColumn('contributions', 'reconciliation_bank_account')) {
                $table->string('reconciliation_bank_account')->nullable()->after('registered_bank_account');
                $table->index(['workspace_id', 'reconciliation_bank_account', 'received_at'], 'contributions_workspace_recon_account_date_idx');
            }
        });

        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_reconciliations', 'book_opening_balance')) {
                $table->decimal('book_opening_balance', 12, 2)->nullable()->after('opening_balance');
            }
            if (! Schema::hasColumn('bank_reconciliations', 'adjusted_book_balance')) {
                $table->decimal('adjusted_book_balance', 12, 2)->nullable()->after('adjusted_bank_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['book_opening_balance', 'adjusted_book_balance'],
                fn (string $column): bool => Schema::hasColumn('bank_reconciliations', $column),
            ));
            if ($columns !== []) $table->dropColumn($columns);
        });

        Schema::table('contributions', function (Blueprint $table): void {
            if (Schema::hasColumn('contributions', 'reconciliation_bank_account')) {
                $table->dropIndex('contributions_workspace_recon_account_date_idx');
                $table->dropColumn('reconciliation_bank_account');
            }
        });

        Schema::table('cash_disbursement_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('cash_disbursement_payments', 'bank_account')) {
                $table->dropIndex('payments_workspace_account_date_idx');
                $table->dropColumn('bank_account');
            }
        });

        Schema::table('bank_ledger_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_ledger_entries', 'bank_account')) {
                $table->dropIndex('bank_entries_workspace_account_date_idx');
                $table->dropColumn('bank_account');
            }
        });
    }
};
