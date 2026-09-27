<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_withdrawal_disbursement_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_ledger_entry_id')->constrained('bank_ledger_entries')->cascadeOnDelete();
            $table->foreignId('cash_disbursement_id')->constrained('cash_disbursements')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bank_ledger_entry_id', 'cash_disbursement_id'], 'bank_withdrawal_disbursement_unique');
            $table->unique('cash_disbursement_id', 'cash_disbursement_single_withdrawal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_withdrawal_disbursement_matches');
    }
};
