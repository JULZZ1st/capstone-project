<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('purpose_type', 30)->nullable()->after('amount');
            $table->string('purpose_name', 255)->nullable()->after('purpose_type');
            $table->string('registered_bank_account', 150)->nullable()->after('bank_name');
            $table->string('donor_tin', 30)->nullable()->after('certificate_issued_at');
            $table->index(['type', 'purpose_type']);
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'purpose_type']);
            $table->dropColumn(['purpose_type', 'purpose_name', 'registered_bank_account', 'donor_tin']);
        });
    }
};
