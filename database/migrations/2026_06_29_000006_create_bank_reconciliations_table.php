<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('cash_in', 10, 2)->default(0);
            $table->decimal('cash_out', 10, 2)->default(0);
            $table->decimal('closing_balance', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
