<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('check_number', 100)->nullable()->after('reference_number');
            $table->string('bank_name', 150)->nullable()->after('check_number');
            $table->string('official_receipt_number', 100)->nullable()->after('bank_name');
            $table->index(['payment_method', 'check_number']);
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'check_number']);
            $table->dropColumn(['check_number', 'bank_name', 'official_receipt_number']);
        });
    }
};
