<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->foreignId('prepared_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('prepared_by');
            $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->foreignId('correction_requested_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('correction_requested_at')->nullable()->after('correction_requested_by');
            $table->unsignedInteger('correction_count')->default(0)->after('correction_requested_at');
            $table->index(['status', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropForeign(['prepared_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['correction_requested_by']);
            $table->dropIndex(['status', 'period_start', 'period_end']);
            $table->dropColumn(['prepared_by', 'submitted_at', 'approved_by', 'approved_at', 'rejection_reason', 'correction_requested_by', 'correction_requested_at', 'correction_count']);
        });
    }
};
