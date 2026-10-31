<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->string('signed_voucher_status', 30)->default('missing')->after('status');
            $table->text('signed_voucher_request_reason')->nullable()->after('signed_voucher_status');
            $table->foreignId('signed_voucher_requested_by')->nullable()->after('signed_voucher_request_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('signed_voucher_requested_at')->nullable()->after('signed_voucher_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_voucher_requested_by');
            $table->dropColumn([
                'signed_voucher_status',
                'signed_voucher_request_reason',
                'signed_voucher_requested_at',
            ]);
        });
    }
};
