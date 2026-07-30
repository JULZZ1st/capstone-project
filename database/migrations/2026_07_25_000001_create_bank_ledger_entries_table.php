<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('statement_id')->unique();
            $table->string('description');
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('unmatched');
            $table->foreignId('matched_contribution_id')->nullable()->unique()->constrained('contributions')->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'transaction_date']);
            $table->index('reference_number');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_ledger_entries');
    }
};
