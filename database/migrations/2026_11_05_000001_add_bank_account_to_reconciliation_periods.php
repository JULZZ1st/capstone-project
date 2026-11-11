<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_reconciliations')) {
            throw new RuntimeException('Cannot add bank account: bank_reconciliations table is missing.');
        }

        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_reconciliations', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('workspace_id');
            }
            if (! Schema::hasIndex('bank_reconciliations', ['workspace_id', 'bank_account', 'period_end'])) {
                $table->index(['workspace_id', 'bank_account', 'period_end']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bank_reconciliations')) {
            return;
        }

        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            if (Schema::hasIndex('bank_reconciliations', ['workspace_id', 'bank_account', 'period_end'])) {
                $table->dropIndex(['workspace_id', 'bank_account', 'period_end']);
            }
            if (Schema::hasColumn('bank_reconciliations', 'bank_account')) {
                $table->dropColumn('bank_account');
            }
        });
    }
};
