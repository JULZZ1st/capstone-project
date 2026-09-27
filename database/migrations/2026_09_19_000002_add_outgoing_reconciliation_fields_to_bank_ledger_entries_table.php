<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->foreignId('reconciled_by')->nullable()->after('matched_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('reconciled_by');
        });
    }

    public function down(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn(['reconciled_by', 'reconciled_at']);
        });
    }
};
