<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_disbursements', 'emergency_category')) {
            Schema::table('cash_disbursements', function (Blueprint $table): void {
                $table->string('emergency_category', 100)->nullable()->after('purpose');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_disbursements', 'emergency_category')) {
            Schema::table('cash_disbursements', fn (Blueprint $table) => $table->dropColumn('emergency_category'));
        }
    }
};
