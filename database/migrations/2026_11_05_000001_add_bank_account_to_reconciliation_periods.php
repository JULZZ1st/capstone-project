<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            $table->string('bank_account')->nullable()->after('workspace_id');
            $table->index(['workspace_id', 'bank_account', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'bank_account', 'period_end']);
            $table->dropColumn('bank_account');
        });
    }
};
