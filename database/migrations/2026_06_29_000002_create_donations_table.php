<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained()->onDelete('cascade');
            $table->string('donation_type');
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
