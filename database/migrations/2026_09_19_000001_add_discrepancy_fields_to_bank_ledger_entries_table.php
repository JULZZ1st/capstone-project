<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->decimal('discrepancy_amount', 12, 2)->nullable()->after('notes');
            $table->text('discrepancy_reason')->nullable()->after('discrepancy_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['discrepancy_amount', 'discrepancy_reason']);
        });
    }
};
