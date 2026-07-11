<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('category')->index();
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->string('condition')->index();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('contribution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_items');
    }
};
