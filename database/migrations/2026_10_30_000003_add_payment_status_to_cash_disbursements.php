<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->string('payment_status', 30)->default('not_disbursed')->after('signed_voucher_requested_at');
            $table->timestamp('disbursed_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->dropColumn(['payment_status', 'disbursed_at']);
        });
    }
};
