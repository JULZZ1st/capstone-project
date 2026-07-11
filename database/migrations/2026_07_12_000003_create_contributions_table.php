<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('payment_method')->nullable()->index();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('donor_id');
            $table->index('campaign_id');
            $table->index('created_by');
            $table->index('verified_by');
            $table->index(['type', 'status', 'received_at']);
            $table->index(['campaign_id', 'status']);
            $table->unique(['payment_method', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
