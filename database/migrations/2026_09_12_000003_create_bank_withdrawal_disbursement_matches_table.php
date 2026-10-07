<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_withdrawal_disbursement_matches')) {
            return;
        }

        Schema::create('bank_withdrawal_disbursement_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_ledger_entry_id');
            $table->foreignId('cash_disbursement_id');
            $table->timestamps();

            $table->foreign('bank_ledger_entry_id')->references('id')->on('bank_ledger_entries')->cascadeOnDelete()->name('bank_ledger_match_fk');
            $table->foreign('cash_disbursement_id')->references('id')->on('cash_disbursements')->cascadeOnDelete()->name('cash_disbursement_match_fk');

            $table->unique(['bank_ledger_entry_id', 'cash_disbursement_id'], 'bank_withdrawal_disbursement_unique');
            $table->unique('cash_disbursement_id', 'cash_disbursement_single_withdrawal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_withdrawal_disbursement_matches');
    }
};
