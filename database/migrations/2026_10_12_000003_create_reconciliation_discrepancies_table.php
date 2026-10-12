<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->string('type');
            $table->string('classification');
            $table->decimal('amount', 12, 2);
            $table->text('explanation');
            $table->string('evidence_reference')->nullable();
            $table->string('resolution_status')->default('unresolved');
            $table->decimal('adjustment_amount', 12, 2)->default(0);
            $table->boolean('carry_forward')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['bank_reconciliation_id', 'resolution_status'], 'recon_discrepancies_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
    }
};
