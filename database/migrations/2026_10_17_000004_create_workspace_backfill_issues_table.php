<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_backfill_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('table_name', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('issue_type', 100);
            $table->text('details');
            $table->timestamps();

            $table->unique(['table_name', 'record_id', 'issue_type'], 'workspace_backfill_issue_unique');
            $table->index(['table_name', 'issue_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_backfill_issues');
    }
};
