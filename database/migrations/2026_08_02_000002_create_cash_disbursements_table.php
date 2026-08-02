<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursed_to');
            $table->string('purpose')->index();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('disbursed_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purpose', 'disbursed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_disbursements');
    }
};
