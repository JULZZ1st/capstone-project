<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->string('transaction_type')->default('deposit')->after('transaction_date');
            $table->index('transaction_type', 'bank_ledger_entries_transaction_type_index');
            $table->index(['transaction_type', 'status', 'transaction_date'], 'bank_ledger_entries_type_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('bank_ledger_entries_type_status_date_index');
            $table->dropIndex('bank_ledger_entries_transaction_type_index');
            $table->dropColumn('transaction_type');
        });
    }
};
