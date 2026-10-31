<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->string('payee_address')->nullable()->after('recipient');
            $table->string('payee_tin', 30)->nullable()->after('payee_address');
            $table->string('fund_source')->nullable()->after('payee_tin');
            $table->string('obr_number')->nullable()->after('fund_source');
            $table->string('dv_number', 30)->nullable()->after('obr_number');
            $table->foreignId('treasurer_certified_by')->nullable()->after('dv_number')->constrained('users')->nullOnDelete();
            $table->timestamp('treasurer_certified_at')->nullable()->after('treasurer_certified_by');
            $table->text('treasurer_certification_notes')->nullable()->after('treasurer_certified_at');
            $table->unique(['workspace_id', 'dv_number']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'dv_number']);
            $table->dropConstrainedForeignId('treasurer_certified_by');
            $table->dropColumn([
                'payee_address', 'payee_tin', 'fund_source', 'obr_number', 'dv_number',
                'treasurer_certified_at', 'treasurer_certification_notes',
            ]);
        });
    }
};
