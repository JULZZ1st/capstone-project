<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            throw new RuntimeException('Cannot add payment status: cash_disbursements table is missing.');
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_disbursements', 'payment_status')) {
                $column = $table->string('payment_status', 30)->default('not_disbursed');
                if (Schema::hasColumn('cash_disbursements', 'signed_voucher_requested_at')) {
                    $column->after('signed_voucher_requested_at');
                }
            }
            if (! Schema::hasColumn('cash_disbursements', 'disbursed_at')) {
                $column = $table->timestamp('disbursed_at')->nullable();
                if (Schema::hasColumn('cash_disbursements', 'payment_status')) {
                    $column->after('payment_status');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            return;
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['payment_status', 'disbursed_at'],
                fn (string $column): bool => Schema::hasColumn('cash_disbursements', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
