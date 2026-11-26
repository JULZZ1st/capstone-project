<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            throw new RuntimeException('Cannot add signed voucher review fields: cash_disbursements table is missing.');
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_disbursements', 'signed_voucher_status')) {
                $table->string('signed_voucher_status', 30)->default('missing')->after('status');
            }
            if (! Schema::hasColumn('cash_disbursements', 'signed_voucher_request_reason')) {
                $table->text('signed_voucher_request_reason')->nullable()->after('signed_voucher_status');
            }
            if (! Schema::hasColumn('cash_disbursements', 'signed_voucher_requested_by')) {
                $table->foreignId('signed_voucher_requested_by')->nullable()->after('signed_voucher_request_reason')->constrained('users')->nullOnDelete();
            } elseif (! $this->hasForeignKey('cash_disbursements', 'cash_disbursements_signed_voucher_requested_by_foreign')) {
                $table->foreign('signed_voucher_requested_by', 'cash_disbursements_signed_voucher_requested_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_disbursements', 'signed_voucher_requested_at')) {
                $table->timestamp('signed_voucher_requested_at')->nullable()->after('signed_voucher_requested_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            return;
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if ($this->hasForeignKey('cash_disbursements', 'cash_disbursements_signed_voucher_requested_by_foreign')) {
                $table->dropForeign('cash_disbursements_signed_voucher_requested_by_foreign');
            }
            $columns = array_values(array_filter([
                'signed_voucher_status',
                'signed_voucher_request_reason',
                'signed_voucher_requested_by',
                'signed_voucher_requested_at',
            ], fn (string $column): bool => Schema::hasColumn('cash_disbursements', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }
};
