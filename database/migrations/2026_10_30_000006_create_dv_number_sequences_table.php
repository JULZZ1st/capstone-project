<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dv_number_sequences')) {
            return;
        }
        if (! Schema::hasTable('workspaces')) {
            throw new RuntimeException('Cannot create dv_number_sequences: workspaces table is missing.');
        }

        Schema::create('dv_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['workspace_id', 'period']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dv_number_sequences')) {
            Schema::drop('dv_number_sequences');
        }
    }
};
