<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->string('duplicate_fingerprint', 64)->nullable()->after('statement_id');
            $table->index('duplicate_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('bank_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['duplicate_fingerprint']);
            $table->dropColumn('duplicate_fingerprint');
        });
    }
};
