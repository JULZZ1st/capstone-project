<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_disbursement_payments')) {
            return;
        }

        foreach (['workspaces', 'cash_disbursements', 'users'] as $requiredTable) {
            if (! Schema::hasTable($requiredTable)) {
                throw new RuntimeException("Cannot create cash_disbursement_payments: missing {$requiredTable} table.");
            }
        }

        Schema::create('cash_disbursement_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_disbursement_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('reference_number')->nullable();
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at');
            $table->timestamps();

            $table->index(['workspace_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_disbursement_payments')) {
            Schema::drop('cash_disbursement_payments');
        }
    }
};
