<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name');
            $table->decimal('amount', 10, 2);
            $table->string('purpose');
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('verified');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
